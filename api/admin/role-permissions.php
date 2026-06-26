<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

mg_require_method('POST');
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
mg_rate_limit('admin.role_permissions.write', 'user:' . $actorId, 80, 60);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();

try {
    if (!mg_admin_account_actor_has($actor, 'admin.roles.manage')) {
        mg_fail('Permission denied.', 403);
    }
    $roleSlug = strtolower(trim((string)($input['role'] ?? '')));
    $permissionSlug = strtolower(trim((string)($input['permission'] ?? '')));
    $operation = strtolower(trim((string)($input['operation'] ?? '')));
    $reason = mg_admin_account_reason($input['reason'] ?? null);

    if (!in_array($operation, ['add', 'remove'], true)) {
        throw new MgAdminAccountException('Invalid role permission operation.', 422);
    }
    if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $roleSlug) !== 1 || preg_match('/^[a-z0-9][a-z0-9._-]{0,159}$/', $permissionSlug) !== 1) {
        throw new MgAdminAccountException('Invalid role or permission.', 422);
    }
    if (in_array($roleSlug, ['admin', 'super_admin'], true) && !mg_admin_account_actor_is_super($actor)) {
        throw new MgAdminAccountException('Only a super administrator can modify elevated role permissions.', 403);
    }
    if ($roleSlug === 'super_admin' && $operation === 'remove') {
        throw new MgAdminAccountException('The super administrator role remains permission-complete by policy.', 409);
    }

    $pdo->beginTransaction();
    $role = mg_admin_account_role($pdo, $roleSlug);
    $stmt = $pdo->prepare('SELECT id, slug, name FROM permissions WHERE slug = ? LIMIT 1');
    $stmt->execute([$permissionSlug]);
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$permission) {
        throw new MgAdminAccountException('Permission not found.', 404);
    }

    if ($operation === 'add') {
        $write = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())');
        $write->execute([(int)$role['id'], (int)$permission['id']]);
        if ($write->rowCount() < 1) {
            throw new MgAdminAccountException('Permission is already assigned to this role.', 409);
        }
    } else {
        $write = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?');
        $write->execute([(int)$role['id'], (int)$permission['id']]);
        if ($write->rowCount() < 1) {
            throw new MgAdminAccountException('Permission is not assigned to this role.', 409);
        }
    }

    $metadata = [
        'role' => $roleSlug,
        'permission' => $permissionSlug,
        'operation' => $operation,
        'reason' => $reason,
    ];
    mg_audit('admin_role_permission_' . $operation, 'role', $metadata, $actorId);
    mg_event('admin.role_permission.' . $operation, $metadata + ['admin_user_id' => $actorId], $actorId);
    mg_security_log('info', 'admin.role_permission.completed', 'Admin role permission mutation completed.', $metadata, $actorId);
    $pdo->commit();
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.role_permission.failed', 'Admin role permission mutation failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to update role permission.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok(['role' => $roleSlug, 'permission' => $permissionSlug, 'operation' => $operation], 'Role permission updated.');
