<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

function mg_qr_campaign_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_qr_campaign_expiry(array $template): ?string
{
    $rule = (string) ($template['expiration_rule'] ?? 'none');
    if ($rule === 'fixed_date' || $rule === 'event_date') return $template['expires_at'] ?: null;
    if ($rule === 'after_issue' && !empty($template['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int) $template['expiration_days'] * 86400));
    return null;
}

function mg_qr_campaign_event(PDO $pdo, int $merchantId, int $campaignId, ?int $walletItemId, ?int $contactId, string $eventType, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_qr_campaign_uuid(), $merchantId, $campaignId, $walletItemId, $contactId, $eventType, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function mg_qr_campaign_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND status = \'active\' LIMIT 1');
    $stmt->execute([$email]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();

$campaignRef = strtolower(trim((string) ($input['campaign_id'] ?? $input['campaign'] ?? '')));
$qrToken = trim((string) ($input['qr_token'] ?? $input['token'] ?? ''));
$email = strtolower(trim((string) ($input['email'] ?? '')));
$name = trim((string) ($input['name'] ?? $input['full_name'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));

if (($campaignRef === '' && $qrToken === '') || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255 || mb_strlen($name) > 180 || mb_strlen($phone) > 60) {
    mg_fail('Invalid QR pickup submission.', 422);
}

try {
    $pdo->beginTransaction();

    $sql = 'SELECT c.*, rt.id reward_template_db_id, rt.public_id reward_template_public_id, rt.title reward_template_title,
                   rt.value_amount_cents, rt.currency, rt.expiration_rule, rt.expiration_days, rt.expires_at,
                   rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count
            FROM campaigns c
            INNER JOIN reward_templates rt ON rt.id = c.reward_template_id
            WHERE c.campaign_type = \'qr_reward_drop\'
              AND c.status = \'active\'
              AND rt.status = \'active\'
              AND ((? <> \'\' AND c.qr_code_token = ?) OR (? <> \'\' AND c.public_id = ?))
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$qrToken, $qrToken, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        $pdo->rollBack();
        mg_fail('QR reward drop is not available.', 404);
    }

    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string) $campaign['starts_at']) > $now) {
        $pdo->rollBack();
        mg_fail('QR reward drop has not started yet.', 409);
    }
    if (!empty($campaign['ends_at']) && strtotime((string) $campaign['ends_at']) < $now) {
        $pdo->rollBack();
        mg_fail('QR reward drop has ended.', 409);
    }
    if ($campaign['quantity_limit'] !== null && (int) $campaign['issued_count'] >= (int) $campaign['quantity_limit']) {
        $pdo->rollBack();
        mg_fail('QR reward drop limit has been reached.', 409);
    }
    if ($campaign['reward_template_quantity_limit'] !== null && (int) $campaign['reward_template_issued_count'] >= (int) $campaign['reward_template_quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Reward template limit has been reached.', 409);
    }

    $merchantId = (int) $campaign['merchant_user_id'];
    $campaignId = (int) $campaign['id'];
    $rewardTemplateId = (int) $campaign['reward_template_db_id'];
    $userId = mg_qr_campaign_find_user($pdo, $email);

    $contactPublicId = mg_qr_campaign_uuid();
    $contactStmt = $pdo->prepare('INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), phone=VALUES(phone), name=VALUES(name), metadata_json=VALUES(metadata_json), updated_at=NOW()');
    $contactStmt->execute([$contactPublicId, $merchantId, $campaignId, $userId, $email, $phone !== '' ? $phone : null, $name !== '' ? $name : null, 'qr_scan', 'unknown', json_encode(['qr_token_present' => $qrToken !== '', 'ip' => mg_client_ip()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

    $contactLookup = $pdo->prepare('SELECT id, public_id FROM campaign_contacts WHERE campaign_id = ? AND email = ? LIMIT 1');
    $contactLookup->execute([$campaignId, $email]);
    $contact = $contactLookup->fetch();
    $contactId = (int) $contact['id'];

    mg_qr_campaign_event($pdo, $merchantId, $campaignId, null, $contactId, 'qr.scanned', ['email' => $email]);

    $existingStmt = $pdo->prepare('SELECT public_id,status FROM wallet_items WHERE campaign_id = ? AND contact_id = ? AND source_type = \'qr_scan\' AND status <> \'cancelled\' ORDER BY id DESC LIMIT 1');
    $existingStmt->execute([$campaignId, $contactId]);
    $existing = $existingStmt->fetch();
    if ($existing) {
        $pdo->commit();
        mg_ok(['contact_id' => (string) $contact['public_id'], 'wallet_item_id' => (string) $existing['public_id'], 'wallet_status' => (string) $existing['status'], 'already_issued' => true], 'QR reward already added.');
    }

    $walletPublicId = mg_qr_campaign_uuid();
    $expiresAt = mg_qr_campaign_expiry($campaign);
    $walletStmt = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
    $walletStmt->execute([$walletPublicId, $userId, $contactId, $merchantId, $rewardTemplateId, $campaignId, 'qr_scan', (string) $contact['public_id'], 'issued', (int) $campaign['value_amount_cents'], (string) $campaign['currency'], (string) $campaign['reward_template_title'], json_encode(['campaign_type' => 'qr_reward_drop', 'reward_template_id' => (string) $campaign['reward_template_public_id']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
    $walletDbId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE campaigns SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([$campaignId]);
    $pdo->prepare('UPDATE reward_templates SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([$rewardTemplateId]);
    mg_qr_campaign_event($pdo, $merchantId, $campaignId, $walletDbId, $contactId, 'wallet_item.issued', ['wallet_item_id' => $walletPublicId]);

    $pdo->commit();
    mg_ok(['contact_id' => (string) $contact['public_id'], 'wallet_item_id' => $walletPublicId, 'wallet_status' => 'issued', 'already_issued' => false, 'reward_title' => (string) $campaign['reward_template_title'], 'expires_at' => $expiresAt], 'QR reward added to wallet.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.qr_pickup.failed', 'Unable to process QR reward pickup.', ['exception_class' => $error::class]);
    mg_fail('Unable to process QR reward pickup.', 500);
}
