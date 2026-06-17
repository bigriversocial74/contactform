<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');

try {
    $stmt = mg_db()->query('SELECT id, slug, name FROM roles ORDER BY id ASC');
    mg_ok(['roles' => $stmt->fetchAll()], 'Roles loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load roles.', 500);
}
