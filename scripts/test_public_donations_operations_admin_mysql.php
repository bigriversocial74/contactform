<?php
declare(strict_types=1);

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MYSQL_PORT') ?: 3306);
$database = getenv('MYSQL_DATABASE') ?: 'microgifter_public_donations_ops';
$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: 'root';
$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function mg_db(): PDO
{
    global $pdo;
    return $pdo;
}

require_once dirname(__DIR__) . '/includes/public-donations-feature.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};
$import = static function (PDO $pdo): void {
    $sql = file_get_contents(dirname(__DIR__) . '/database/20260724_public_donations_operations_admin_v1_single_install.sql');
    if (!is_string($sql)) throw new RuntimeException('Unable to read operations installer.');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
};

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['public_donations_reconciliation_receipts','public_donations_operations_settings','role_permissions','permissions','roles'] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec("CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(160) NOT NULL UNIQUE,
    name VARCHAR(220) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY(role_id,permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("INSERT INTO roles (slug,name,created_at) VALUES
    ('admin','Admin',NOW()),('super_admin','Super Admin',NOW()),('merchant','Merchant',NOW())");

putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE=enabled');
putenv('MG_PUBLIC_DONATIONS_MERCHANT_IDS=4,8');
$import($pdo);

$tables = $pdo->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('public_donations_operations_settings','public_donations_reconciliation_receipts')"
)->fetchAll(PDO::FETCH_COLUMN);
$assert(count($tables) === 2, 'Both operations tables must be installed.');
$row = $pdo->query('SELECT * FROM public_donations_operations_settings WHERE id=1')->fetch();
$assert((int)$row['override_active'] === 0, 'Installer must not activate the database override.');
$assert((string)$row['feature_state'] === 'disabled', 'Database row must fail closed while inactive.');
$assert((int)$row['configuration_version'] === 1, 'Initial configuration version must be one.');

$environment = mg_public_donations_rollout_config(true);
$assert($environment['source'] === 'environment', 'Environment must remain authoritative after import.');
$assert($environment['state'] === 'enabled', 'Environment feature state must remain available.');
$assert($environment['selected_merchant_ids'] === [4,8], 'Environment merchant IDs must be normalized.');

$permissionCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM permissions WHERE slug LIKE 'admin.public_donations_operations.%'"
)->fetchColumn();
$assert($permissionCount === 3, 'Three operations permissions must be installed.');
$adminGrantCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM role_permissions rp
     INNER JOIN roles r ON r.id=rp.role_id
     INNER JOIN permissions p ON p.id=rp.permission_id
     WHERE r.slug='admin' AND p.slug LIKE 'admin.public_donations_operations.%'"
)->fetchColumn();
$assert($adminGrantCount === 3, 'Admin role must receive all operations permissions.');

$pdo->prepare(
    "UPDATE public_donations_operations_settings
     SET override_active=1,feature_state='selected_merchants',selected_merchant_ids_json=?,configuration_version=7,
         change_reason='Fixture rollout',updated_by_user_id=99,updated_at=NOW() WHERE id=1"
)->execute([json_encode([9,3,9], JSON_THROW_ON_ERROR)]);
$databaseConfig = mg_public_donations_rollout_config(true);
$assert($databaseConfig['source'] === 'database_override', 'Active database override must become authoritative.');
$assert($databaseConfig['state'] === 'selected_merchants', 'Database feature state must be projected.');
$assert($databaseConfig['selected_merchant_ids'] === [3,9], 'Database merchant IDs must be normalized.');
$assert((int)$databaseConfig['configuration_version'] === 7, 'Database configuration version must be projected.');

$import($pdo);
$preserved = $pdo->query('SELECT override_active,feature_state,configuration_version FROM public_donations_operations_settings WHERE id=1')->fetch();
$assert((int)$preserved['override_active'] === 1, 'Reimport must preserve active override state.');
$assert((string)$preserved['feature_state'] === 'selected_merchants', 'Reimport must preserve configured feature state.');
$assert((int)$preserved['configuration_version'] === 7, 'Reimport must preserve configuration version.');

$receipt = [
    'receipt_id' => '00000000-0000-4000-a000-000000000001',
    'mode' => 'dry_run',
    'repair_modes' => [],
    'filters' => ['merchant_id' => 3, 'campaign' => null, 'operation' => null, 'limit' => 100],
    'before' => ['issues' => 0, 'repairable' => 0, 'report_only' => 0],
    'repairs_applied' => 0,
    'after' => ['issues' => 0, 'repairable' => 0, 'report_only' => 0],
    'unexplained_drift_after' => 0,
    'completed_at' => gmdate('c'),
];
$receipt['checksum'] = hash('sha256', json_encode($receipt, JSON_UNESCAPED_SLASHES));
$stmt = $pdo->prepare(
    "INSERT INTO public_donations_reconciliation_receipts
     (receipt_id,actor_user_id,merchant_user_id,campaign_reference,operation_reference,execution_mode,repair_modes_json,
      issues_before,repairable_before,report_only_before,repairs_applied,issues_after,unexplained_drift_after,
      checksum,reason,receipt_json,report_json,created_at)
     VALUES (?,?,?,?,?,'dry_run',?,0,0,0,0,0,0,?,?,?, ?,NOW())"
);
$stmt->execute([
    $receipt['receipt_id'],99,3,null,null,json_encode([], JSON_THROW_ON_ERROR),$receipt['checksum'],
    'Fixture reconciliation',json_encode($receipt, JSON_THROW_ON_ERROR),json_encode(['issues'=>[]], JSON_THROW_ON_ERROR),
]);
$stored = $pdo->query('SELECT receipt_id,checksum,unexplained_drift_after FROM public_donations_reconciliation_receipts')->fetch();
$assert((string)$stored['receipt_id'] === $receipt['receipt_id'], 'Receipt identifier must persist.');
$assert(hash_equals($receipt['checksum'], (string)$stored['checksum']), 'Receipt checksum must persist exactly.');
$assert((int)$stored['unexplained_drift_after'] === 0, 'Clean dry-run receipt must preserve zero unexplained drift.');

$pdo->exec('UPDATE public_donations_operations_settings SET override_active=0 WHERE id=1');
$fallback = mg_public_donations_rollout_config(true);
$assert($fallback['source'] === 'environment' && $fallback['state'] === 'enabled', 'Disabling override must restore environment authority.');

putenv('MG_PUBLIC_DONATIONS_FEATURE_STATE');
putenv('MG_PUBLIC_DONATIONS_MERCHANT_IDS');
echo "Public Donations Operations Admin MySQL fixture passed.\n";
