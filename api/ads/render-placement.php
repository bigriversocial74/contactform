<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('GET');
$pdo = mg_db();
$placementKey = mg_ads_enum($_GET['placement_key'] ?? '', mg_ads_allowed_placements(), 'feed_sponsored_card');
$requestedLimit = max(1, min(20, (int)($_GET['limit'] ?? 1)));

try {
    $schema = mg_ads_schema_status($pdo);
    if (!$schema['ready']) {
        mg_ok(['schema_ready' => false, 'placement_key' => $placementKey, 'items' => []], 'Campaign Ads Manager migration is required.');
    }
    mg_ads_seed_placements($pdo);
    $placementStmt = $pdo->prepare('SELECT is_active, max_ads FROM ad_placements WHERE placement_key=? LIMIT 1');
    $placementStmt->execute([$placementKey]);
    $placement = $placementStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($placement) || (int)($placement['is_active'] ?? 0) !== 1) {
        mg_ok(['schema_ready' => true, 'placement_key' => $placementKey, 'items' => [], 'placement_active' => false], 'Placement is inactive.');
    }
    $maxAds = max(1, min(20, (int)($placement['max_ads'] ?? 1)));
    $limit = min($requestedLimit, $maxAds);
    $items = mg_ads_render_placement($pdo, $placementKey, $limit);
    mg_ok(['schema_ready' => true, 'placement_key' => $placementKey, 'items' => $items, 'placement_active' => true, 'max_ads' => $maxAds], 'Placement loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'ads.render_failed', 'Campaign Ads Manager placement render failed.', ['exception_class' => $error::class, 'message' => $error->getMessage(), 'placement_key' => $placementKey], null);
    mg_ok(['schema_ready' => false, 'placement_key' => $placementKey, 'items' => []], 'Placement unavailable.');
}
