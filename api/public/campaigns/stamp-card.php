<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';
require_once dirname(__DIR__, 3) . '/includes/merchant-crm-value-events.php';
require_once __DIR__ . '/_merchant_notifications.php';
require_once __DIR__ . '/_embed_attribution.php';

function mg_stamp_card_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_stamp_card_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_stamp_card_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_stamp_card_required_count(array $campaign): int
{
    $rules = mg_stamp_card_json($campaign['rules_json'] ?? null);
    $required = (int)($rules['required_count'] ?? $rules['stamp_required_count'] ?? 5);
    return max(1, min(100, $required));
}

function mg_stamp_card_cooldown_hours(array $campaign): int
{
    $rules = mg_stamp_card_json($campaign['rules_json'] ?? null);
    $hours = (int)($rules['cooldown_hours'] ?? $rules['stamp_cooldown_hours'] ?? 0);
    return max(0, min(8760, $hours));
}

function mg_stamp_card_label(array $campaign): string
{
    $rules = mg_stamp_card_json($campaign['rules_json'] ?? null);
    $label = trim((string)($rules['stamp_label'] ?? 'Visit'));
    return $label !== '' ? mb_substr($label, 0, 40) : 'Visit';
}

function mg_stamp_card_cashier_required(array $campaign): bool
{
    $rules = mg_stamp_card_json($campaign['rules_json'] ?? null);
    return array_key_exists('cashier_verification_required', $rules) ? !empty($rules['cashier_verification_required']) : true;
}

function mg_stamp_card_claim_code_normalize(string $code): string
{
    $code = strtoupper(trim($code));
    if (mb_strlen($code) < 4 || mb_strlen($code) > 64 || !preg_match('/^[A-Z0-9_-]{4,64}$/', $code)) {
        mg_fail('Cashier claim code must be 4 to 64 letters, numbers, dashes, or underscores.', 422);
    }
    return $code;
}

function mg_stamp_card_claim_code_pepper(): string
{
    $config = require dirname(__DIR__) . '/config.php';
    $configured = trim((string)($config['security']['claim_code_pepper'] ?? ''));
    if ($configured !== '') return $configured;
    $root = trim((string)($config['storage']['root'] ?? ''));
    if ($root !== '') {
        $path = rtrim($root, "/\\") . DIRECTORY_SEPARATOR . '.secrets' . DIRECTORY_SEPARATOR . 'claim-code-pepper';
        if (is_file($path) && !is_link($path)) {
            $value = trim((string)file_get_contents($path));
            if (preg_match('/^[a-f0-9]{64}$/i', $value) === 1) return strtolower($value);
        }
    }
    mg_fail('Merchant claim-code verification is not configured.', 503);
}

function mg_stamp_card_verify_cashier_code(PDO $pdo, int $merchantId, string $rawCode): array
{
    $code = mg_stamp_card_claim_code_normalize($rawCode);
    $hash = hash_hmac('sha256', $code, mg_stamp_card_claim_code_pepper());
    $stmt = $pdo->prepare("SELECT mcc.id claim_code_id,mcc.public_id claim_code_public_id,mcc.location_id,mcc.label,mcc.code_last4,mcc.usage_limit,mcc.usage_count,ml.public_id location_public_id,ml.name location_name
        FROM merchant_claim_codes mcc
        INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
        WHERE mcc.merchant_user_id=? AND mcc.code_hash=? AND mcc.status='active' AND ml.status='active'
          AND (mcc.valid_from IS NULL OR mcc.valid_from<=NOW())
          AND (mcc.valid_until IS NULL OR mcc.valid_until>=NOW())
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$merchantId, $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Cashier claim code could not be verified for this merchant.', 422);
    if ($row['usage_limit'] !== null && (int)$row['usage_count'] >= (int)$row['usage_limit']) mg_fail('Cashier claim code usage limit has been reached.', 409);
    return $row;
}

function mg_stamp_card_record_stamp(PDO $pdo, array $campaign, array $input, array $entry, array $embedAttribution, int $stampCount, int $requiredCount, ?array $verification): array
{
    $merchantId = (int)$campaign['merchant_user_id'];
    $campaignId = (int)$campaign['id'];
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $name = trim((string)($input['name'] ?? $input['full_name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $source = 'stamp_card_reward';
    $userId = mg_stamp_card_find_user($pdo, $email);
    $verificationMeta = $verification ? [
        'claim_code_id' => (string)$verification['claim_code_public_id'],
        'claim_code_last4' => (string)$verification['code_last4'],
        'location_id' => (string)$verification['location_public_id'],
        'location_name' => (string)$verification['location_name'],
        'verified_at' => gmdate('c'),
    ] : ['verified_at' => gmdate('c'), 'cashier_required' => false];
    $metadata = mg_public_campaign_metadata_with_embed([
        'campaign_type' => 'stamp_card_reward',
        'campaign_public_id' => (string)$campaign['public_id'],
        'crm_source' => $source,
        'entry' => $entry,
        'stamp_count' => $stampCount,
        'required_count' => $requiredCount,
        'stamps_remaining' => max(0, $requiredCount - $stampCount),
        'stamp_result' => 'recorded',
        'stamp_verification' => $verificationMeta,
        'crm_creation_boundary' => 'deferred_until_first_value_event',
        'value_event' => false,
        'ip' => mg_client_ip(),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ], $embedAttribution);

    $contactPublicId = mg_stamp_card_uuid();
    $stmt = $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), phone=VALUES(phone), name=VALUES(name), source=VALUES(source), metadata_json=VALUES(metadata_json), updated_at=NOW()");
    $stmt->execute([$contactPublicId, $merchantId, $campaignId, $userId, $email, $phone !== '' ? $phone : null, $name !== '' ? $name : null, $source, 'opted_in', json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $lookup = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1');
    $lookup->execute([$campaignId, $email]);
    $contact = $lookup->fetch(PDO::FETCH_ASSOC) ?: [];
    $contactId = (int)($contact['id'] ?? 0);

    $crm = mg_merchant_crm_record_existing_contact_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_id' => $campaignId,
        'campaign_type' => 'stamp_card_reward',
        'event_type' => 'stamp_card.stamped',
        'source_type' => $source,
        'source_public_id' => (string)($contact['public_id'] ?? ''),
        'user_id' => $userId,
        'email' => $email,
        'phone' => $phone,
        'name' => $name,
        'metadata' => $metadata,
    ]);
    $merchantNotification = mg_public_campaign_notify_merchant_contact($pdo, $campaign, $contact, $email, $name, $phone, $source, $crm, false);
    $event = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $event->execute([
        mg_stamp_card_uuid(),
        $merchantId,
        $campaignId,
        null,
        $contactId ?: null,
        'stamp_card.stamped',
        json_encode(['campaign_type' => 'stamp_card_reward', 'source' => $source, 'email' => $email, 'entry' => $entry, 'stamp_count' => $stampCount, 'required_count' => $requiredCount, 'stamps_remaining' => max(0, $requiredCount - $stampCount), 'stamp_verification' => $verificationMeta, 'embed_attribution' => $embedAttribution, 'merchant_crm' => $crm, 'merchant_notification' => $merchantNotification, 'crm_creation_boundary' => 'deferred_until_first_value_event'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    if ($verification) {
        $pdo->prepare('UPDATE merchant_claim_codes SET usage_count=usage_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$verification['claim_code_id']]);
    }

    return ['contact' => $contact, 'crm' => $crm, 'merchant_notification' => $merchantNotification, 'verification' => $verificationMeta];
}

mg_require_method('POST');
$input = mg_input();
$pdo = mg_db();
$campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
$email = strtolower(trim((string)($input['email'] ?? '')));
$entry = $input['entry'] ?? [];
if (!is_array($entry)) $entry = [];
$embedAttribution = mg_public_campaign_embed_attribution($input);

if ($campaignRef === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mg_fail('Invalid stamp card visit.', 422);
}

$stmt = $pdo->prepare("SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title FROM campaigns c INNER JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.status='active' AND rt.status='active' AND (c.public_id=? OR c.public_slug=?) LIMIT 1");
$stmt->execute([$campaignRef, $campaignRef]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign || (string)$campaign['campaign_type'] !== 'stamp_card_reward') {
    mg_fail('Stamp card campaign is not available.', 404);
}
$now = time();
if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) mg_fail('Stamp card campaign has not started yet.', 409);
if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) mg_fail('Stamp card campaign has ended.', 409);
if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) mg_fail('Stamp card reward limit has been reached.', 409);

$requiredCount = mg_stamp_card_required_count($campaign);
$cooldownHours = mg_stamp_card_cooldown_hours($campaign);
$stampLabel = mg_stamp_card_label($campaign);
$cashierRequired = mg_stamp_card_cashier_required($campaign);
$verification = null;
try {
    $pdo->beginTransaction();
    if ($cashierRequired) {
        $verification = mg_stamp_card_verify_cashier_code($pdo, (int)$campaign['merchant_user_id'], (string)($input['cashier_code'] ?? $input['merchant_claim_code'] ?? ''));
    }
    $contactStmt = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1 FOR UPDATE');
    $contactStmt->execute([(int)$campaign['id'], $email]);
    $existingContact = $contactStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $previousStamps = 0;
    $lastStampAt = null;
    if ($existingContact) {
        $countStmt = $pdo->prepare("SELECT COUNT(*), MAX(created_at) FROM campaign_events WHERE campaign_id=? AND contact_id=? AND event_type='stamp_card.stamped'");
        $countStmt->execute([(int)$campaign['id'], (int)$existingContact['id']]);
        $row = $countStmt->fetch(PDO::FETCH_NUM) ?: [0, null];
        $previousStamps = (int)($row[0] ?? 0);
        $lastStampAt = $row[1] ?? null;
    }
    if ($cooldownHours > 0 && is_string($lastStampAt) && strtotime($lastStampAt) > (time() - ($cooldownHours * 3600))) {
        $pdo->rollBack();
        mg_fail('This stamp card has a cooldown before the next stamp can be recorded.', 409);
    }
    $stampCount = $previousStamps + 1;
    $unlocked = $stampCount >= $requiredCount;
    $entry['stamp_label'] = $stampLabel;
    $entry['stamp_count'] = $stampCount;
    $entry['previous_stamp_count'] = $previousStamps;
    $entry['required_count'] = $requiredCount;
    $entry['stamps_remaining'] = max(0, $requiredCount - $stampCount);
    $entry['stamp_result'] = $unlocked ? 'unlocked' : 'recorded';
    $entry['stamp_cooldown_hours'] = $cooldownHours;
    $entry['stamp_card_verified'] = true;
    $entry['stamp_verification'] = $verification ? ['claim_code_id' => (string)$verification['claim_code_public_id'], 'location_id' => (string)$verification['location_public_id'], 'location_name' => (string)$verification['location_name']] : ['cashier_required' => false];
    $entry['stamped_at'] = gmdate('c');
    $input['entry'] = $entry;
    $input['campaign_type'] = 'stamp_card_reward';

    if (!$unlocked) {
        $record = mg_stamp_card_record_stamp($pdo, $campaign, $input, $entry, $embedAttribution, $stampCount, $requiredCount, $verification);
        $pdo->commit();
        $remaining = max(0, $requiredCount - $stampCount);
        mg_ok([
            'campaign_id' => (string)$campaign['public_id'],
            'campaign_type' => 'stamp_card_reward',
            'source' => 'stamp_card_reward',
            'stamp_result' => 'recorded',
            'stamp_count' => $stampCount,
            'required_count' => $requiredCount,
            'stamps_remaining' => $remaining,
            'reward_unlocked' => false,
            'contact_id' => (string)($record['contact']['public_id'] ?? ''),
            'merchant_crm' => $record['crm'] ?? null,
            'merchant_notification' => $record['merchant_notification'] ?? null,
            'stamp_verification' => $record['verification'] ?? null,
            'crm_creation_boundary' => 'deferred_until_first_value_event',
            'entry' => $entry,
            'embed_attribution' => $embedAttribution,
        ], $stampLabel . ' stamp verified. ' . $remaining . ' more to unlock your reward.', 200);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'public.stamp_card.record_failed', 'Unable to record stamp card visit.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
    mg_fail($error instanceof RuntimeException ? $error->getMessage() : 'Unable to record stamp card visit.', 500);
}

$GLOBALS['mg_stamp_card_entry'] = $entry;
function mg_public_campaign_engage_preprocess_input(PDO $pdo, array $input): array
{
    $entry = $input['entry'] ?? [];
    if (!is_array($entry)) $entry = [];
    $entry = array_merge($entry, is_array($GLOBALS['mg_stamp_card_entry'] ?? null) ? $GLOBALS['mg_stamp_card_entry'] : []);
    $entry['stamp_result'] = 'unlocked';
    $entry['stamp_card_verified'] = true;
    $input['entry'] = $entry;
    $input['campaign_type'] = 'stamp_card_reward';
    return $input;
}

require __DIR__ . '/engage.php';