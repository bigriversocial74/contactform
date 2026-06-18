<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management_common.php';

function mg_admin_management_model_action(PDO $pdo, array $actor, int $userId, string $modelCode, string $status, string $reason): array
{
    $allowed = ['active', 'disabled', 'suspended', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid user-model status.');
    }

    $target = mg_admin_management_require_target($pdo, $userId, true);
    mg_admin_management_guard_target($actor, $target, 'admin.user_models.manage');

    $stmt = $pdo->prepare(
        'SELECT uma.status,um.id AS model_id,um.is_system,um.is_assignable
         FROM user_model_assignments uma
         INNER JOIN user_models um ON um.id=uma.user_model_id
         WHERE uma.user_id=? AND um.code=?
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$userId, $modelCode]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assignment) {
        throw new MgAdminUserManagementException('No user-model request or assignment exists.', 404);
    }
    if ((bool)$assignment['is_system'] || !(bool)$assignment['is_assignable']) {
        throw new MgAdminUserManagementException('System user models require the manual owner workflow.', 403);
    }

    $previous = (string)$assignment['status'];
    if ($previous === $status) {
        throw new MgAdminUserManagementException('The user model already has that status.');
    }

    $transitions = [
        'pending' => ['active', 'rejected'],
        'active' => ['disabled', 'suspended'],
        'disabled' => ['active'],
        'suspended' => ['active', 'disabled'],
        'rejected' => ['active'],
    ];
    if (!in_array($status, $transitions[$previous] ?? [], true)) {
        throw new MgAdminUserManagementException('That user-model transition is not allowed.');
    }

    if (!mg_assign_user_model($userId, $modelCode, $status, (int)$actor['id'], $reason, ['source' => 'admin_account_management'])) {
        throw new MgAdminUserManagementException('The user-model assignment was not changed.');
    }

    if ($status === 'suspended' || $status === 'rejected') {
        $column = $status === 'suspended' ? 'suspended_at' : 'rejected_at';
        $timestamp = $pdo->prepare('UPDATE user_model_assignments SET ' . $column . '=NOW() WHERE user_id=? AND user_model_id=?');
        $timestamp->execute([$userId, (int)$assignment['model_id']]);
    }

    if ($status === 'active') {
        $required = $pdo->prepare(
            'INSERT IGNORE INTO user_roles (user_id,role_id,created_at)
             SELECT ?,mdr.role_id,NOW() FROM model_default_roles mdr
             WHERE mdr.user_model_id=? AND mdr.is_required=1'
        );
        $required->execute([$userId, (int)$assignment['model_id']]);
    }

    return ['action' => 'model_status', 'model' => $modelCode, 'from' => $previous, 'to' => $status, 'reason' => $reason];
}
