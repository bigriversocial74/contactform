<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

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
$input = mg_input();
$pdo = mg_db();

$offerId = strtolower(trim((string) ($input['offer_id'] ?? $input['reward_template_id'] ?? '')));
$email = strtolower(trim((string) ($input['email'] ?? '')));

if ($offerId === '' || strlen($offerId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $offerId) || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mg_fail('Invalid add-to-wallet request.', 422);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM reward_templates WHERE public_id = ? AND status = \'active\' AND agent_discoverable = 1 AND agent_add_to_wallet_allowed = 1 LIMIT 1');
    $stmt->execute([$offerId]);
    $template = $stmt->fetch();
    if (!$template) {
        $pdo->rollBack();
        mg_fail('Offer is not available for wallet add.', 404);
    }

    if ($template['quantity_limit'] !== null && (int) $template['issued_count'] >= (int) $template['quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Offer limit has been reached.', 409);
    }

    $userStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND status = \'active\' LIMIT 1');
    $userStmt->execute([$email]);
    $userId = (int) ($userStmt->fetchColumn() ?: 0);
    $userId = $userId > 0 ? $userId : null;

    $existing = $pdo->prepare('SELECT public_id,status FROM wallet_items WHERE reward_template_id = ? AND source_type = \'agent_discovery\' AND source_id = ? AND status <> \'cancelled\' ORDER BY id DESC LIMIT 1');
    $existing->execute([(int) $template['id'], $email]);
    $prior = $existing->fetch();
    if ($prior) {
        $pdo->commit();
        mg_ok(['wallet_item_id' => (string) $prior['public_id'], 'wallet_status' => (string) $prior['status'], 'already_added' => true], 'Offer already added to wallet.');
    }

    $walletPublicId = mg_wallet_add_uuid();
    $expiresAt = mg_wallet_add_expiry($template);
    $wallet = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
    $wallet->execute([$walletPublicId, $userId, null, (int) $template['merchant_user_id'], (int) $template['id'], null, 'agent_discovery', $email, 'issued', (int) $template['value_amount_cents'], (string) $template['currency'], (string) $template['title'], json_encode(['source' => 'agent_discovery', 'offer_id' => $offerId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
    $walletDbId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE reward_templates SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int) $template['id']]);
    $event = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $event->execute([mg_wallet_add_uuid(), (int) $template['merchant_user_id'], null, $walletDbId, null, 'agent_offer.added_to_wallet', json_encode(['email' => $email, 'offer_id' => $offerId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

    $pdo->commit();
    mg_ok(['wallet_item_id' => $walletPublicId, 'wallet_status' => 'issued', 'already_added' => false, 'expires_at' => $expiresAt], 'Offer added to wallet.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.wallet_add.failed', 'Unable to add offer to wallet.', ['exception_class' => $error::class]);
    mg_fail('Unable to add offer to wallet.', 500);
}
