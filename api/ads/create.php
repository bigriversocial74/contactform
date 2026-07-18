<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('POST');
$user = mg_require_api_user();
$pdo = mg_db();
$input = mg_input();
mg_require_csrf_for_write($input);
mg_ads_require_merchant_user($user, $pdo);

try {
    if (function_exists('mg_rate_limit')) mg_rate_limit('ads.create', 'user:' . (int)$user['id'], 60, 60);
    $campaign = mg_ads_upsert_campaign($pdo, (int)$user['id'], $input, null);
    mg_ok(['schema_ready' => true, 'campaign' => $campaign], 'Draft ad campaign created.');
} catch (Throwable $error) {
    mg_fail_unexpected($error, 'ads.create_failed', 'Unable to create the ad campaign.', 500);
}
