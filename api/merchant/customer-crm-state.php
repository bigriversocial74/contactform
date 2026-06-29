<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';

function mg_ccs_json(mixed $json): array
{
    $data = is_array($json) ? $json : json_decode((string)$json, true);
    return is_array($data) ? $data : [];
}

function mg_ccs_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) { return $cache[$table] = false; }
}

function mg_ccs_clean_tags(mixed $tags): array
{
    if (!is_array($tags)) return [];
    $out = [];
    foreach ($tags as $tag) {
        $tag = trim(preg_replace('/\s+/u', ' ', (string)$tag) ?? '');
        if ($tag !== '' && mb_strlen($tag) <= 60) $out[] = $tag;
    }
    return array_values(array_unique(array_slice($out, 0, 12)));
}

function mg_ccs_param(array $input, string $key): string
{
    return trim((string)($input[$key] ?? $_GET[$key] ?? ''));
}

function mg_ccs_find_campaign_contact(PDO $pdo, int $merchantId, string $publicId): ?array
{
    if ($publicId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $publicId)) return null;
    try {
        $stmt = $pdo->prepare('SELECT * FROM campaign_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
        $stmt->execute([$publicId, $merchantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable) { return null; }
}

function mg_ccs_find_contact(PDO $pdo, int $merchantId, array $input): ?array
{
    $contactRef = strtolower(mg_ccs_param($input, 'contact_id') ?: mg_ccs_param($input, 'crm_contact_id'));
    $campaignContactRef = strtolower(mg_ccs_param($input, 'campaign_contact_id'));
    $email = strtolower(mg_ccs_param($input, 'email'));
    $customerName = trim(mg_ccs_param($input, 'customer_name'));
    $sessionId = trim(mg_ccs_param($input, 'session_id'));

    try {
        if ($contactRef !== '' && preg_match('/^[0-9a-f-]{36}$/i', $contactRef)) {
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
            $stmt->execute([$contactRef, $merchantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
        if ($campaignContactRef !== '') {
            $cc = mg_ccs_find_campaign_contact($pdo, $merchantId, $campaignContactRef);
            if ($cc) {
                $existing = mg_merchant_crm_contact($pdo, $merchantId, !empty($cc['user_id']) ? (int)$cc['user_id'] : null, mg_merchant_crm_email($cc['email'] ?? null));
                if ($existing) return $existing;
                mg_merchant_crm_record_event($pdo, [
                    'merchant_user_id' => $merchantId,
                    'campaign_id' => (int)($cc['campaign_id'] ?? 0),
                    'campaign_type' => (string)($cc['source'] ?? 'campaign_contact'),
                    'event_type' => 'store_canvas.crm_contact.linked',
                    'source_type' => 'store_canvas_customer_crm',
                    'source_public_id' => (string)($cc['public_id'] ?? ''),
                    'user_id' => !empty($cc['user_id']) ? (int)$cc['user_id'] : null,
                    'email' => (string)($cc['email'] ?? ''),
                    'phone' => (string)($cc['phone'] ?? ''),
                    'name' => (string)($cc['name'] ?? ''),
                    'metadata' => ['campaign_contact_id' => (string)($cc['public_id'] ?? ''), 'session_id' => $sessionId],
                ]);
                $existing = mg_merchant_crm_contact($pdo, $merchantId, !empty($cc['user_id']) ? (int)$cc['user_id'] : null, mg_merchant_crm_email($cc['email'] ?? null));
                if ($existing) return $existing;
            }
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND primary_email=? LIMIT 1');
            $stmt->execute([$merchantId, $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
        if ($customerName !== '') {
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND display_name=? ORDER BY updated_at DESC,id DESC LIMIT 1');
            $stmt->execute([$merchantId, $customerName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
            mg_merchant_crm_record_event($pdo, [
                'merchant_user_id' => $merchantId,
                'campaign_type' => 'store_canvas',
                'event_type' => 'store_canvas.crm_contact.created',
                'source_type' => 'store_canvas_customer_crm',
                'source_public_id' => $sessionId,
                'name' => $customerName,
                'metadata' => ['session_id' => $sessionId, 'created_from' => 'store_canvas_customer_crm'],
            ]);
            $stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE merchant_user_id=? AND display_name=? ORDER BY updated_at DESC,id DESC LIMIT 1');
            $stmt->execute([$merchantId, $customerName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        }
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.customer_crm_state.lookup_failed', 'Customer CRM state lookup failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    }
    return null;
}

function mg_ccs_notes(PDO $pdo, int $merchantId, int $crmContactId): array
{
    if ($crmContactId <= 0 || !mg_ccs_table_exists($pdo, 'merchant_crm_notes')) return [];
    try {
        $stmt = $pdo->prepare('SELECT public_id,note,created_at,updated_at FROM merchant_crm_notes WHERE merchant_user_id=? AND crm_contact_id=? ORDER BY updated_at DESC,id DESC LIMIT 5');
        $stmt->execute([$merchantId, $crmContactId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) { return []; }
}

function mg_ccs_response(PDO $pdo, int $merchantId, ?array $contact): array
{
    if (!$contact) return ['found' => false, 'tags' => [], 'note' => '', 'notes' => [], 'contact' => null];
    $notes = mg_ccs_notes($pdo, $merchantId, (int)($contact['id'] ?? 0));
    return [
        'found' => true,
        'tags' => mg_ccs_json($contact['tags_json'] ?? null),
        'note' => (string)($notes[0]['note'] ?? ''),
        'notes' => $notes,
        'contact' => [
            'id' => (string)($contact['public_id'] ?? ''),
            'name' => (string)($contact['display_name'] ?? ''),
            'email' => (string)($contact['primary_email'] ?? ''),
            'status' => (string)($contact['crm_status'] ?? 'active'),
            'stage' => (string)($contact['lifecycle_stage'] ?? 'lead'),
        ],
    ];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'POST' ? mg_require_permission('merchant.campaigns.manage') : mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    $contact = mg_ccs_find_contact($pdo, $merchantId, []);
    mg_ok(mg_ccs_response($pdo, $merchantId, $contact));
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);

$input = mg_input();
mg_require_csrf_for_write($input);
$contact = mg_ccs_find_contact($pdo, $merchantId, $input);
if (!$contact) mg_fail('Customer CRM contact could not be resolved.', 404);

$tags = array_key_exists('tags', $input) ? mg_ccs_clean_tags($input['tags']) : null;
$note = trim((string)($input['note'] ?? ''));
$actionStatus = is_array($input['action_status'] ?? null) ? $input['action_status'] : [];
$sessionId = trim((string)($input['session_id'] ?? ''));
$crmContactId = (int)($contact['id'] ?? 0);

if (is_array($tags)) {
    $pdo->prepare('UPDATE merchant_crm_contacts SET tags_json=?, updated_at=NOW() WHERE id=? AND merchant_user_id=?')
        ->execute([json_encode($tags, JSON_UNESCAPED_SLASHES), $crmContactId, $merchantId]);
}

if ($note !== '') {
    if (mb_strlen($note) > 4000) mg_fail('Note is too long.', 422);
    if (mg_ccs_table_exists($pdo, 'merchant_crm_notes')) {
        $pdo->prepare("INSERT INTO merchant_crm_notes (public_id,merchant_user_id,crm_contact_id,author_user_id,note,visibility,created_at,updated_at) VALUES (?,?,?,?,?,'merchant_internal',NOW(),NOW())")
            ->execute([mg_merchant_crm_uuid(), $merchantId, $crmContactId, $merchantId, $note]);
    }
}

if ($note !== '' || is_array($tags) || $actionStatus) {
    mg_merchant_crm_record_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_type' => 'store_canvas',
        'event_type' => 'store_canvas.customer_crm.saved',
        'source_type' => 'store_canvas_customer_crm',
        'source_public_id' => $sessionId ?: (string)($contact['public_id'] ?? ''),
        'user_id' => !empty($contact['user_id']) ? (int)$contact['user_id'] : null,
        'email' => (string)($contact['primary_email'] ?? ''),
        'phone' => (string)($contact['primary_phone'] ?? ''),
        'name' => (string)($contact['display_name'] ?? ''),
        'metadata' => ['tags' => $tags, 'note_saved' => $note !== '', 'action_status' => $actionStatus, 'session_id' => $sessionId],
    ]);
}

$stmt = $pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE id=? AND merchant_user_id=? LIMIT 1');
$stmt->execute([$crmContactId, $merchantId]);
$updated = $stmt->fetch(PDO::FETCH_ASSOC) ?: $contact;
mg_ok(mg_ccs_response($pdo, $merchantId, $updated), 'Customer CRM state saved.');
