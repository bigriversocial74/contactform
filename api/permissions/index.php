<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');

try {
    $stmt = mg_db()->query('SELECT id, slug, name FROM permissions ORDER BY slug ASC');
    mg_ok(['permissions' => $stmt->fetchAll()], 'Permissions loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load permissions.', 500);
}
