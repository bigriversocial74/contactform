<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/ads/_direct_attribution.php';

function mg_wallet_add_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_wallet_add_expiry(array $template): ?string
{
    $rule = (string) ($template['expiration_rule'] ?? 'none');
    if ($rule === 'fixed_date' || $rule === 'event_date') return $template['expires_at'] ?: null;
    if (($rule === 'after_issue' || $rule === 'after_claim') && !empty($template['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int) $template['expiration_days'] * 86400));
    return null;
}

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
$pdo = mg_db();

$offerId = strtolower(trim((string) ($input['offer_id'] ?? $input['reward_template_id'] ?? '')));
$sessionEmail = strtolower(trim((string) ($user['email'] ?? '')));
$requestEmail = strtolower(trim((string) ($input['email'] ?? $sessionEmail)));
$email = $requestEmail !== '' ? $requestEmail : $sessionEmail;
$adAttribution = mg_ads_direct_attribution_from_input($input);

if ($offerId === '' || strlen($offerId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $offerId) || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mg_fail('Invalid add-to-wallet request.', 422);
}
if ($sessionEmail === '' || $email !== $sessionEmail) {
    mg_security_log('warning', 'public.wallet_add.email_mismatch', 'Wallet add requested for a different email.', ['requested_email' => $email], (int) $user['id']);
    mg_fail('Wallet add requires the signed-in user to approve the offer for their own wallet.', 403);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM reward_templates WHERE public_id = ? AND status = \'active\' AND agent_discoverable = 1 AND agent_add_to_wallet_allowed = 1 LIMIT 1');
    $stmt->execute([$offerId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) {
        $pdo->rollBack();
        mg_fail('Offer is not available for wallet add.', 404);
    }

    if ($template['quantity_limit'] !== null && (int) $template['issued_count'] >= (int) $template['quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Offer limit has been reached.', 409);
    }

    $userId = (int) $user['id'];
    $existing = $pdo->prepare('SELECT * FROM wallet_items WHERE reward_template_id = ? AND user_id = ? AND source_type = \'agent_discovery\' AND status <> \'cancelled\' ORDER BY id DESC LIMIT 1');
    $existing->execute([(int) $template['id'], $userId]);
    $prior = $existing->fetch(PDO::FETCH_ASSOC);
    if ($prior) {
        $pdo->commit();
        mg_ads_track_direct_wallet_event($pdo, 'wallet_save', $prior, ['ad_attribution' => $adAttribution], $user, ['already_added' => true, 'offer_id' => $offerId]);
        mg_ok(['wallet_item_id' => (string) $prior['public_id'], 'wallet_status' => (string) $prior['status'], 'already_added' => true], 'Offer already added to wallet.');
    }

    $walletPublicId = mg_wallet_add_uuid();
    $expiresAt = mg_wallet_add_expiry($template);
    $metadata = mg_ads_wallet_metadata_with_attribution([
        'source' => 'agent_discovery',
        'offer_id' => $offerId,
        'approved_by_user' => true,
    ], $adAttribution);

    $wallet = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
    $wallet->execute([$walletPublicId, $userId, null, (int) $template['merchant_user_id'], (int) $template['id'], null, 'agent_discovery', $sessionEmail, 'issued', (int) $template['value_amount_cents'], (string) $template['currency'], (string) $template['title'], json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
    $walletDbId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE reward_templates SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int) $template['id']]);
    $event = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $event->execute([mg_wallet_add_uuid(), (int) $template['merchant_user_id'], null, $walletDbId, null, 'agent_offer.added_to_wallet', json_encode(['email' => $sessionEmail, 'offer_id' => $offerId, 'approved_by_user' => true, 'ad_attribution' => $adAttribution ?: null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

    $walletItem = [
        'id' => $walletDbId,
        'public_id' => $walletPublicId,
        'user_id' => $userId,
        'merchant_user_id' => (int) $template['merchant_user_id'],
        'reward_template_id' => (int) $template['id'],
        'campaign_id' => null,
        'source_type' => 'agent_discovery',
        'source_id' => $sessionEmail,
        'status' => 'issued',
        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];

    $pdo->commit();
    mg_ads_track_direct_wallet_event($pdo, 'wallet_save', $walletItem, ['ad_attribution' => $adAttribution], $user, ['already_added' => false, 'offer_id' => $offerId]);
    mg_ok(['wallet_item_id' => $walletPublicId, 'wallet_status' => 'issued', 'already_added' => false, 'expires_at' => $expiresAt], 'Offer added to wallet.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.wallet_add.failed', 'Unable to add offer to wallet.', ['exception_class' => $error::class], (int) $user['id']);
    mg_fail('Unable to add offer to wallet.', 500);
}
