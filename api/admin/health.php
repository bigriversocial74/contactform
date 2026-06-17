<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/security.php';

mg_require_method('GET');
$user = mg_require_permission('admin.health.view');

$dbOk = false;
$tables = [];

try {
    $stmt = mg_db()->query('SELECT 1 AS ok');
    $dbOk = (bool) $stmt->fetchColumn();

    $tableStmt = mg_db()->query("SHOW TABLES LIKE 'users'");
    $tables['users'] = (bool) $tableStmt->fetchColumn();
    $tableStmt = mg_db()->query("SHOW TABLES LIKE 'rate_limits'");
    $tables['rate_limits'] = (bool) $tableStmt->fetchColumn();
    $tableStmt = mg_db()->query("SHOW TABLES LIKE 'user_sessions'");
    $tables['user_sessions'] = (bool) $tableStmt->fetchColumn();
    $tableStmt = mg_db()->query("SHOW TABLES LIKE 'security_logs'");
    $tables['security_logs'] = (bool) $tableStmt->fetchColumn();
} catch (Throwable $e) {
    mg_security_log('error', 'admin.health.failed', 'Admin health check failed.', ['exception' => $e->getMessage()], (int) $user['id']);
}

mg_ok([
    'service' => 'microgifter',
    'database' => ['ok' => $dbOk],
    'tables' => $tables,
    'php' => PHP_VERSION,
    'request_id' => mg_request_id(),
    'timestamp' => gmdate('c'),
], $dbOk ? 'Admin health check passed.' : 'Admin health check failed.', $dbOk ? 200 : 503);
