<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_sha_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) return $exists;
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute(['merchant_store_health_actions']);
        $exists = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        $exists = false;
    }
    return $exists;
}

function mg_sha_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_sha_clean_key(mixed $value, int $max = 191): string
{
    $value = trim((string)$value);
    $value = preg_replace('/[^a-zA-Z0-9:_\-.]+/', '_', $value) ?? '';
    return substr($value, 0, $max);
}

function mg_sha_status(mixed $value): string
{
    $allowed = ['suggested','started','completed','snoozed','dismissed'];
    $value = strtolower(trim((string)$value));
    return in_array($value, $allowed, true) ? $value : 'suggested';
}

function mg_sha_priority(mixed $value): string
{
    $allowed = ['high','medium','warning','safe','low'];
    $value = strtolower(trim((string)$value));
    return in_array($value, $allowed, true) ? $value : 'low';
}

function mg_sha_row(array $row): array
{
    $metadata = [];
    if (!empty($row['metadata_json'])) {
        $decoded = json_decode((string)$row['metadata_json'], true);
        if (is_array($decoded)) $metadata = $decoded;
    }
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'key' => (string)($row['action_key'] ?? ''),
        'type' => (string)($row['action_type'] ?? ''),
        'condition' => (string)($row['condition_key'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'copy' => (string)($row['description'] ?? ''),
        'priority' => (string)($row['priority'] ?? 'low'),
        'status' => (string)($row['status'] ?? 'suggested'),
        'count' => (int)($row['condition_count'] ?? 0),
        'metadata' => $metadata,
        'startedAt' => $row['started_at'] ?? null,
        'completedAt' => $row['completed_at'] ?? null,
        'snoozedUntil' => $row['snoozed_until'] ?? null,
        'dismissedAt' => $row['dismissed_at'] ?? null,
        'createdAt' => $row['created_at'] ?? null,
        'updatedAt' => $row['updated_at'] ?? null,
    ];
}

function mg_sha_list(PDO $pdo, int $merchantId): array
{
    if (!mg_sha_table_exists($pdo)) return [];
    $stmt = $pdo->prepare('SELECT * FROM merchant_store_health_actions WHERE merchant_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 100');
    $stmt->execute([$merchantId]);
    return array_map('mg_sha_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET' ? mg_require_permission('merchant.campaigns.view') : mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();

if ($method === 'GET') {
    mg_ok(['actions' => mg_sha_list($pdo, $merchantId), 'persistence' => mg_sha_table_exists($pdo) ? 'database' : 'missing_table']);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
if (!mg_sha_table_exists($pdo)) mg_fail('Store Health action table is not installed.', 503, ['table' => 'merchant_store_health_actions']);

$action = is_array($input['action'] ?? null) ? $input['action'] : $input;
$key = mg_sha_clean_key($action['key'] ?? $action['action_key'] ?? '');
if ($key === '') mg_fail('Action key is required.', 422);
$type = mg_sha_clean_key($action['type'] ?? $action['action_type'] ?? 'store_health_action', 80) ?: 'store_health_action';
$condition = mg_sha_clean_key($action['condition'] ?? $action['condition_key'] ?? $type, 120) ?: $type;
$title = trim((string)($action['title'] ?? 'Store Health action'));
$title = substr($title !== '' ? $title : 'Store Health action', 0, 190);
$copy = trim((string)($action['copy'] ?? $action['description'] ?? ''));
$status = mg_sha_status($action['status'] ?? 'suggested');
$priority = mg_sha_priority($action['priority'] ?? 'low');
$count = max(0, (int)($action['count'] ?? $action['condition_count'] ?? 0));
$metadata = is_array($action['metadata'] ?? null) ? $action['metadata'] : [];

$startedAt = $status === 'started' ? date('Y-m-d H:i:s') : null;
$completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
$dismissedAt = $status === 'dismissed' ? date('Y-m-d H:i:s') : null;
$snoozedUntil = null;
if ($status === 'snoozed') {
    $snoozedUntil = date('Y-m-d H:i:s', time() + 86400);
}

$stmt = $pdo->prepare(
    "INSERT INTO merchant_store_health_actions
      (public_id,merchant_user_id,action_key,action_type,condition_key,title,description,priority,status,condition_count,metadata_json,started_at,completed_at,snoozed_until,dismissed_at,created_at,updated_at)
     VALUES
      (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
     ON DUPLICATE KEY UPDATE
      action_type=VALUES(action_type), condition_key=VALUES(condition_key), title=VALUES(title), description=VALUES(description),
      priority=VALUES(priority), status=VALUES(status), condition_count=VALUES(condition_count), metadata_json=VALUES(metadata_json),
      started_at=COALESCE(VALUES(started_at), started_at), completed_at=COALESCE(VALUES(completed_at), completed_at),
      snoozed_until=COALESCE(VALUES(snoozed_until), snoozed_until), dismissed_at=COALESCE(VALUES(dismissed_at), dismissed_at), updated_at=NOW()"
);
$stmt->execute([
    mg_sha_uuid(), $merchantId, $key, $type, $condition, $title, $copy, $priority, $status, $count,
    json_encode($metadata, JSON_UNESCAPED_SLASHES), $startedAt, $completedAt, $snoozedUntil, $dismissedAt,
]);

$rowStmt = $pdo->prepare('SELECT * FROM merchant_store_health_actions WHERE merchant_user_id=? AND action_key=? LIMIT 1');
$rowStmt->execute([$merchantId, $key]);
$row = $rowStmt->fetch(PDO::FETCH_ASSOC);
mg_ok(['action' => $row ? mg_sha_row($row) : null, 'actions' => mg_sha_list($pdo, $merchantId)], 'Store Health action state saved.');
