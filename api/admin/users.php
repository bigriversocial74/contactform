<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
mg_require_permission('admin.users.view');

try {
    $stmt = mg_db()->query('SELECT id, email, full_name, display_name, status, email_verified_at, created_at FROM users ORDER BY id DESC LIMIT 100');
    mg_ok(['users' => $stmt->fetchAll()], 'Users loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load users.', 500);
}
