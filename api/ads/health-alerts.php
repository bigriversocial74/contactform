<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$scope = strtolower(trim((string)($_GET['scope'] ?? 'merchant')));
$isAdmin = $scope === 'admin';

if ($isAdmin) {
    mg_ads_require_admin_user($user);
} else {
    mg_ads_require_merchant_user($user, $pdo);
}

function mg_ads_health_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) return false;
    try {
        $database = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
        if ($database !== '') {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
            $stmt->execute([$database, $table, $column]);
            return (bool)$stmt->fetchColumn();
        }
    } catch (Throwable) {
        // Keep health checks non-blocking.
    }
    try {
        $stmt = $pdo->query('DESCRIBE `' . str_replace('`', '``', $table) . '` `' . str_replace('`', '``', $column) . '`');
        return (bool)($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
    } catch (Throwable) {
        return false;
    }
}

function mg_ads_health_alert(string $level, string $title, string $message, array $meta = []): array
{
    return ['level' => $level, 'title' => $title, 'message' => $message, 'meta' => $meta];
}

function mg_ads_health_admin(PDO $pdo): array
{
    $alerts = [];
    $schema = mg_ads_schema_status($pdo);
    if (!$schema['ready']) {
        $missing = array_keys(array_filter($schema['tables'], static fn($ready) => !$ready));
        return [
            'scope' => 'admin',
            'schema_ready' => false,
            'alerts' => [mg_ads_health_alert('critical', 'Campaign Ads setup incomplete', 'Missing tables: ' . implode(', ', $missing) . '. Run the Phase 1 SQL migration.')],
            'summary' => ['critical' => 1, 'warning' => 0, 'info' => 0, 'ok' => 0],
        ];
    }
    mg_ads_seed_placements($pdo);

    if (!mg_ads_table_exists($pdo, 'wallet_items')) $alerts[] = mg_ads_health_alert('warning', 'Wallet value attribution unavailable', 'wallet_items table was not detected, so value attribution cannot be fully checked.');
    if (!mg_ads_health_column_exists($pdo, 'ad_events', 'wallet_item_id')) $alerts[] = mg_ads_health_alert('warning', 'Direct attribution column missing', 'ad_events.wallet_item_id is required for direct wallet attribution.');
    if (mg_ads_table_exists($pdo, 'wallet_items') && !mg_ads_health_column_exists($pdo, 'wallet_items', 'value_cents_snapshot')) $alerts[] = mg_ads_health_alert('warning', 'Wallet value field missing', 'wallet_items.value_cents_snapshot is required for claimed/redeemed value reporting.');

    $placements = [];
    $stmt = $pdo->query('SELECT placement_key, placement_name, is_active, max_ads FROM ad_placements ORDER BY placement_key ASC');
    foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) $placements[(string)$row['placement_key']] = $row;

    $assignmentStmt = $pdo->query("SELECT cp.placement_key, COUNT(*) total FROM ad_campaign_placements cp INNER JOIN ad_campaigns c ON c.id=cp.ad_campaign_id WHERE cp.status='active' AND c.status IN ('approved','active') GROUP BY cp.placement_key");
    $assignments = [];
    foreach (($assignmentStmt ? $assignmentStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) $assignments[(string)$row['placement_key']] = (int)$row['total'];

    $eventStmt = $pdo->query("SELECT placement_key, event_type, COUNT(*) total, MAX(created_at) last_at FROM ad_events WHERE placement_key IS NOT NULL AND placement_key<>'' GROUP BY placement_key,event_type");
    $events = [];
    foreach (($eventStmt ? $eventStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $key = (string)$row['placement_key'];
        $events[$key][(string)$row['event_type']] = ['count' => (int)$row['total'], 'last_at' => $row['last_at']];
    }

    foreach ($placements as $key => $placement) {
        $active = (int)($placement['is_active'] ?? 0) === 1;
        $assigned = (int)($assignments[$key] ?? 0);
        if (!$active && in_array($key, ['sidebar_sponsored_card','world_canvas_sponsored_pin','target_zone_sponsored_drop','inbox_recommendation','claim_success_recommendation'], true)) {
            $alerts[] = mg_ads_health_alert('info', 'Placement is inactive', (string)($placement['placement_name'] ?? $key) . ' is inactive. Enable it from Ad placements when ready.', ['placement_key' => $key]);
        }
        if ($active && $assigned < 1) {
            $alerts[] = mg_ads_health_alert('warning', 'Active placement has no ads assigned', (string)($placement['placement_name'] ?? $key) . ' is active but has no approved/active campaign assignments.', ['placement_key' => $key]);
            continue;
        }
        if ($active && $assigned > 0) {
            try {
                $items = mg_ads_render_placement($pdo, $key, min(5, max(1, (int)($placement['max_ads'] ?? 1))));
                if (count($items) < 1) $alerts[] = mg_ads_health_alert('critical', 'Placement render returned zero ads', (string)($placement['placement_name'] ?? $key) . ' has assignments but the renderer returned no ads.', ['placement_key' => $key]);
                $impressions = (int)($events[$key]['impression']['count'] ?? 0);
                if (count($items) > 0 && $impressions < 1) $alerts[] = mg_ads_health_alert('info', 'Renderable placement has no impressions yet', (string)($placement['placement_name'] ?? $key) . ' can render ads, but no impressions have been tracked yet.', ['placement_key' => $key]);
            } catch (Throwable $error) {
                $alerts[] = mg_ads_health_alert('critical', 'Placement render failed', $key . ' render check failed: ' . $error->getMessage(), ['placement_key' => $key]);
            }
        }
    }

    if ($alerts === []) $alerts[] = mg_ads_health_alert('ok', 'Campaign Ads health looks good', 'No active delivery alerts were found.');
    return ['scope' => 'admin', 'schema_ready' => true, 'alerts' => $alerts, 'summary' => mg_ads_health_summary($alerts)];
}

function mg_ads_health_merchant(PDO $pdo, array $user): array
{
    $alerts = [];
    $schema = mg_ads_schema_status($pdo);
    if (!$schema['ready']) {
        return ['scope' => 'merchant', 'schema_ready' => false, 'alerts' => [mg_ads_health_alert('critical', 'Campaign Ads setup incomplete', 'Campaign Ads tables are not ready yet.')], 'summary' => ['critical' => 1, 'warning' => 0, 'info' => 0, 'ok' => 0]];
    }
    mg_ads_seed_placements($pdo);
    $merchantId = (int)($user['id'] ?? 0);

    $campaignStmt = $pdo->prepare("SELECT c.id, c.public_id, c.title, c.status, COUNT(cp.id) placement_count FROM ad_campaigns c LEFT JOIN ad_campaign_placements cp ON cp.ad_campaign_id=c.id AND cp.status<>'archived' WHERE c.merchant_id=? AND c.status<>'archived' GROUP BY c.id ORDER BY c.updated_at DESC LIMIT 100");
    $campaignStmt->execute([$merchantId]);
    $campaigns = $campaignStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$campaigns) {
        $alerts[] = mg_ads_health_alert('info', 'No campaigns created yet', 'Create your first sponsored campaign, save it, then submit it for review.');
        return ['scope' => 'merchant', 'schema_ready' => true, 'alerts' => $alerts, 'summary' => mg_ads_health_summary($alerts)];
    }

    $eventStmt = $pdo->prepare("SELECT c.id campaign_id, e.event_type, COUNT(*) total FROM ad_campaigns c LEFT JOIN ad_events e ON e.ad_campaign_id=c.id WHERE c.merchant_id=? AND c.status<>'archived' GROUP BY c.id,e.event_type");
    $eventStmt->execute([$merchantId]);
    $events = [];
    foreach ($eventStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (($row['event_type'] ?? null) === null) continue;
        $events[(int)$row['campaign_id']][(string)$row['event_type']] = (int)$row['total'];
    }

    foreach ($campaigns as $campaign) {
        $id = (int)$campaign['id'];
        $title = (string)($campaign['title'] ?? 'Campaign');
        $status = (string)($campaign['status'] ?? '');
        $placementCount = (int)($campaign['placement_count'] ?? 0);
        $impressions = (int)($events[$id]['impression'] ?? 0);
        $clicks = (int)($events[$id]['click'] ?? 0);
        if ($placementCount < 1) $alerts[] = mg_ads_health_alert('warning', 'Campaign has no placements', $title . ' is not assigned to any placement.', ['campaign_id' => (string)$campaign['public_id']]);
        if (in_array($status, ['approved','active'], true) && $impressions < 1) $alerts[] = mg_ads_health_alert('info', 'Campaign has no impressions yet', $title . ' is approved/active but has not recorded impressions yet.', ['campaign_id' => (string)$campaign['public_id']]);
        if ($impressions > 0 && $clicks < 1) $alerts[] = mg_ads_health_alert('info', 'Campaign has impressions but no clicks', $title . ' is being seen but has not produced clicks yet.', ['campaign_id' => (string)$campaign['public_id']]);
    }

    if ($alerts === []) $alerts[] = mg_ads_health_alert('ok', 'Campaign Ads health looks good', 'Your ads have no active setup alerts.');
    return ['scope' => 'merchant', 'schema_ready' => true, 'alerts' => $alerts, 'summary' => mg_ads_health_summary($alerts)];
}

function mg_ads_health_summary(array $alerts): array
{
    $summary = ['critical' => 0, 'warning' => 0, 'info' => 0, 'ok' => 0];
    foreach ($alerts as $alert) {
        $level = (string)($alert['level'] ?? 'info');
        if (!isset($summary[$level])) $summary[$level] = 0;
        $summary[$level]++;
    }
    return $summary;
}

try {
    mg_ok($isAdmin ? mg_ads_health_admin($pdo) : mg_ads_health_merchant($pdo, $user), 'Campaign Ads health alerts loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'ads.health_alerts_failed', 'Campaign Ads health alerts failed.', ['exception_class' => $error::class, 'message' => $error->getMessage(), 'scope' => $scope], (int)($user['id'] ?? 0));
    mg_fail('Unable to load Campaign Ads health alerts.', 422);
}
