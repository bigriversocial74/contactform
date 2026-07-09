<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';
require_once dirname(__DIR__, 3) . '/includes/merchant-crm.php';
require_once __DIR__ . '/_merchant_notifications.php';
require_once __DIR__ . '/_embed_attribution.php';

function mg_instant_win_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_instant_win_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_instant_win_find_user(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$email]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_instant_win_odds(array $campaign): int
{
    $rules = mg_instant_win_json($campaign['rules_json'] ?? null);
    $odds = (int)($rules['odds_percent'] ?? $rules['instant_win_odds_percent'] ?? 100);
    return max(0, min(100, $odds));
}

function mg_instant_win_no_win_message(array $campaign): string
{
    $rules = mg_instant_win_json($campaign['rules_json'] ?? null);
    $message = trim((string)($rules['no_win_message'] ?? $rules['instant_win_no_win_message'] ?? ''));
    return $message !== '' ? mb_substr($message, 0, 240) : 'Not a winner this time — thanks for playing.';
}

function mg_instant_win_record_no_win(PDO $pdo, array $campaign, array $input, array $entry, array $embedAttribution, string $message): void
{
    $merchantId = (int)$campaign['merchant_user_id'];
    $campaignId = (int)$campaign['id'];
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $name = trim((string)($input['name'] ?? $input['full_name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $source = 'instant_win_reward';
    $userId = mg_instant_win_find_user($pdo, $email);
    $metadata = mg_public_campaign_metadata_with_embed([
        'campaign_type' => 'instant_win_reward',
        'campaign_public_id' => (string)$campaign['public_id'],
        'crm_source' => $source,
        'entry' => $entry,
        'instant_win_result' => 'not_won',
        'ip' => mg_client_ip(),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ], $embedAttribution);

    $contactPublicId = mg_instant_win_uuid();
    $stmt = $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,phone,name,source,opt_in_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), phone=VALUES(phone), name=VALUES(name), source=VALUES(source), metadata_json=VALUES(metadata_json), updated_at=NOW()");
    $stmt->execute([$contactPublicId, $merchantId, $campaignId, $userId, $email, $phone !== '' ? $phone : null, $name !== '' ? $name : null, $source, 'opted_in', json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    $lookup = $pdo->prepare('SELECT id,public_id FROM campaign_contacts WHERE campaign_id=? AND email=? LIMIT 1');
    $lookup->execute([$campaignId, $email]);
    $contact = $lookup->fetch(PDO::FETCH_ASSOC) ?: [];
    $contactId = (int)($contact['id'] ?? 0);

    $crm = mg_merchant_crm_record_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_id' => $campaignId,
        'campaign_type' => 'instant_win_reward',
        'event_type' => 'instant_win.not_won',
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
        mg_instant_win_uuid(),
        $merchantId,
        $campaignId,
        null,
        $contactId ?: null,
        'instant_win.not_won',
        json_encode(['campaign_type' => 'instant_win_reward', 'source' => $source, 'email' => $email, 'entry' => $entry, 'instant_win_result' => 'not_won', 'message' => $message, 'embed_attribution' => $embedAttribution, 'merchant_crm' => $crm, 'merchant_notification' => $merchantNotification], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
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
    mg_fail('Invalid instant win play.', 422);
}
if (empty($entry['reveal_confirmed']) && empty($input['reveal_confirmed'])) {
    mg_fail('Reveal the instant win card before submitting.', 422);
}

$stmt = $pdo->prepare("SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title FROM campaigns c INNER JOIN reward_templates rt ON rt.id=c.reward_template_id WHERE c.status='active' AND rt.status='active' AND (c.public_id=? OR c.public_slug=?) LIMIT 1");
$stmt->execute([$campaignRef, $campaignRef]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign || (string)$campaign['campaign_type'] !== 'instant_win_reward') {
    mg_fail('Instant win campaign is not available.', 404);
}
$now = time();
if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) mg_fail('Instant win campaign has not started yet.', 409);
if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) mg_fail('Instant win campaign has ended.', 409);
if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) mg_fail('Instant win reward limit has been reached.', 409);

$odds = mg_instant_win_odds($campaign);
$roll = random_int(1, 100);
$won = $odds >= 100 || ($odds > 0 && $roll <= $odds);
$entry['instant_win_mode'] = 'scratch_reveal';
$entry['instant_win_odds_percent'] = $odds;
$entry['instant_win_roll'] = $roll;
$entry['instant_win_result'] = $won ? 'won' : 'not_won';
$entry['played_at'] = gmdate('c');
$input['entry'] = $entry;
$input['campaign_type'] = 'instant_win_reward';

if (!$won) {
    $message = mg_instant_win_no_win_message($campaign);
    try {
        $pdo->beginTransaction();
        mg_instant_win_record_no_win($pdo, $campaign, $input, $entry, $embedAttribution, $message);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'public.instant_win.not_won_failed', 'Unable to record instant win no-win result.', ['exception_class' => $error::class, 'message' => $error->getMessage()]);
        mg_fail('Unable to record instant win play.', 500);
    }
    mg_ok([
        'campaign_id' => (string)$campaign['public_id'],
        'campaign_type' => 'instant_win_reward',
        'source' => 'instant_win_reward',
        'instant_win_result' => 'not_won',
        'won' => false,
        'message' => $message,
        'entry' => $entry,
        'embed_attribution' => $embedAttribution,
    ], $message, 200);
}

$GLOBALS['mg_instant_win_entry'] = $entry;
function mg_public_campaign_engage_preprocess_input(PDO $pdo, array $input): array
{
    $entry = $input['entry'] ?? [];
    if (!is_array($entry)) $entry = [];
    $entry = array_merge($entry, is_array($GLOBALS['mg_instant_win_entry'] ?? null) ? $GLOBALS['mg_instant_win_entry'] : []);
    $entry['instant_win_result'] = 'won';
    $entry['instant_win_verified'] = true;
    $input['entry'] = $entry;
    $input['campaign_type'] = 'instant_win_reward';
    return $input;
}

require __DIR__ . '/engage.php';
