<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management_context.php';
require_once __DIR__ . '/_user_management_status.php';
require_once __DIR__ . '/_user_management_role_actions.php';
require_once __DIR__ . '/_user_management_model_actions.php';

mg_require_method('POST');
$actor = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $targetUserId = mg_admin_user_detail_id($input['user_id'] ?? null);
    $action = mg_admin_management_slug($input['action'] ?? '', 'management action');
    $reason = mg_admin_management_reason($input['reason'] ?? '');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
}

mg_rate_limit('admin.user_management', 'user:' . (int)$actor['id'], 60, 300);
mg_rate_limit('admin.user_management.target', 'user:' . $targetUserId, 120, 3600);

$pdo = mg_db();
$pdo->beginTransaction();
try {
    $result = match ($action) {
        'suspend_user', 'reactivate_user' => mg_admin_management_account_action($pdo, $actor, $targetUserId, $action, $reason),
        'assign_role', 'remove_role' => mg_admin_management_role_action(
            $pdo,
            $actor,
            $targetUserId,
            mg_admin_management_slug($input['role'] ?? '', 'role'),
            $action === 'assign_role' ? 'assign' : 'remove',
            $reason
        ),
        'set_model_status' => mg_admin_management_model_action(
            $pdo,
            $actor,
            $targetUserId,
            mg_admin_management_slug($input['model'] ?? '', 'user model'),
            mg_admin_management_slug($input['status'] ?? '', 'user-model status'),
            $reason
        ),
        default => throw new InvalidArgumentException('Invalid management action.'),
    };

    $pdo->commit();

    mg_audit('admin.user_management.' . $action, 'user', [
        'target_user_id' => $targetUserId,
        'result' => $result,
    ], (int)$actor['id']);
    mg_event('admin.user_management.' . $action, [
        'target_user_id' => $targetUserId,
    ], (int)$actor['id']);
    mg_security_log('info', 'admin.user_management.completed', 'Administrative account action completed.', [
        'action' => $action,
        'target_user_id' => $targetUserId,
    ], (int)$actor['id']);

    $detail = mg_admin_user_detail_read($pdo, $targetUserId);
    mg_ok([
        'result' => $result,
        'user' => $detail,
        'management' => mg_admin_management_context($pdo, $actor, $targetUserId),
    ], 'Account action completed.');
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (MgAdminUserManagementException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('warning', 'admin.user_management.rejected', 'Administrative account action rejected.', [
        'action' => $action,
        'target_user_id' => $targetUserId,
        'reason' => $error->getMessage(),
    ], (int)$actor['id']);
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'admin.user_management.failed', 'Administrative account action failed.', [
        'action' => $action,
        'target_user_id' => $targetUserId,
        'exception_class' => $error::class,
    ], (int)$actor['id']);
    mg_fail('Unable to complete the account action.', 500);
}
