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
        mg_ok([
            'schema_ready' => false,
            'placement_key' => $placementKey,
            'requested_limit' => $requestedLimit,
            'returned_count' => 0,
            'items' => [],
            'empty_reason' => 'schema_not_ready',
        ], 'Campaign Ads Manager migration is required.');
    }

    mg_ads_seed_placements($pdo);
    $placementStmt = $pdo->prepare('SELECT is_active, max_ads FROM ad_placements WHERE placement_key=? LIMIT 1');
    $placementStmt->execute([$placementKey]);
    $placement = $placementStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($placement) || (int)($placement['is_active'] ?? 0) !== 1) {
        mg_ok([
            'schema_ready' => true,
            'placement_key' => $placementKey,
            'requested_limit' => $requestedLimit,
            'returned_count' => 0,
            'items' => [],
            'placement_active' => false,
            'empty_reason' => 'placement_inactive',
        ], 'Placement is inactive.');
    }

    $maxAds = max(1, min(20, (int)($placement['max_ads'] ?? 1)));
    $limit = min($requestedLimit, $maxAds);
    $items = mg_ads_render_placement($pdo, $placementKey, $limit);
    $items = array_values(array_filter($items, static function ($item): bool {
        if (!is_array($item)) return false;
        $publicId = trim((string)($item['public_id'] ?? $item['id'] ?? ''));
        $creative = is_array($item['creative'] ?? null) ? $item['creative'] : [];
        $headline = trim((string)($creative['headline'] ?? $item['title'] ?? ''));
        return $publicId !== '' && $headline !== '';
    }));

    mg_ok([
        'schema_ready' => true,
        'placement_key' => $placementKey,
        'requested_limit' => $requestedLimit,
        'items' => $items,
        'returned_count' => count($items),
        'placement_active' => true,
        'max_ads' => $maxAds,
        'empty_reason' => $items === [] ? 'no_renderable_ads' : null,
    ], 'Placement loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'ads.render_failed', 'Campaign Ads Manager placement render failed.', ['exception_class' => $error::class, 'message' => $error->getMessage(), 'placement_key' => $placementKey], null);
    mg_ok([
        'schema_ready' => false,
        'placement_key' => $placementKey,
        'requested_limit' => $requestedLimit,
        'returned_count' => 0,
        'items' => [],
        'placement_active' => false,
        'empty_reason' => 'render_error',
    ], 'Placement unavailable.');
}
