<?php
declare(strict_types=1);

require_once __DIR__ . '/_users.php';

mg_require_method('GET');
$user = mg_admin_users_require_user();
$actorId = (int)$user['id'];
mg_rate_limit('admin.users.read', 'user:' . $actorId, 180, 60);

try {
    $data = mg_admin_users_read(mg_db(), $_GET);
} catch (InvalidArgumentException $error) {
    mg_security_log('warning', 'admin.users.invalid_request', 'Invalid admin user directory request.', [
        'reason' => $error->getMessage(),
    ], $actorId);
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.users.read_failed', 'Admin user directory query failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to load users.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($data, 'Users loaded.');
