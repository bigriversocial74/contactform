<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management_common.php';

function mg_admin_management_role_action(PDO $pdo, array $actor, int $userId, string $roleSlug, string $operation, string $reason): array
{
    if (!in_array($operation, ['assign', 'remove'], true)) {
        throw new InvalidArgumentException('Invalid role operation.');
    }

    $target = mg_admin_management_require_target($pdo, $userId, true);
    mg_admin_management_guard_target($actor, $target, 'admin.roles.manage');

    if (in_array($roleSlug, ['admin', 'super_admin'], true)) {
        throw new MgAdminUserManagementException('Privileged roles require the manual owner workflow.', 403);
    }

    $stmt = $pdo->prepare('SELECT id,slug,name FROM roles WHERE slug=? LIMIT 1');
    $stmt->execute([$roleSlug]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        throw new MgAdminUserManagementException('Role not found.', 404);
    }

    if ($operation === 'assign') {
        $insert = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id,created_at) VALUES (?,?,NOW())');
        $insert->execute([$userId, (int)$role['id']]);
        if ($insert->rowCount() === 0) {
            throw new MgAdminUserManagementException('That role is already assigned.');
        }
        return ['action' => 'role_assigned', 'role' => $roleSlug, 'reason' => $reason];
    }

    $required = $pdo->prepare(
        'SELECT um.name
         FROM user_model_assignments uma
         INNER JOIN model_default_roles mdr ON mdr.user_model_id=uma.user_model_id AND mdr.is_required=1
         INNER JOIN user_models um ON um.id=uma.user_model_id
         WHERE uma.user_id=? AND uma.status="active" AND mdr.role_id=?
         LIMIT 1'
    );
    $required->execute([$userId, (int)$role['id']]);
    $requiredBy = $required->fetchColumn();
    if ($requiredBy) {
        throw new MgAdminUserManagementException('Disable the ' . $requiredBy . ' model before removing this required role.');
    }

    $delete = $pdo->prepare('DELETE FROM user_roles WHERE user_id=? AND role_id=?');
    $delete->execute([$userId, (int)$role['id']]);
    if ($delete->rowCount() === 0) {
        throw new MgAdminUserManagementException('That role is not assigned.');
    }

    return ['action' => 'role_removed', 'role' => $roleSlug, 'reason' => $reason];
}
