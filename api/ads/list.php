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
        mg_ok(['schema_ready' => false, 'tables' => $schema['tables'], 'campaigns' => []], 'Campaign Ads Manager migration is required.');
    }
    $status = mg_ads_enum($_GET['status'] ?? '', mg_ads_allowed_statuses(), '');
    $campaigns = mg_ads_list_campaigns($pdo, $admin ? null : (int)$user['id'], $admin, $status, (int)($_GET['limit'] ?? 50));
    mg_ok(['schema_ready' => true, 'campaigns' => $campaigns], 'Ad campaigns loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'ads.list_failed', 'Campaign Ads Manager list failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)$user['id']);
    mg_fail($error->getMessage(), 422);
}
