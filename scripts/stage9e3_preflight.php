<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$pdo = mg_db();
$errors = [];
$warnings = [];
$report = [
    'mode' => 'early_install_upgrade_preflight',
    'existing_install_detected' => false,
    'user_count' => 0,
    'role_count' => 0,
    'stage_tables_present' => [],
    'missing_base_tables' => [],
    'warnings' => [],
    'errors' => [],
];

function mg_stage9e3_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() === 1;
}

$baseTables = ['users', 'roles', 'permissions', 'role_permissions'];
foreach ($baseTables as $table) {
    if (!mg_stage9e3_table_exists($pdo, $table)) {
        $errors[] = 'Missing required base table: ' . $table;
        $report['missing_base_tables'][] = $table;
    }
}

if (mg_stage9e3_table_exists($pdo, 'users')) {
    $report['user_count'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $report['existing_install_detected'] = $report['user_count'] > 0;
}
if (mg_stage9e3_table_exists($pdo, 'roles')) {
    $report['role_count'] = (int)$pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
}

if ($report['user_count'] > 0) {
    $warnings[] = 'Existing users detected. Do not wipe or recreate the database; run additive stage scripts only.';
}

$stageTables = [
    'commerce_orders',
    'commerce_order_items',
    'pppm_items',
    'entitlements',
    'microgift_templates',
    'microgift_instances',
    'microgift_claims',
    'microgift_redemptions',
    'microgift_review_items',
];
foreach ($stageTables as $table) {
    $report['stage_tables_present'][$table] = mg_stage9e3_table_exists($pdo, $table);
}

$configChecks = [
    'MG_DB_HOST',
    'MG_DB_NAME',
    'MG_DB_USER',
    'MG_CLAIM_CODE_PEPPER',
    'MG_MEDIA_SIGNING_SECRET',
    'MG_PAYMENT_PROVIDER',
];
foreach ($configChecks as $name) {
    if (getenv($name) === false || trim((string)getenv($name)) === '') {
        $warnings[] = 'Environment variable not detected in CLI context: ' . $name;
    }
}

$report['warnings'] = $warnings;
$report['errors'] = $errors;

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($errors !== []) {
    exit(1);
}
