<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
mg_ads_require_merchant_user($user, $pdo);

try {
    $schema = mg_ads_schema_status($pdo);
    if (!$schema['ready']) {
        mg_ok(['schema_ready' => false, 'tables' => $schema['tables'], 'placements' => []], 'Campaign Ads Manager migration is required.');
    }
    mg_ads_seed_placements($pdo);
    $stmt = $pdo->query('SELECT placement_key,placement_name,surface,description,is_active,max_ads FROM ad_placements ORDER BY FIELD(placement_key,\'feed_sponsored_card\',\'sidebar_sponsored_card\',\'world_canvas_sponsored_pin\',\'target_zone_sponsored_drop\'),placement_key ASC');
    mg_ok(['schema_ready' => true, 'placements' => $stmt->fetchAll(PDO::FETCH_ASSOC)], 'Placements loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'ads.placements_failed', 'Campaign Ads Manager placements failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
