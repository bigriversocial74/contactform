<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

mg_require_method('POST');
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
mg_rate_limit('admin.user_management.write', 'user:' . $actorId, 90, 60);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();

try {
    $action = mg_admin_account_action($input['action'] ?? null);
    $permission = mg_admin_account_permission($action);
    if (!mg_admin_account_actor_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'action' => $action], $actorId);
        mg_security_log('warning', 'admin.user_management.denied', 'Admin account management permission denied.', [
            'permission' => $permission,
            'action' => $action,
        ], $actorId);
        mg_fail('Permission denied.', 403);
    }

    $targetUserId = mg_admin_user_detail_id($input['user_id'] ?? null);
    $reason = mg_admin_account_reason($input['reason'] ?? null);
    $pdo->beginTransaction();

    $result = match ($action) {
        'set_status' => mg_admin_account_set_status($pdo, $actor, $targetUserId, strtolower(trim((string)($input['status'] ?? '')))),
        'add_role' => mg_admin_account_change_role($pdo, $actor, $targetUserId, strtolower(trim((string)($input['role'] ?? ''))), true),
        'remove_role' => mg_admin_account_change_role($pdo, $actor, $targetUserId, strtolower(trim((string)($input['role'] ?? ''))), false),
        'set_model_status' => mg_admin_account_set_model_status(
            $pdo,
            $actor,
            $targetUserId,
            strtolower(trim((string)($input['model'] ?? ''))),
            strtolower(trim((string)($input['status'] ?? ''))),
            $reason
        ),
        'revoke_session' => mg_admin_account_revoke_session($pdo, $actor, $targetUserId, (int)($input['session_id'] ?? 0)),
        'revoke_sessions' => mg_admin_account_revoke_sessions($pdo, $actor, $targetUserId),
    };

    $pdo->commit();
    $metadata = [
        'target_user_id' => $targetUserId,
        'action' => $action,
        'reason' => $reason,
        'result' => $result,
    ];
    mg_audit('admin_user_' . $action, 'user', $metadata, $actorId);
    mg_event('admin.user.' . $action, $metadata + ['admin_user_id' => $actorId], $actorId);
    mg_security_log('info', 'admin.user_management.completed', 'Admin account management action completed.', [
        'target_user_id' => $targetUserId,
        'action' => $action,
    ], $actorId);

    $detail = mg_admin_account_context($pdo, $actor, $targetUserId);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('warning', 'admin.user_management.rejected', 'Admin account management action rejected.', [
        'reason' => $error->getMessage(),
    ], $actorId);
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.user_management.failed', 'Admin account management action failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to complete the account management action.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok(['result' => $result, 'user' => $detail], 'Account management action completed.');
