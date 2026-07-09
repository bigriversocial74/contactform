<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';
require_once dirname(__DIR__, 3) . '/includes/merchant-crm.php';
require_once __DIR__ . '/_merchant_notifications.php';
require_once __DIR__ . '/_embed_attribution.php';

function mg_rsvp_event_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_rsvp_event_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_rsvp_event_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_rsvp_event_rules(array $campaign): array
{
    return mg_rsvp_event_json($campaign['rules_json'] ?? null);
}

function mg_rsvp_event_name(array $campaign): string
{
    $rules = mg_rsvp_event_rules($campaign);
    $name = trim((string)($rules['event_name'] ?? $rules['rsvp_event_name'] ?? $campaign['title'] ?? 'Merchant event'));
    return $name !== '' ? mb_substr($name, 0, 160) : 'Merchant event';
}

function mg_rsvp_event_date(array $campaign): ?string
{
    $rules = mg_rsvp_event_rules($campaign);
    $date = trim((string)($rules['event_date'] ?? $rules['rsvp_event_date'] ?? ''));
    return $date !== '' ? mb_substr($date, 0, 80) : null;
}

function mg_rsvp_event_attendance_code(array $campaign): string
{
    $rules = mg_rsvp_event_rules($campaign);
    return strtoupper(trim((string)($rules['attendance_code'] ?? $rules['rsvp_attendance_code'] ?? '')));
}

function mg_rsvp_event_record_rsvp(PDO $pdo, array $campaign, array $input, array $entry, array $embedAttribution): array
{
    $merchantId = (int)$campaign['merchant_user_id'];
    $campaignId = (int)$campaign['id'];
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $name = trim((string)($input['name'] ?? $input['full_name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $source = 'rsvp_event_reward';
    $userId = mg_rsvp_event_find_user($pdo, $email);
    $metadata = mg_public_campaign_metadata_with_embed([
        'campaign_type' => 'rsvp_event_reward',
        'campaign_public_id' => (string)$campaign['public_id'],
        'crm_source' => $source,
        'entry' => $entry,
        'rsvp_status' => 'rsvped',
        'attendance_confirmed' => false,
        'ip' => mg_client_ip(),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ], $embedAttribution);

    $contactPublicId = mg_rsvp_event_uuid();
    $stmt = $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), phone=VALUES(phone), name=VALUES(name), source=VALUES(source), metadata_json=VALUES(metadata_json), updated_at=NOW()");
    $stmt->execute([$contactPublicId, $merchantId, $campaignId, $userId, $email, $phone !== '' ? $phone : null, $name !== '' ? $name : null, $source, 'opted_in', json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $lookup = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1');
    $lookup->execute([$campaignId, $email]);
    $contact = $lookup->fetch(PDO::FETCH_ASSOC) ?: [];
    $contactId = (int)($contact['id'] ?? 0);

    $crm = mg_merchant_crm_record_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_id' => $campaignId,
        'campaign_type' => 'rsvp_event_reward',
        'event_type' => 'rsvp_event.rsvped',
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
        mg_rsvp_event_uuid(),
        $merchantId,
        $campaignId,
        null,
        $contactId ?: null,
        'rsvp_event.rsvped',
        json_encode(['campaign_type' => 'rsvp_event_reward', 'source' => $source, 'email' => $email, 'entry' => $entry, 'rsvp_status' => 'rsvped', 'attendance_confirmed' => false, 'embed_attribution' => $embedAttribution, 'merchant_crm' => $crm, 'merchant_notification' => $merchantNotification], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    return ['contact' => $contact, 'crm' => $crm, 'merchant_notification' => $merchantNotification];
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
    mg_fail('Invalid RSVP event submission.', 422);
}

$stmt = $pdo->prepare("SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title FROM campaigns c INNER JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.status='active' AND rt.status='active' AND (c.public_id=? OR c.public_slug=?) LIMIT 1");
$stmt->execute([$campaignRef, $campaignRef]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign || (string)$campaign['campaign_type'] !== 'rsvp_event_reward') {
    mg_fail('RSVP event campaign is not available.', 404);
}
$now = time();
if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) mg_fail('RSVP event campaign has not started yet.', 409);
if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) mg_fail('RSVP event campaign has ended.', 409);
if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) mg_fail('Attendance reward limit has been reached.', 409);

$eventName = mg_rsvp_event_name($campaign);
$eventDate = mg_rsvp_event_date($campaign);
$expectedCode = mg_rsvp_event_attendance_code($campaign);
$submittedCode = strtoupper(trim((string)($entry['attendance_code'] ?? $input['attendance_code'] ?? '')));
$attendanceRequested = $submittedCode !== '';

$entry['event_name'] = $eventName;
if ($eventDate !== null) $entry['event_date'] = $eventDate;
$entry['rsvp_status'] = $attendanceRequested ? 'attendance_requested' : 'rsvped';
$entry['attendance_confirmed'] = false;
$entry['submitted_at'] = gmdate('c');
$input['entry'] = $entry;
$input['campaign_type'] = 'rsvp_event_reward';

if (!$attendanceRequested) {
    try {
        $pdo->beginTransaction();
        $record = mg_rsvp_event_record_rsvp($pdo, $campaign, $input, $entry, $embedAttribution);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'public.rsvp_event.record_failed', 'Unable to record RSVP event submission.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
        mg_fail('Unable to record RSVP.', 500);
    }
    mg_ok([
        'campaign_id' => (string)$campaign['public_id'],
        'campaign_type' => 'rsvp_event_reward',
        'source' => 'rsvp_event_reward',
        'rsvp_status' => 'rsvped',
        'attendance_confirmed' => false,
        'reward_unlocked' => false,
        'event_name' => $eventName,
        'event_date' => $eventDate,
        'contact_id' => (string)($record['contact']['public_id'] ?? ''),
        'merchant_crm' => $record['crm'] ?? null,
        'merchant_notification' => $record['merchant_notification'] ?? null,
        'entry' => $entry,
        'embed_attribution' => $embedAttribution,
    ], 'RSVP recorded. Attendance reward eligibility will be checked at the event.', 200);
}

if ($expectedCode === '' || !hash_equals($expectedCode, $submittedCode)) {
    mg_fail('Attendance code is invalid or not configured for this event reward.', 422);
}

$entry['rsvp_status'] = 'attended';
$entry['attendance_confirmed'] = true;
$entry['attendance_confirmed_at'] = gmdate('c');
$entry['attendance_code_last4'] = substr($submittedCode, -4);
$input['entry'] = $entry;
$GLOBALS['mg_rsvp_event_entry'] = $entry;
function mg_public_campaign_engage_preprocess_input(PDO $pdo, array $input): array
{
    $entry = $input['entry'] ?? [];
    if (!is_array($entry)) $entry = [];
    $entry = array_merge($entry, is_array($GLOBALS['mg_rsvp_event_entry'] ?? null) ? $GLOBALS['mg_rsvp_event_entry'] : []);
    $entry['rsvp_status'] = 'attended';
    $entry['attendance_confirmed'] = true;
    $entry['rsvp_event_verified'] = true;
    $input['entry'] = $entry;
    $input['campaign_type'] = 'rsvp_event_reward';
    return $input;
}

require __DIR__ . '/engage.php';
