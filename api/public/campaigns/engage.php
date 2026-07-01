<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__, 3) . '/includes/merchant-crm.php';
require_once __DIR__ . '/_limits.php';
require_once __DIR__ . '/_merchant_notifications.php';

function mg_public_campaign_engage_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_public_campaign_engage_source(string $campaignType): string
{
    return match ($campaignType) {
        'contest_giveaway' => 'contest_entry',
        'qr_reward_drop' => 'qr_scan',
        'referral_reward' => 'referral',
        'birthday_vip' => 'birthday_vip',
        'agent_offer' => 'agent_discovery',
        default => 'newsletter_signup',
    };
}

function mg_public_campaign_engage_expiry(array $template): ?string
{
    $rule = (string)($template['expiration_rule'] ?? 'none');
    if ($rule === 'fixed_date' || $rule === 'event_date') return $template['expires_at'] ?: null;
    if ($rule === 'after_issue' && !empty($template['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int)$template['expiration_days'] * 86400));
    return null;
}

function mg_public_campaign_engage_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND status = \'active\' LIMIT 1');
    $stmt->execute([$email]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_public_campaign_engage_bridge(PDO $pdo, array $campaign, array $contact, int $walletDbId, string $walletPublicId, ?int $userId, ?string $expiresAt, string $source): ?array
{
    if (!$userId) return null;
    return mg_zero_reward_issue_from_wallet($pdo, [
        'merchant_user_id' => (int)$campaign['merchant_user_id'],
        'recipient_user_id' => $userId,
        'recipient_external_id' => (string)($contact['public_id'] ?? ''),
        'wallet_item_db_id' => $walletDbId,
        'wallet_item_public_id' => $walletPublicId,
        'campaign_public_id' => (string)$campaign['public_id'],
        'reward_template_public_id' => (string)$campaign['reward_template_public_id'],
        'source_type' => $source,
        'source_reference' => $walletPublicId,
        'source_line_reference' => (string)($contact['public_id'] ?? $walletPublicId),
        'title' => (string)$campaign['reward_template_title'],
        'description' => $campaign['reward_template_description'] ?? $campaign['description'] ?? null,
        'currency' => (string)($campaign['currency'] ?? 'USD'),
        'display_value_cents' => (int)($campaign['value_amount_cents'] ?? 0),
        'expires_at' => $expiresAt,
        'redemption_instructions' => $campaign['redemption_instructions'] ?? null,
        'terms' => ['campaign_type' => (string)$campaign['campaign_type']],
    ]);
}

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();

$campaignRef = strtolower(trim((string) ($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
$email = strtolower(trim((string) ($input['email'] ?? '')));
$name = trim((string) ($input['name'] ?? $input['full_name'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$entry = $input['entry'] ?? [];
if (!is_array($entry)) $entry = [];

if ($campaignRef === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255 || mb_strlen($name) > 180 || mb_strlen($phone) > 60) {
    mg_fail('Invalid campaign engagement.', 422);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT c.*, rt.id reward_template_db_id, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.description reward_template_description, rt.redemption_instructions, rt.value_amount_cents, rt.currency, rt.expiration_rule, rt.expiration_days, rt.expires_at, rt.quantity_limit reward_template_quantity_limit, rt.issued_count reward_template_issued_count, rt.per_user_limit reward_template_per_user_limit FROM campaigns c INNER JOIN reward_templates rt ON rt.id = c.reward_template_id WHERE c.status = \'active\' AND rt.status = \'active\' AND (c.public_id = ? OR c.public_slug = ?) LIMIT 1 FOR UPDATE');
    $stmt->execute([$campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        $pdo->rollBack();
        mg_fail('Campaign is not available.', 404);
    }

    $now = time();
    if (!empty($campaign['starts_at']) && strtotime((string) $campaign['starts_at']) > $now) {
        $pdo->rollBack();
        mg_fail('Campaign has not started yet.', 409);
    }
    if (!empty($campaign['ends_at']) && strtotime((string) $campaign['ends_at']) < $now) {
        $pdo->rollBack();
        mg_fail('Campaign has ended.', 409);
    }
    if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Campaign reward limit has been reached.', 409);
    }
    if ($campaign['reward_template_quantity_limit'] !== null && (int)$campaign['reward_template_issued_count'] >= (int)$campaign['reward_template_quantity_limit']) {
        $pdo->rollBack();
        mg_fail('Reward template limit has been reached.', 409);
    }

    $merchantId = (int) $campaign['merchant_user_id'];
    $campaignId = (int) $campaign['id'];
    $campaignType = (string) $campaign['campaign_type'];
    $rewardTemplateId = (int) $campaign['reward_template_db_id'];
    $source = mg_public_campaign_engage_source($campaignType);
    $userId = mg_public_campaign_engage_find_user($pdo, $email);
    $contactPublicId = mg_public_campaign_engage_uuid();
    $existingContactStmt = $pdo->prepare('SELECT id, public_id FROM campaign_contacts WHERE campaign_id = ? AND email = ? LIMIT 1 FOR UPDATE');
    $existingContactStmt->execute([$campaignId, $email]);
    $existingContact = $existingContactStmt->fetch(PDO::FETCH_ASSOC);
    $isNewContact = !$existingContact;
    $metadata = [
        'campaign_type' => $campaignType,
        'campaign_public_id' => (string) $campaign['public_id'],
        'crm_source' => $source,
        'entry' => $entry,
        'ip' => mg_client_ip(),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'generic_engagement' => true,
    ];

    $contactStmt = $pdo->prepare('INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), phone=VALUES(phone), name=VALUES(name), source=VALUES(source), metadata_json=VALUES(metadata_json), updated_at=NOW()');
    $contactStmt->execute([$contactPublicId, $merchantId, $campaignId, $userId, $email, $phone !== '' ? $phone : null, $name !== '' ? $name : null, $source, 'opted_in', json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

    $contactLookup = $pdo->prepare('SELECT id, public_id FROM campaign_contacts WHERE campaign_id = ? AND email = ? LIMIT 1');
    $contactLookup->execute([$campaignId, $email]);
    $contact = $contactLookup->fetch(PDO::FETCH_ASSOC);
    $contactId = (int) ($contact['id'] ?? 0);

    $crm = mg_merchant_crm_record_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_id' => $campaignId,
        'campaign_type' => $campaignType,
        'event_type' => 'campaign.engaged',
        'source_type' => $source,
        'source_public_id' => (string) ($contact['public_id'] ?? ''),
        'user_id' => $userId,
        'email' => $email,
        'phone' => $phone,
        'name' => $name,
        'metadata' => $metadata,
    ]);
    $merchantNotification = mg_public_campaign_notify_merchant_contact($pdo, $campaign, $contact ?: [], $email, $name, $phone, $source, $crm, $isNewContact);

    $eventStmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $eventStmt->execute([
        mg_public_campaign_engage_uuid(),
        $merchantId,
        $campaignId,
        null,
        $contactId ?: null,
        'campaign.engaged',
        json_encode(['campaign_type' => $campaignType, 'source' => $source, 'email' => $email, 'crm_entry' => true, 'merchant_crm' => $crm, 'merchant_notification' => $merchantNotification], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $expiresAt = mg_public_campaign_engage_expiry($campaign);
    $existingStmt = $pdo->prepare('SELECT id, public_id, status FROM wallet_items WHERE campaign_id = ? AND contact_id = ? AND source_type = ? AND status <> \'cancelled\' ORDER BY id DESC LIMIT 1');
    $existingStmt->execute([$campaignId, $contactId, $source]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $bridge = mg_public_campaign_engage_bridge($pdo, $campaign, $contact, (int)$existing['id'], (string)$existing['public_id'], $userId, $expiresAt, $source);
        $pdo->commit();
        mg_ok([
            'contact_id' => (string) ($contact['public_id'] ?? ''),
            'campaign_id' => (string) $campaign['public_id'],
            'campaign_type' => $campaignType,
            'source' => $source,
            'wallet_item_id' => (string)$existing['public_id'],
            'wallet_status' => (string)$existing['status'],
            'already_issued' => true,
            'reward_title' => (string)$campaign['reward_template_title'],
            'expires_at' => $expiresAt,
            'pppm_bridge' => $bridge,
            'merchant_crm' => $crm,
            'merchant_notification' => $merchantNotification,
        ], 'Campaign reward already issued.');
    }

    mg_public_campaign_enforce_reward_limits($pdo, $campaign, $userId, $email);
    $walletPublicId = mg_public_campaign_engage_uuid();
    $stampLedger = mg_public_campaign_debit_reward_stamp($pdo, $campaign, $walletPublicId, $source, [
        'contact_id' => (string)($contact['public_id'] ?? ''),
        'email' => $email,
        'generic_engagement' => true,
    ]);
    $walletMetadata = [
        'campaign_type' => $campaignType,
        'reward_template_id' => (string)$campaign['reward_template_public_id'],
        'generic_engagement' => true,
        'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null,
    ];
    $walletStmt = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
    $walletStmt->execute([
        $walletPublicId,
        $userId,
        $contactId,
        $merchantId,
        $rewardTemplateId,
        $campaignId,
        $source,
        (string)($contact['public_id'] ?? ''),
        'issued',
        (int)($campaign['value_amount_cents'] ?? 0),
        (string)($campaign['currency'] ?? 'USD'),
        (string)$campaign['reward_template_title'],
        json_encode($walletMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $expiresAt,
    ]);
    $walletDbId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE campaigns SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([$campaignId]);
    $pdo->prepare('UPDATE reward_templates SET issued_count = issued_count + 1, updated_at = NOW() WHERE id = ?')->execute([$rewardTemplateId]);
    $bridge = mg_public_campaign_engage_bridge($pdo, $campaign, $contact, $walletDbId, $walletPublicId, $userId, $expiresAt, $source);
    $eventStmt->execute([
        mg_public_campaign_engage_uuid(),
        $merchantId,
        $campaignId,
        $walletDbId,
        $contactId ?: null,
        'wallet_item.issued',
        json_encode(['wallet_item_id' => $walletPublicId, 'campaign_type' => $campaignType, 'source' => $source, 'pppm_bridge' => $bridge, 'merchant_crm' => $crm, 'merchant_notification' => $merchantNotification, 'stamp_ledger_entry_id' => $stampLedger['entry']['entry_id'] ?? null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();
    mg_ok([
        'contact_id' => (string) ($contact['public_id'] ?? ''),
        'campaign_id' => (string) $campaign['public_id'],
        'campaign_type' => $campaignType,
        'source' => $source,
        'wallet_item_id' => $walletPublicId,
        'wallet_status' => 'issued',
        'already_issued' => false,
        'reward_title' => (string)$campaign['reward_template_title'],
        'expires_at' => $expiresAt,
        'pppm_bridge' => $bridge,
        'merchant_crm' => $crm,
        'merchant_notification' => $merchantNotification,
        'stamp_ledger' => $stampLedger,
    ], (string) ($campaign['success_message'] ?? 'Campaign reward issued.'), 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.campaign_engage.failed', 'Unable to record campaign engagement.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
    mg_fail('Unable to record campaign engagement.', 500);
}
