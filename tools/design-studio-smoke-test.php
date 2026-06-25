<?php
declare(strict_types=1);

/**
 * Design Studio Stage 19 smoke test.
 *
 * Run from the repository root after importing:
 *   php tools/design-studio-smoke-test.php
 *
 * This script is read-only. It checks required files, database tables,
 * migration ledger rows, permissions, role grants, and seeded AI presets.
 */

$root = dirname(__DIR__);
$failures = [];
$warnings = [];
$passes = [];

function mg_smoke_pass(string $message): void
{
    global $passes;
    $passes[] = $message;
    echo "[PASS] {$message}\n";
}

function mg_smoke_warn(string $message): void
{
    global $warnings;
    $warnings[] = $message;
    echo "[WARN] {$message}\n";
}

function mg_smoke_fail(string $message): void
{
    global $failures;
    $failures[] = $message;
    echo "[FAIL] {$message}\n";
}

function mg_smoke_file(string $path): void
{
    global $root;
    $full = $root . '/' . ltrim($path, '/');
    if (is_file($full)) {
        mg_smoke_pass("File exists: {$path}");
    } else {
        mg_smoke_fail("Missing file: {$path}");
    }
}

function mg_smoke_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function mg_smoke_count_where(PDO $pdo, string $table, string $where, array $params = []): int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new RuntimeException('Invalid table name.');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

$requiredFiles = [
    'database/stage_19_design_studio_qr_library.sql',
    'database/stage_19_design_studio_IMPORT_ORDER.md',
    'includes/design-studio-renderer.php',
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

foreach ($requiredFiles as $file) mg_smoke_file($file);

$dbFile = $root . '/api/db.php';
if (!is_file($dbFile)) {
    mg_smoke_fail('Missing api/db.php; cannot test database.');
    exit(1);
}

require_once $dbFile;

try {
    $pdo = mg_db();
    $pdo->query('SELECT 1');
    mg_smoke_pass('Database connection works.');
} catch (Throwable $e) {
    mg_smoke_fail('Database connection failed: ' . $e->getMessage());
    echo "\nSummary: " . count($passes) . " passed, " . count($warnings) . " warnings, " . count($failures) . " failed.\n";
    exit(1);
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
    if (mg_smoke_table_exists($pdo, $table)) {
        mg_smoke_pass("Table exists: {$table}");
    } else {
        mg_smoke_fail("Missing table: {$table}");
    }
}

if (mg_smoke_table_exists($pdo, 'microgifter_schema_migrations')) {
    $migrationCount = mg_smoke_count_where($pdo, 'microgifter_schema_migrations', 'migration_key = ?', ['stage_19_design_studio_qr_library']);
    $migrationCount > 0 ? mg_smoke_pass('Migration ledger contains stage_19_design_studio_qr_library.') : mg_smoke_fail('Migration ledger missing stage_19_design_studio_qr_library.');
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

if (mg_smoke_table_exists($pdo, 'permissions')) {
    foreach ($permissions as $permission) {
        $count = mg_smoke_count_where($pdo, 'permissions', 'slug = ?', [$permission]);
        $count > 0 ? mg_smoke_pass("Permission exists: {$permission}") : mg_smoke_fail("Missing permission: {$permission}");
    }
} else {
    mg_smoke_fail('Missing permissions table.');
}

if (mg_smoke_table_exists($pdo, 'merchant_design_ai_presets')) {
    $presetCount = mg_smoke_count_where($pdo, 'merchant_design_ai_presets', "preset_key IN ('restaurant-food-promo','live-event-promo','fitness-challenge','holiday-gift-card','local-rewards-campaign')");
    $presetCount === 5 ? mg_smoke_pass('All 5 Design Studio AI presets exist.') : mg_smoke_fail("Expected 5 AI presets, found {$presetCount}.");
}

if (mg_smoke_table_exists($pdo, 'roles') && mg_smoke_table_exists($pdo, 'role_permissions') && mg_smoke_table_exists($pdo, 'permissions')) {
    $stmt = $pdo->query("SELECT r.slug role_slug, COUNT(*) permission_count FROM roles r JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug IN ('merchant','admin','super_admin') AND p.slug LIKE 'merchant.%' GROUP BY r.slug");
    $rows = $stmt->fetchAll();
    if ($rows) {
        foreach ($rows as $row) {
            mg_smoke_pass("Role grants present for {$row['role_slug']}: {$row['permission_count']} merchant permissions.");
        }
    } else {
        mg_smoke_warn('No merchant permission grants found for merchant/admin/super_admin roles. Check role slugs and role_permissions.');
    }
}

if (mg_smoke_table_exists($pdo, 'merchant_design_campaign_links')) {
    $stmt = $pdo->query("SHOW COLUMNS FROM merchant_design_campaign_links LIKE 'campaign_unique_hash'");
    $stmt->fetch() ? mg_smoke_pass('Campaign link uniqueness generated column exists.') : mg_smoke_fail('Missing campaign_unique_hash generated column.');
}

if (mg_smoke_table_exists($pdo, 'merchant_design_export_jobs')) {
    foreach (['attempt_count','locked_at','locked_by','next_attempt_at','failure_code','renderer_version'] as $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='merchant_design_export_jobs' AND column_name=?");
        $stmt->execute([$column]);
        (int) $stmt->fetchColumn() > 0 ? mg_smoke_pass("Export queue column exists: {$column}") : mg_smoke_fail("Missing export queue column: {$column}");
    }
}

echo "\nSummary: " . count($passes) . " passed, " . count($warnings) . " warnings, " . count($failures) . " failed.\n";

if ($failures) {
    echo "\nDesign Studio smoke test failed. Fix the failures above before browser testing.\n";
    exit(1);
}

echo "\nDesign Studio smoke test passed. Continue with browser/API flow testing.\n";
exit(0);
