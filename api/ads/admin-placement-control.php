<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

$user = mg_require_api_user();
$pdo = mg_db();
mg_ads_require_admin_user($user);

function mg_ads_admin_public_assignment(PDO $pdo, array $row): array
{
    $profile = mg_ads_merchant_profile($pdo, (int)($row['merchant_id'] ?? 0));
    return [
        'assignment_id' => (int)($row['assignment_id'] ?? 0),
        'campaign_id' => (string)($row['campaign_public_id'] ?? ''),
        'campaign_title' => (string)($row['campaign_title'] ?? 'Sponsored Campaign'),
        'campaign_status' => (string)($row['campaign_status'] ?? ''),
        'objective' => (string)($row['objective'] ?? ''),
        'merchant_id' => (int)($row['merchant_id'] ?? 0),
        'merchant_name' => (string)($profile['merchant_name'] ?? 'Microgifter Merchant'),
        'creative_headline' => (string)($row['headline'] ?? ''),
        'placement_key' => (string)($row['placement_key'] ?? ''),
        'priority' => (int)($row['priority'] ?? 100),
        'status' => (string)($row['assignment_status'] ?? 'active'),
    ];
}

function mg_ads_admin_load_payload(PDO $pdo): array
{
    mg_ads_require_schema($pdo);
    mg_ads_seed_placements($pdo);

    $placementsStmt = $pdo->query('SELECT placement_key, placement_name, surface, description, is_active, max_ads, updated_at FROM ad_placements ORDER BY FIELD(placement_key,\'feed_sponsored_card\',\'sidebar_sponsored_card\',\'world_canvas_sponsored_pin\',\'target_zone_sponsored_drop\'), placement_key ASC');
    $placements = $placementsStmt ? $placementsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $campaignStmt = $pdo->query("SELECT c.public_id, c.title, c.status, c.objective, c.merchant_id, cr.headline FROM ad_campaigns c LEFT JOIN ad_creatives cr ON cr.ad_campaign_id=c.id WHERE c.status IN ('approved','active','paused','pending_review') ORDER BY FIELD(c.status,'active','approved','pending_review','paused'), c.updated_at DESC LIMIT 150");
    $campaigns = [];
    foreach (($campaignStmt ? $campaignStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $profile = mg_ads_merchant_profile($pdo, (int)($row['merchant_id'] ?? 0));
        $campaigns[] = [
            'id' => (string)$row['public_id'],
            'title' => (string)$row['title'],
            'status' => (string)$row['status'],
            'objective' => (string)$row['objective'],
            'merchant_id' => (int)$row['merchant_id'],
            'merchant_name' => (string)($profile['merchant_name'] ?? 'Microgifter Merchant'),
            'headline' => (string)($row['headline'] ?? ''),
        ];
    }

    $assignmentSql = "SELECT cp.id assignment_id, cp.placement_key, cp.priority, cp.status assignment_status, c.public_id campaign_public_id, c.title campaign_title, c.status campaign_status, c.objective, c.merchant_id, cr.headline FROM ad_campaign_placements cp INNER JOIN ad_campaigns c ON c.id=cp.ad_campaign_id LEFT JOIN ad_creatives cr ON cr.ad_campaign_id=c.id WHERE c.status<>'archived' AND cp.status<>'archived' ORDER BY cp.placement_key ASC, cp.priority ASC, cp.updated_at DESC";
    $assignmentStmt = $pdo->query($assignmentSql);
    $assignments = [];
    foreach (($assignmentStmt ? $assignmentStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $assignments[] = mg_ads_admin_public_assignment($pdo, $row);
    }

    $metricsStmt = $pdo->query("SELECT placement_key, event_type, COUNT(*) total FROM ad_events WHERE placement_key IS NOT NULL AND placement_key<>'' GROUP BY placement_key,event_type");
    $metrics = [];
    foreach (($metricsStmt ? $metricsStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $key = (string)$row['placement_key'];
        if (!isset($metrics[$key])) $metrics[$key] = [];
        $metrics[$key][(string)$row['event_type']] = (int)$row['total'];
    }

    return [
        'schema_ready' => true,
        'placements' => $placements,
        'campaigns' => $campaigns,
        'assignments' => $assignments,
        'metrics' => $metrics,
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $schema = mg_ads_schema_status($pdo);
        if (!$schema['ready']) {
            mg_ok(['schema_ready' => false, 'tables' => $schema['tables'], 'placements' => [], 'campaigns' => [], 'assignments' => [], 'metrics' => []], 'Campaign Ads Manager migration is required.');
        }
        mg_ok(mg_ads_admin_load_payload($pdo), 'Placement controls loaded.');
    }

    mg_require_method('POST');
    $input = mg_input();
    mg_require_csrf_for_write($input);
    if (function_exists('mg_rate_limit')) {
        mg_rate_limit('ads.admin_placement_control', 'user:' . (int)$user['id'], 120, 60);
    }
    mg_ads_require_schema($pdo);
    mg_ads_seed_placements($pdo);

    $action = mg_ads_enum($input['action'] ?? '', ['update_placement','assign_campaign','update_assignment','archive_assignment'], '');
    if ($action === '') mg_fail('Unsupported placement control action.', 422);

    if ($action === 'update_placement') {
        $placementKey = mg_ads_enum($input['placement_key'] ?? '', mg_ads_allowed_placements(), '');
        if ($placementKey === '') mg_fail('Placement key is required.', 422);
        $isActive = !empty($input['is_active']) ? 1 : 0;
        $maxAds = max(1, min(20, (int)($input['max_ads'] ?? 1)));
        $stmt = $pdo->prepare('UPDATE ad_placements SET is_active=?, max_ads=?, updated_at=NOW() WHERE placement_key=?');
        $stmt->execute([$isActive, $maxAds, $placementKey]);
        mg_ok(mg_ads_admin_load_payload($pdo), 'Placement settings updated.');
    }

    if ($action === 'assign_campaign') {
        $placementKey = mg_ads_enum($input['placement_key'] ?? '', mg_ads_allowed_placements(), '');
        $campaignPublicId = mg_ads_text($input['campaign_id'] ?? '', 80, '');
        $priority = max(1, min(999, (int)($input['priority'] ?? 100)));
        if ($placementKey === '' || $campaignPublicId === '') mg_fail('Campaign and placement are required.', 422);
        $campaignStmt = $pdo->prepare("SELECT id FROM ad_campaigns WHERE public_id=? AND status IN ('approved','active','pending_review','paused') LIMIT 1");
        $campaignStmt->execute([$campaignPublicId]);
        $campaignId = (int)($campaignStmt->fetchColumn() ?: 0);
        if ($campaignId <= 0) mg_fail('Campaign is not available for placement assignment.', 422);
        $stmt = $pdo->prepare("INSERT INTO ad_campaign_placements (ad_campaign_id, placement_key, priority, status, created_at, updated_at) VALUES (?,?,?,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE priority=VALUES(priority), status='active', updated_at=NOW()");
        $stmt->execute([$campaignId, $placementKey, $priority]);
        mg_ok(mg_ads_admin_load_payload($pdo), 'Campaign assigned to placement.');
    }

    if ($action === 'update_assignment') {
        $assignmentId = max(0, (int)($input['assignment_id'] ?? 0));
        $priority = max(1, min(999, (int)($input['priority'] ?? 100)));
        $status = mg_ads_enum($input['status'] ?? 'active', ['active','paused','archived'], 'active');
        if ($assignmentId <= 0) mg_fail('Assignment id is required.', 422);
        $stmt = $pdo->prepare('UPDATE ad_campaign_placements SET priority=?, status=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$priority, $status, $assignmentId]);
        mg_ok(mg_ads_admin_load_payload($pdo), 'Placement assignment updated.');
    }

    if ($action === 'archive_assignment') {
        $assignmentId = max(0, (int)($input['assignment_id'] ?? 0));
        if ($assignmentId <= 0) mg_fail('Assignment id is required.', 422);
        $stmt = $pdo->prepare("UPDATE ad_campaign_placements SET status='archived', updated_at=NOW() WHERE id=?");
        $stmt->execute([$assignmentId]);
        mg_ok(mg_ads_admin_load_payload($pdo), 'Placement assignment archived.');
    }
} catch (Throwable $error) {
    mg_security_log('error', 'ads.admin_placement_control_failed', 'Admin ad placement control failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($user['id'] ?? 0));
    mg_fail($error->getMessage(), 422);
}
