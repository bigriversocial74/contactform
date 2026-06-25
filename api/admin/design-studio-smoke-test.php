<?php
declare(strict_types=1);

/**
 * Browser/API smoke test for Stage 19 Design Studio.
 *
 * Open while logged in as an admin/super admin or a user with
 * merchant.design_templates.admin permission:
 *   /api/admin/design-studio-smoke-test.php
 *
 * This endpoint is read-only. It does not create, update, or delete data.
 */

require_once __DIR__ . '/../bootstrap.php';

function mg_design_smoke_user_can(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (array_intersect($roles, ['admin', 'super_admin'])) return true;
    return mg_api_user_has_permission($user, 'merchant.design_templates.admin');
}

function mg_design_smoke_table_exists(PDO $pdo, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function mg_design_smoke_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function mg_design_smoke_count_where(PDO $pdo, string $table, string $where, array $params = []): int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new RuntimeException('Invalid table name.');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function mg_design_smoke_add(array &$result, string $type, string $message, array $context = []): void
{
    $result[$type][] = ['message' => $message, 'context' => $context];
}

$user = mg_require_api_user();
if (!mg_design_smoke_user_can($user)) {
    mg_security_log('warning', 'admin.design_studio_smoke_test.denied', 'Design Studio smoke test access refused.', [], (int) $user['id']);
    mg_fail('Access refused.', 403);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') mg_fail('Method not allowed.', 405);

$result = [
    'passes' => [],
    'warnings' => [],
    'failures' => [],
];

$root = dirname(__DIR__, 2);
$requiredFiles = [
    'database/stage_19_design_studio_qr_library.sql',
    'database/stage_19_design_studio_IMPORT_ORDER.md',
    'docs/design-studio-release-checklist.md',
    'design-studio.php',
    'qr.php',
    'api/merchant/_design_studio_guard.php',
    'api/merchant/brand-kit.php',
    'api/merchant/design-export.php',
    'api/merchant/design-studio-assets.php',
    'api/merchant/qr-library.php',
    'api/admin/design-studio-templates.php',
    'api/admin/design-studio-smoke-test.php',
    'api/admin/design-export-worker.php',
    'assets/js/design-studio.js',
    'assets/css/design-studio.css',
    'assets/css/design-studio-wiring.css',
];

foreach ($requiredFiles as $file) {
    is_file($root . '/' . $file)
        ? mg_design_smoke_add($result, 'passes', 'File exists: ' . $file)
        : mg_design_smoke_add($result, 'failures', 'Missing file: ' . $file);
}

try {
    $pdo = mg_db();
    $pdo->query('SELECT 1');
    mg_design_smoke_add($result, 'passes', 'Database connection works.');
} catch (Throwable $e) {
    mg_design_smoke_add($result, 'failures', 'Database connection failed.', ['error' => $e->getMessage()]);
    mg_ok([
        'status' => 'failed',
        'summary' => ['passed' => count($result['passes']), 'warnings' => count($result['warnings']), 'failed' => count($result['failures'])],
        'results' => $result,
    ], 'Design Studio smoke test failed.');
}

$requiredTables = [
    'microgifter_schema_migrations',
    'merchant_qr_codes',
    'merchant_qr_code_scans',
    'merchant_brand_kits',
    'merchant_brand_kit_assets',
    'merchant_design_templates',
    'merchant_design_template_reviews',
    'merchant_design_projects',
    'merchant_design_ai_jobs',
    'merchant_design_ai_presets',
    'merchant_design_assets',
    'merchant_design_export_jobs',
    'merchant_design_campaign_links',
];

foreach ($requiredTables as $table) {
    mg_design_smoke_table_exists($pdo, $table)
        ? mg_design_smoke_add($result, 'passes', 'Table exists: ' . $table)
        : mg_design_smoke_add($result, 'failures', 'Missing table: ' . $table);
}

if (mg_design_smoke_table_exists($pdo, 'microgifter_schema_migrations')) {
    $migrationCount = mg_design_smoke_count_where($pdo, 'microgifter_schema_migrations', 'migration_key = ?', ['stage_19_design_studio_qr_library']);
    $migrationCount > 0
        ? mg_design_smoke_add($result, 'passes', 'Migration ledger contains stage_19_design_studio_qr_library.')
        : mg_design_smoke_add($result, 'failures', 'Migration ledger missing stage_19_design_studio_qr_library.');
}

$permissions = [
    'merchant.design_studio.view',
    'merchant.design_studio.manage',
    'merchant.brand_kits.view',
    'merchant.brand_kits.manage',
    'merchant.design_templates.view',
    'merchant.design_templates.manage',
    'merchant.design_templates.admin',
    'merchant.design_projects.view',
    'merchant.design_projects.manage',
    'merchant.design_assets.view',
    'merchant.design_assets.manage',
    'merchant.design_ai.generate',
    'merchant.design_ai.admin',
    'merchant.qr_library.view',
    'merchant.qr_library.manage',
];

if (mg_design_smoke_table_exists($pdo, 'permissions')) {
    foreach ($permissions as $permission) {
        $count = mg_design_smoke_count_where($pdo, 'permissions', 'slug = ?', [$permission]);
        $count > 0
            ? mg_design_smoke_add($result, 'passes', 'Permission exists: ' . $permission)
            : mg_design_smoke_add($result, 'failures', 'Missing permission: ' . $permission);
    }
} else {
    mg_design_smoke_add($result, 'failures', 'Missing permissions table.');
}

if (mg_design_smoke_table_exists($pdo, 'merchant_design_ai_presets')) {
    $presetCount = mg_design_smoke_count_where($pdo, 'merchant_design_ai_presets', "preset_key IN ('restaurant-food-promo','live-event-promo','fitness-challenge','holiday-gift-card','local-rewards-campaign')");
    $presetCount === 5
        ? mg_design_smoke_add($result, 'passes', 'All 5 Design Studio AI presets exist.')
        : mg_design_smoke_add($result, 'failures', 'Expected 5 AI presets.', ['found' => $presetCount]);
}

if (mg_design_smoke_table_exists($pdo, 'roles') && mg_design_smoke_table_exists($pdo, 'role_permissions') && mg_design_smoke_table_exists($pdo, 'permissions')) {
    $stmt = $pdo->query("SELECT r.slug role_slug, COUNT(*) permission_count FROM roles r JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug IN ('merchant','admin','super_admin') AND p.slug LIKE 'merchant.%' GROUP BY r.slug");
    $rows = $stmt->fetchAll();
    if ($rows) {
        foreach ($rows as $row) {
            mg_design_smoke_add($result, 'passes', 'Role grants present for ' . $row['role_slug'] . ': ' . $row['permission_count'] . ' merchant permissions.');
        }
    } else {
        mg_design_smoke_add($result, 'warnings', 'No merchant permission grants found for merchant/admin/super_admin roles. Check role slugs and role_permissions.');
    }
}

if (mg_design_smoke_table_exists($pdo, 'merchant_design_campaign_links')) {
    mg_design_smoke_column_exists($pdo, 'merchant_design_campaign_links', 'campaign_unique_hash')
        ? mg_design_smoke_add($result, 'passes', 'Campaign link uniqueness generated column exists.')
        : mg_design_smoke_add($result, 'failures', 'Missing campaign_unique_hash generated column.');
}

if (mg_design_smoke_table_exists($pdo, 'merchant_design_export_jobs')) {
    foreach (['attempt_count','locked_at','locked_by','next_attempt_at','failure_code','renderer_version'] as $column) {
        mg_design_smoke_column_exists($pdo, 'merchant_design_export_jobs', $column)
            ? mg_design_smoke_add($result, 'passes', 'Export queue column exists: ' . $column)
            : mg_design_smoke_add($result, 'failures', 'Missing export queue column: ' . $column);
    }
}

$failed = count($result['failures']);
$status = $failed > 0 ? 'failed' : 'passed';
mg_ok([
    'status' => $status,
    'summary' => [
        'passed' => count($result['passes']),
        'warnings' => count($result['warnings']),
        'failed' => $failed,
    ],
    'results' => $result,
], $failed > 0 ? 'Design Studio smoke test failed.' : 'Design Studio smoke test passed.');
