<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'api/admin/_migration_reconciliation_meta.php',
    'api/admin/_migration_reconciliation_checks.php',
    'api/admin/_migration_reconciliation_analyze.php',
    'api/admin/_migration_reconciliation.php',
    'api/admin/system-health-action.php',
    'api/admin/system-health.php',
    'assets/js/admin-health-warning-filter.js',
];
foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }
}

$meta = file_get_contents($root . '/api/admin/_migration_reconciliation_meta.php') ?: '';
$checks = file_get_contents($root . '/api/admin/_migration_reconciliation_checks.php') ?: '';
$analyze = file_get_contents($root . '/api/admin/_migration_reconciliation_analyze.php') ?: '';
$service = file_get_contents($root . '/api/admin/_migration_reconciliation.php') ?: '';
$action = file_get_contents($root . '/api/admin/system-health-action.php') ?: '';
$health = file_get_contents($root . '/api/admin/system-health.php') ?: '';
$ui = file_get_contents($root . '/assets/js/admin-health-warning-filter.js') ?: '';

$contracts = [
    [$meta, 'information_schema.COLUMNS'],
    [$meta, 'information_schema.STATISTICS'],
    [$meta, 'information_schema.TABLE_CONSTRAINTS'],
    [$meta, 'mg_admin_migration_reconciliation_enum_repair_sql'],
    [$checks, 'mg_admin_migration_reconciliation_add_create_table_checks'],
    [$checks, 'mg_admin_migration_reconciliation_add_alter_checks'],
    [$checks, 'delivery.operations.manage'],
    [$analyze, "'recordable' => \$status === 'installed'"],
    [$service, 'SELECT GET_LOCK(?, 15)'],
    [$service, 'mg_admin_migration_reconciliation_verify_token'],
    [$service, 'INSERT INTO schema_migrations'],
    [$service, 'It never executes migration DDL.'],
    [$action, "'migration_reconciliation_plan'"],
    [$action, "'migration_reconciliation_apply'"],
    [$health, "'migration_reconciliation_plan'"],
    [$ui, 'Analyze installed schema'],
    [$ui, 'Record verified installed'],
    [$ui, 'Download repair SQL'],
];
foreach ($contracts as [$haystack, $needle]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing contract: {$needle}\n");
        exit(1);
    }
}

if (str_contains($service, '$pdo->exec($sql)') || str_contains($service, 'run_migrations.php')) {
    fwrite(STDERR, "Reconciliation service must not execute migration DDL or invoke the migration runner.\n");
    exit(1);
}

require_once $root . '/api/admin/_migration_reconciliation_meta.php';
$sample = <<<'SQL'
ALTER TABLE campaigns MODIFY campaign_type ENUM('one','two') NOT NULL;
SET @sql := IF(1, 'ALTER TABLE jobs ADD COLUMN lease_token CHAR(64) NULL', 'SELECT 1');
CREATE TABLE IF NOT EXISTS sample_table (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  status ENUM('new','done') NOT NULL DEFAULT 'new',
  PRIMARY KEY (id),
  UNIQUE KEY uq_sample_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
$alters = mg_admin_migration_reconciliation_alter_statements($sample);
$creates = mg_admin_migration_reconciliation_create_tables($sample);
if (count($alters) !== 2 || count($creates) !== 1) {
    fwrite(STDERR, "Migration SQL parser fixture failed.\n");
    exit(1);
}
$parts = mg_admin_migration_reconciliation_split_top_level("MODIFY status ENUM('one','two') NOT NULL, ADD KEY idx_status (status,id)");
if (count($parts) !== 2) {
    fwrite(STDERR, "Top-level SQL operation splitter failed.\n");
    exit(1);
}

echo "System Health Migration Reconciliation: 10/10 contract passed.\n";
