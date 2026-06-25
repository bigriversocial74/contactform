<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/market/marketplace-index-engine.php';

mg_require_method('GET');
$user = mg_require_api_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$allowed = in_array('super_admin', $roles, true) || in_array('admin.health.view', $permissions, true) || in_array('demand.dashboard.view', $permissions, true) || in_array('intelligence.dashboard.view', $permissions, true);
if (!$allowed) mg_fail('Marketplace index access denied.', 403);

$days = max(7, min(365, (int)($_GET['days'] ?? 90)));
try {
    mg_ok(mg_marketplace_index_build(mg_db(), $days), 'Marketplace index loaded.');
} catch (Throwable $e) {
    mg_fail($e->getMessage() ?: 'Unable to load marketplace index.', 500);
}
