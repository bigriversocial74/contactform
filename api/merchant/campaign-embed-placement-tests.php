<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_embed_test_token(mixed $value, int $max = 190): string
{
    $value = trim((string)$value);
    $value = preg_replace('/[^a-zA-Z0-9_:\-.\/ ]+/', '_', $value) ?: '';
    return substr(trim($value), 0, $max);
}

function mg_embed_test_status(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['planned', 'running', 'completed', 'paused'], true) ? $value : 'running';
}

function mg_embed_test_table_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute(['campaign_embed_placement_tests']);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $error) {
        return false;
    }
}

function mg_embed_test_json(mixed $value): ?string
{
    if (!is_array($value) || !$value) return null;
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : null;
}

function mg_embed_test_campaign(PDO $pdo, int $merchantUserId, string $campaignRef): ?array
{
    $campaignRef = trim($campaignRef);
    if ($campaignRef === '') return null;
    $numericId = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
    $stmt = $pdo->prepare('SELECT id, public_id, public_slug, title, campaign_type FROM campaigns WHERE merchant_user_id = ? AND ((? > 0 AND id = ?) OR public_id = ? OR public_slug = ?) LIMIT 1');
    $stmt->execute([$merchantUserId, $numericId, $numericId, $campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    return $campaign ?: null;
}

function mg_embed_test_row(array $row): array
{
    $campaignRef = (string)($row['campaign_slug'] ?: $row['campaign_public_id'] ?: '');
    return [
        'id' => (string)$row['public_id'],
        'status' => (string)$row['status'],
        'campaign' => [
            'id' => (string)($row['campaign_public_id'] ?? ''),
            'slug' => $row['campaign_slug'] ?? null,
            'title' => (string)($row['campaign_title'] ?? 'Campaign'),
            'url' => $campaignRef !== '' ? '/merchant-campaigns.php?campaign=' . rawurlencode($campaignRef) : '/merchant-campaigns.php',
        ],
        'origin_host' => (string)($row['origin_host'] ?? ''),
        'page_url' => (string)($row['page_url'] ?? ''),
        'page_path' => (string)($row['page_path'] ?? ''),
        'source' => (string)($row['source'] ?? ''),
        'embed_mode' => (string)($row['embed_mode'] ?? ''),
        'placement_label' => (string)($row['placement_label'] ?? ''),
        'next_test' => (string)($row['next_test'] ?? ''),
        'started_at' => $row['started_at'] ?? null,
        'ended_at' => $row['ended_at'] ?? null,
        'paused_at' => $row['paused_at'] ?? null,
        'compared_at' => $row['compared_at'] ?? null,
        'notes' => (string)($row['notes'] ?? ''),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_embed_test_fetch(PDO $pdo, int $merchantUserId, string $campaignRef = ''): array
{
    $where = ['merchant_user_id = ?'];
    $params = [$merchantUserId];
    if ($campaignRef !== '') {
        $where[] = '(campaign_public_id = ? OR campaign_slug = ?)';
        $params[] = $campaignRef;
        $params[] = $campaignRef;
    }
    $stmt = $pdo->prepare('SELECT * FROM campaign_embed_placement_tests WHERE ' . implode(' AND ', $where) . ' ORDER BY FIELD(status,\'running\',\'planned\',\'paused\',\'completed\'), COALESCE(started_at, created_at) DESC, id DESC LIMIT 120');
    $stmt->execute($params);
    $rows = array_map('mg_embed_test_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    $counts = ['planned' => 0, 'running' => 0, 'completed' => 0, 'paused' => 0];
    foreach ($rows as $row) if (isset($counts[$row['status']])) $counts[$row['status']]++;
    return ['counts' => $counts, 'tests' => $rows];
}

function mg_embed_test_require_owned(PDO $pdo, int $merchantUserId, string $publicId): array
{
    if ($publicId === '') mg_fail('Placement test id is required.', 422);
    $stmt = $pdo->prepare('SELECT * FROM campaign_embed_placement_tests WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
    $stmt->execute([$publicId, $merchantUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Placement test not found.', 404);
    return $row;
}

$user = mg_require_api_user();
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];

if (!mg_embed_test_table_ready($pdo)) {
    mg_fail('Campaign embed placement test tracking is not installed. Import database/campaign_embed_placement_tests_v4_7.sql.', 503, ['sql_required' => 'database/campaign_embed_placement_tests_v4_7.sql']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $campaignRef = mg_embed_test_token($_GET['campaign'] ?? '', 190);
    mg_ok(array_merge(['schema_ready' => true, 'sql_required' => null, 'campaign' => $campaignRef], mg_embed_test_fetch($pdo, $merchantUserId, $campaignRef)), 'Campaign embed placement tests loaded.');
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'start')));

if ($action === 'start') {
    $campaignRef = mg_embed_test_token($input['campaign_ref'] ?? $input['campaign'] ?? '', 190);
    $campaign = mg_embed_test_campaign($pdo, $merchantUserId, $campaignRef);
    if (!$campaign) mg_fail('Campaign is required to start a placement test.', 422);
    $publicId = mg_merchant_uuid();
    $originHost = mg_embed_test_token($input['origin_host'] ?? '', 190);
    $pageUrl = trim((string)($input['page_url'] ?? ''));
    $pagePath = mg_embed_test_token($input['page_path'] ?? '', 255);
    $source = mg_embed_test_token($input['source'] ?? '', 80);
    $embedMode = mg_embed_test_token($input['embed_mode'] ?? '', 80);
    $placementLabel = mg_embed_test_token($input['placement_label'] ?? '', 255);
    $nextTest = mg_embed_test_token($input['next_test'] ?? '', 255);
    $notes = trim((string)($input['notes'] ?? ''));
    $metadata = mg_embed_test_json([
        'created_from' => 'campaign_embed_v4_7_action_center',
        'ready_rate' => $input['ready_rate'] ?? null,
        'average_quality_score' => $input['average_quality_score'] ?? null,
        'recommended_action' => $input['recommended_action'] ?? null,
    ]);
    $stmt = $pdo->prepare('INSERT INTO campaign_embed_placement_tests (public_id, merchant_user_id, campaign_id, campaign_public_id, campaign_slug, campaign_title, origin_host, page_url, page_path, source, embed_mode, placement_label, next_test, status, started_at, notes, metadata_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,\'running\',NOW(),?,?,NOW(),NOW())');
    $stmt->execute([$publicId, $merchantUserId, (int)$campaign['id'], (string)$campaign['public_id'], (string)($campaign['public_slug'] ?? ''), (string)$campaign['title'], $originHost ?: null, $pageUrl ?: null, $pagePath ?: null, $source ?: null, $embedMode ?: null, $placementLabel ?: null, $nextTest ?: null, $notes ?: null, $metadata]);
    mg_ok(array_merge(['created' => true, 'test' => mg_embed_test_row(['public_id' => $publicId, 'status' => 'running', 'campaign_public_id' => (string)$campaign['public_id'], 'campaign_slug' => (string)($campaign['public_slug'] ?? ''), 'campaign_title' => (string)$campaign['title'], 'origin_host' => $originHost, 'page_url' => $pageUrl, 'page_path' => $pagePath, 'source' => $source, 'embed_mode' => $embedMode, 'placement_label' => $placementLabel, 'next_test' => $nextTest, 'started_at' => date('Y-m-d H:i:s'), 'ended_at' => null, 'paused_at' => null, 'compared_at' => null, 'notes' => $notes, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')])], mg_embed_test_fetch($pdo, $merchantUserId)), 'Placement test started.');
}

$publicId = mg_embed_test_token($input['test_id'] ?? $input['id'] ?? '', 64);
$row = mg_embed_test_require_owned($pdo, $merchantUserId, $publicId);
if ($action === 'pause') {
    $pdo->prepare("UPDATE campaign_embed_placement_tests SET status='paused', paused_at=NOW(), updated_at=NOW() WHERE public_id=? AND merchant_user_id=?")->execute([$publicId, $merchantUserId]);
    mg_ok(mg_embed_test_fetch($pdo, $merchantUserId), 'Placement test paused.');
}
if ($action === 'resume') {
    $pdo->prepare("UPDATE campaign_embed_placement_tests SET status='running', paused_at=NULL, updated_at=NOW() WHERE public_id=? AND merchant_user_id=?")->execute([$publicId, $merchantUserId]);
    mg_ok(mg_embed_test_fetch($pdo, $merchantUserId), 'Placement test resumed.');
}
if ($action === 'complete' || $action === 'end') {
    $pdo->prepare("UPDATE campaign_embed_placement_tests SET status='completed', ended_at=COALESCE(ended_at,NOW()), updated_at=NOW() WHERE public_id=? AND merchant_user_id=?")->execute([$publicId, $merchantUserId]);
    mg_ok(mg_embed_test_fetch($pdo, $merchantUserId), 'Placement test completed.');
}
if ($action === 'compare') {
    $pdo->prepare('UPDATE campaign_embed_placement_tests SET compared_at=NOW(), updated_at=NOW() WHERE public_id=? AND merchant_user_id=?')->execute([$publicId, $merchantUserId]);
    $campaignRef = (string)($row['campaign_slug'] ?: $row['campaign_public_id'] ?: '');
    $params = ['days' => '30'];
    if ($campaignRef !== '') $params['campaign'] = $campaignRef;
    if (!empty($row['origin_host'])) $params['origin_host'] = (string)$row['origin_host'];
    if (!empty($row['source'])) $params['source'] = (string)$row['source'];
    mg_ok(array_merge(['compare_url' => '/merchant-campaign-embed-leads.php?' . http_build_query($params)], mg_embed_test_fetch($pdo, $merchantUserId)), 'Placement test comparison ready.');
}

mg_fail('Unsupported placement test action.', 422);
