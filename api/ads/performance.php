<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$admin = isset($_GET['scope']) && $_GET['scope'] === 'admin';
if ($admin) mg_ads_require_admin_user($user);
else mg_ads_require_merchant_user($user, $pdo);

try {
    $schema = mg_ads_schema_status($pdo);
    if (!$schema['ready']) {
        mg_ok(['schema_ready' => false, 'tables' => $schema['tables'], 'performance' => null], 'Campaign Ads Manager migration is required.');
    }
    $publicId = mg_ads_text($_GET['ad_campaign_id'] ?? $_GET['public_id'] ?? '', 80, '');
    $performance = mg_ads_performance($pdo, $admin ? null : (int)$user['id'], $publicId !== '' ? $publicId : null, $admin);
    mg_ok(['schema_ready' => true, 'performance' => $performance], 'Ad performance loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'ads.performance_failed', 'Campaign Ads Manager performance failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
