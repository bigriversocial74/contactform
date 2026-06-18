<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_detail.php';

mg_require_method('GET');
$admin = mg_admin_users_require_user();
$actorId = (int)$admin['id'];
mg_rate_limit('admin.user_detail.read', 'user:' . $actorId, 240, 60);

try {
    $targetUserId = mg_admin_user_detail_id($_GET['user_id'] ?? null);
    $detail = mg_admin_user_detail_read(mg_db(), $targetUserId);
    if ($detail === null) {
        mg_fail('User not found.', 404);
    }
} catch (InvalidArgumentException $error) {
    mg_security_log('warning', 'admin.user_detail.invalid_request', 'Invalid admin user detail request.', [
        'reason' => $error->getMessage(),
    ], $actorId);
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.user_detail.read_failed', 'Admin user detail query failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to load user details.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok(['user' => $detail], 'User details loaded.');
