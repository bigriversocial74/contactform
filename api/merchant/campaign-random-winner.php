<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_random_winner_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_random_winner_expiry(array $row): ?string
{
    $rule = (string) ($row['expiration_rule'] ?? 'none');
    if ($rule === 'fixed_date' || $rule === 'event_date') return $row['expires_at'] ?: null;
    if (($rule === 'after_issue' || $rule === 'after_claim') && !empty($row['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int) $row['expiration_days'] * 86400));
    return null;
}

function mg_random_winner_event(PDO $pdo, int $merchantId, int $campaignId, ?int $walletItemId, ?int $contactId, string $eventType, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_random_winner_uuid(), $merchantId, $campaignId, $walletItemId, $contactId, $eventType, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$campaignPublicId = strtolower(trim((string) ($input['campaign_id'] ?? '')));
if ($campaignPublicId === '' || strlen($campaignPublicId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $campaignPublicId)) {
    mg_fail('Invalid campaign.', 422);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT c.*, rt.id reward_template_db_id, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.value_amount_cents, rt.currency, rt.expiration_rule, rt.expiration_days, rt.expires_at, rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count
        FROM campaigns c
        INNER JOIN reward_templates rt ON rt.id = c.reward_template_id
        WHERE c.public_id = ? AND c.merchant_user_id = ? AND c.campaign_type = \'contest_giveaway\'
        LIMIT 1');
    $stmt->execute([$campaignPublicId, $merchantId]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        $pdo->rollBack();
        mg_fail('Contest campaign not found.', 404);
    }
    if ($campaign['reward_template_quantity_limit'] !== null && (int) $campaign['reward_template_issued_count'] >= (int) $campaign['reward_template_quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Reward template limit has been reached.', 409);
    }

    $contactStmt = $pdo->prepare('SELECT cc.*
        FROM campaign_contacts cc
        LEFT JOIN wallet_items winner_wallet ON winner_wallet.contact_id = cc.id AND winner_wallet.campaign_id = cc.campaign_id AND winner_wallet.source_type = \'contest_winner\' AND winner_wallet.status <> \'cancelled\'
        WHERE cc.campaign_id = ? AND cc.merchant_user_id = ? AND cc.source = \'contest_entry\' AND winner_wallet.id IS NULL
        ORDER BY RAND()
        LIMIT 1');
    $contactStmt->execute([(int) $campaign['id'], $merchantId]);
    $contact = $contactStmt->fetch();
    if (!$contact) {
        $pdo->rollBack();
        mg_fail('No eligible contest entries are available for this drawing.', 409);
    }

    mg_random_winner_event($pdo, $merchantId, (int) $campaign['id'], null, (int) $contact['id'], 'contest.random_winner_selected', ['contact_id' => (string) $contact['public_id']]);

    $walletPublicId = mg_random_winner_uuid();
    $expiresAt = mg_random_winner_expiry($campaign);
    $wallet = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
    $wallet->execute([$walletPublicId, $contact['user_id'] ? (int) $contact['user_id'] : null, (int) $contact['id'], $merchantId, (int) $campaign['reward_template_db_id'], (int) $campaign['id'], 'contest_winner', (string) $contact['public_id'], 'issued', (int) $campaign['value_amount_cents'], (string) $campaign['currency'], (string) $campaign['reward_template_title'], json_encode(['campaign_type' => 'contest_giveaway', 'reward_template_id' => (string) $campaign['reward_template_public_id'], 'winner' => true, 'random_draw' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
    $walletDbId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE campaigns SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int) $campaign['id']]);
    $pdo->prepare('UPDATE reward_templates SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int) $campaign['reward_template_db_id']]);
    mg_random_winner_event($pdo, $merchantId, (int) $campaign['id'], $walletDbId, (int) $contact['id'], 'wallet_item.issued', ['wallet_item_id' => $walletPublicId, 'source_type' => 'contest_winner', 'random_draw' => true]);

    $pdo->commit();
    mg_ok([
        'contact_id' => (string) $contact['public_id'],
        'email' => (string) $contact['email'],
        'name' => (string) ($contact['name'] ?? ''),
        'wallet_item_id' => $walletPublicId,
        'wallet_status' => 'issued',
        'expires_at' => $expiresAt,
    ], 'Random contest winner selected and reward issued.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.contest_random_winner.failed', 'Unable to select random contest winner.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Unable to select random contest winner.', 500);
}
