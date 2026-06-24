<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

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

function mg_public_campaign_engage_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND status = \'active\' LIMIT 1');
    $stmt->execute([$email]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
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
    $stmt = $pdo->prepare('SELECT * FROM campaigns WHERE status = \'active\' AND (public_id = ? OR public_slug = ?) LIMIT 1 FOR UPDATE');
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

    $merchantId = (int) $campaign['merchant_user_id'];
    $campaignId = (int) $campaign['id'];
    $campaignType = (string) $campaign['campaign_type'];
    $source = mg_public_campaign_engage_source($campaignType);
    $userId = mg_public_campaign_engage_find_user($pdo, $email);
    $contactPublicId = mg_public_campaign_engage_uuid();
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

    $eventStmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $eventStmt->execute([
        mg_public_campaign_engage_uuid(),
        $merchantId,
        $campaignId,
        null,
        $contactId ?: null,
        'campaign.engaged',
        json_encode(['campaign_type' => $campaignType, 'source' => $source, 'email' => $email, 'crm_entry' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();
    mg_ok([
        'contact_id' => (string) ($contact['public_id'] ?? ''),
        'campaign_id' => (string) $campaign['public_id'],
        'campaign_type' => $campaignType,
        'source' => $source,
    ], (string) ($campaign['success_message'] ?? 'Campaign response recorded.'), 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.campaign_engage.failed', 'Unable to record campaign engagement.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
    mg_fail('Unable to record campaign engagement.', 500);
}
