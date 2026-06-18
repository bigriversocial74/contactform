<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_detail.php';

final class MgAdminAccountException extends RuntimeException
{
    private int $httpStatus;

    public function __construct(string $message, int $httpStatus = 422)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

function mg_admin_account_reason(mixed $value): string
{
    $reason = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    $length = mb_strlen($reason);
    if ($length < 8 || $length > 240) {
        throw new MgAdminAccountException('Provide an action reason between 8 and 240 characters.', 422);
    }
    return $reason;
}

function mg_admin_account_action(mixed $value): string
{
    $action = strtolower(trim((string)$value));
    $allowed = ['set_status', 'add_role', 'remove_role', 'set_model_status', 'revoke_session', 'revoke_sessions'];
    if (!in_array($action, $allowed, true)) {
        throw new MgAdminAccountException('Invalid account management action.', 422);
    }
    return $action;
}

function mg_admin_account_permission(string $action): string
{
    return match ($action) {
        'set_status' => 'admin.users.manage',
        'add_role', 'remove_role' => 'admin.roles.manage',
        'set_model_status' => 'admin.user_models.manage',
        'revoke_session', 'revoke_sessions' => 'admin.sessions.revoke',
        default => 'admin.users.manage',
    };
}

function mg_admin_account_actor_is_super(array $actor): bool
{
    return in_array('super_admin', is_array($actor['roles'] ?? null) ? $actor['roles'] : [], true);
}

function mg_admin_account_actor_has(array $actor, string $permission): bool
{
    return mg_api_user_has_permission($actor, $permission);
}

function mg_admin_account_target(PDO $pdo, int $userId, bool $lock = false): array
{
    $sql = 'SELECT id, email, display_name, full_name, status FROM users WHERE id = ? LIMIT 1';
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        throw new MgAdminAccountException('User not found.', 404);
    }

    $roles = mg_admin_user_detail_roles($pdo, $userId);
    $target['roles'] = array_column($roles, 'slug');
    return $target;
}

function mg_admin_account_target_is_elevated(array $target): bool
{
    $roles = is_array($target['roles'] ?? null) ? $target['roles'] : [];
    return in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
}

function mg_admin_account_assert_target_access(array $actor, array $target, bool $allowSelf = false): void
{
    $isSelf = (int)$actor['id'] === (int)$target['id'];
    if ($isSelf && !$allowSelf) {
        throw new MgAdminAccountException('You cannot perform this action on your own account.', 409);
    }
    if (mg_admin_account_target_is_elevated($target) && !mg_admin_account_actor_is_super($actor)) {
        throw new MgAdminAccountException('Only a super administrator can manage an administrative account.', 403);
    }
}

function mg_admin_account_active_super_count(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT COUNT(DISTINCT u.id)
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.id
         INNER JOIN roles r ON r.id = ur.role_id AND r.slug = "super_admin"
         WHERE u.status = "active"'
    );
    return (int)$stmt->fetchColumn();
}

function mg_admin_account_set_status(PDO $pdo, array $actor, int $targetUserId, string $status): array
{
    if (!in_array($status, ['active', 'pending', 'disabled'], true)) {
        throw new MgAdminAccountException('Invalid account status.', 422);
    }

    $target = mg_admin_account_target($pdo, $targetUserId, true);
    mg_admin_account_assert_target_access($actor, $target);
    $fromStatus = (string)$target['status'];
    if ($fromStatus === $status) {
        throw new MgAdminAccountException('The account already has that status.', 409);
    }

    if (in_array('super_admin', $target['roles'], true) && $status !== 'active' && mg_admin_account_active_super_count($pdo) <= 1) {
        throw new MgAdminAccountException('The last active super administrator cannot be deactivated.', 409);
    }

    $stmt = $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $targetUserId]);

    $revoked = 0;
    if ($status !== 'active') {
        $sessions = $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL');
        $sessions->execute([$targetUserId]);
        $revoked = $sessions->rowCount();
    }

    return ['from_status' => $fromStatus, 'to_status' => $status, 'sessions_revoked' => $revoked];
}

function mg_admin_account_role(PDO $pdo, string $slug): array
{
    if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $slug) !== 1) {
        throw new MgAdminAccountException('Invalid role.', 422);
    }
    $stmt = $pdo->prepare('SELECT id, slug, name FROM roles WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        throw new MgAdminAccountException('Role not found.', 404);
    }
    return $role;
}

function mg_admin_account_change_role(PDO $pdo, array $actor, int $targetUserId, string $roleSlug, bool $add): array
{
    $target = mg_admin_account_target($pdo, $targetUserId, true);
    mg_admin_account_assert_target_access($actor, $target);
    $role = mg_admin_account_role($pdo, $roleSlug);
    $super = mg_admin_account_actor_is_super($actor);

    if (!$super && !in_array($roleSlug, ['customer', 'merchant'], true)) {
        throw new MgAdminAccountException('Only a super administrator can manage elevated roles.', 403);
    }
    if (in_array($roleSlug, ['admin', 'super_admin'], true) && !$super) {
        throw new MgAdminAccountException('Only a super administrator can manage elevated roles.', 403);
    }
    if (!$add && $roleSlug === 'super_admin') {
        if ((int)$actor['id'] === $targetUserId) {
            throw new MgAdminAccountException('You cannot remove your own super administrator role.', 409);
        }
        if (mg_admin_account_active_super_count($pdo) <= 1) {
            throw new MgAdminAccountException('The last active super administrator role cannot be removed.', 409);
        }
    }

    if ($add) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$targetUserId, (int)$role['id']]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?');
        $stmt->execute([$targetUserId, (int)$role['id']]);
    }

    if ($stmt->rowCount() < 1) {
        throw new MgAdminAccountException($add ? 'The role is already assigned.' : 'The role is not assigned.', 409);
    }

    return ['role' => $roleSlug, 'operation' => $add ? 'added' : 'removed'];
}

function mg_admin_account_model(PDO $pdo, string $code): array
{
    if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $code) !== 1) {
        throw new MgAdminAccountException('Invalid user model.', 422);
    }
    $stmt = $pdo->prepare('SELECT id, code, name, is_system, is_assignable FROM user_models WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $model = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$model) {
        throw new MgAdminAccountException('User model not found.', 404);
    }
    return $model;
}

function mg_admin_account_set_model_status(PDO $pdo, array $actor, int $targetUserId, string $code, string $status, string $reason): array
{
    $allowed = ['pending', 'active', 'disabled', 'suspended', 'revoked', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        throw new MgAdminAccountException('Invalid user model status.', 422);
    }

    $target = mg_admin_account_target($pdo, $targetUserId, true);
    mg_admin_account_assert_target_access($actor, $target);
    $model = mg_admin_account_model($pdo, $code);
    if (!mg_admin_account_actor_is_super($actor) && ((int)$model['is_system'] === 1 || (int)$model['is_assignable'] !== 1)) {
        throw new MgAdminAccountException('Only a super administrator can manage this system model.', 403);
    }

    $changed = mg_assign_user_model($targetUserId, $code, $status, (int)$actor['id'], $reason, [
        'source' => 'admin_account_management',
    ]);
    if (!$changed) {
        throw new MgAdminAccountException('The user model already has that status.', 409);
    }

    $timestampColumns = [
        'pending' => 'requested_at',
        'active' => 'enabled_at',
        'disabled' => 'disabled_at',
        'suspended' => 'suspended_at',
        'revoked' => 'revoked_at',
        'rejected' => 'rejected_at',
    ];
    $column = $timestampColumns[$status];
    $sql = 'UPDATE user_model_assignments SET ' . $column . ' = COALESCE(' . $column . ', NOW())';
    $params = [];
    if ($status === 'active') {
        $sql .= ', approved_at = COALESCE(approved_at, NOW()), approved_by_user_id = COALESCE(approved_by_user_id, ?)';
        $params[] = (int)$actor['id'];
    } elseif ($status === 'disabled') {
        $sql .= ', disabled_by_user_id = ?';
        $params[] = (int)$actor['id'];
    }
    $sql .= ' WHERE user_id = ? AND user_model_id = ?';
    $params[] = $targetUserId;
    $params[] = (int)$model['id'];
    $pdo->prepare($sql)->execute($params);

    return ['model' => $code, 'status' => $status];
}

function mg_admin_account_revoke_session(PDO $pdo, array $actor, int $targetUserId, int $sessionId): array
{
    if ($sessionId < 1) {
        throw new MgAdminAccountException('Invalid session identifier.', 422);
    }
    $target = mg_admin_account_target($pdo, $targetUserId, true);
    mg_admin_account_assert_target_access($actor, $target);
    $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE id = ? AND user_id = ? AND revoked_at IS NULL');
    $stmt->execute([$sessionId, $targetUserId]);
    if ($stmt->rowCount() < 1) {
        throw new MgAdminAccountException('The session was not found or is already revoked.', 409);
    }
    return ['session_id' => $sessionId, 'revoked' => 1];
}

function mg_admin_account_revoke_sessions(PDO $pdo, array $actor, int $targetUserId): array
{
    $target = mg_admin_account_target($pdo, $targetUserId, true);
    mg_admin_account_assert_target_access($actor, $target);
    $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL');
    $stmt->execute([$targetUserId]);
    return ['revoked' => $stmt->rowCount()];
}

function mg_admin_account_capabilities(array $actor, array $target): array
{
    $super = mg_admin_account_actor_is_super($actor);
    $self = (int)$actor['id'] === (int)$target['id'];
    $elevated = mg_admin_account_target_is_elevated($target);
    $targetAllowed = $super || !$elevated;

    return [
        'is_self' => $self,
        'actor_is_super_admin' => $super,
        'target_is_elevated' => $elevated,
        'manage_status' => mg_admin_account_actor_has($actor, 'admin.users.manage') && !$self && $targetAllowed,
        'manage_roles' => mg_admin_account_actor_has($actor, 'admin.roles.manage') && !$self && $targetAllowed,
        'manage_models' => mg_admin_account_actor_has($actor, 'admin.user_models.manage') && !$self && $targetAllowed,
        'view_sessions' => mg_admin_account_actor_has($actor, 'admin.sessions.view') && $targetAllowed,
        'revoke_sessions' => mg_admin_account_actor_has($actor, 'admin.sessions.revoke') && !$self && $targetAllowed,
    ];
}

function mg_admin_account_available_roles(PDO $pdo, array $actor): array
{
    if (mg_admin_account_actor_is_super($actor)) {
        $stmt = $pdo->query('SELECT slug, name FROM roles ORDER BY slug');
    } else {
        $stmt = $pdo->query('SELECT slug, name FROM roles WHERE slug IN ("customer", "merchant") ORDER BY slug');
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_admin_account_available_models(PDO $pdo, array $actor): array
{
    $sql = 'SELECT code, name, description, is_system, is_assignable, requires_approval FROM user_models';
    if (!mg_admin_account_actor_is_super($actor)) {
        $sql .= ' WHERE is_system = 0 AND is_assignable = 1';
    }
    $sql .= ' ORDER BY sort_order, code';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_admin_account_sessions(PDO $pdo, int $targetUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, ip_address, user_agent, last_seen_at, expires_at, revoked_at, created_at
         FROM user_sessions
         WHERE user_id = ?
         ORDER BY last_seen_at DESC, id DESC
         LIMIT 25'
    );
    $stmt->execute([$targetUserId]);
    return array_map(static fn(array $session): array => [
        'id' => (int)$session['id'],
        'ip_address' => $session['ip_address'] !== null ? (string)$session['ip_address'] : null,
        'user_agent' => $session['user_agent'] !== null ? (string)$session['user_agent'] : null,
        'last_seen_at' => $session['last_seen_at'] !== null ? (string)$session['last_seen_at'] : null,
        'expires_at' => $session['expires_at'] !== null ? (string)$session['expires_at'] : null,
        'revoked_at' => $session['revoked_at'] !== null ? (string)$session['revoked_at'] : null,
        'created_at' => (string)$session['created_at'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_account_context(PDO $pdo, array $actor, int $targetUserId): ?array
{
    $detail = mg_admin_user_detail_read($pdo, $targetUserId);
    if ($detail === null) {
        return null;
    }

    $target = $detail;
    $target['roles'] = array_column($detail['roles'], 'slug');
    $capabilities = mg_admin_account_capabilities($actor, $target);
    $detail['management'] = [
        'capabilities' => $capabilities,
        'available_roles' => $capabilities['manage_roles'] ? mg_admin_account_available_roles($pdo, $actor) : [],
        'available_models' => $capabilities['manage_models'] ? mg_admin_account_available_models($pdo, $actor) : [],
        'model_statuses' => ['pending', 'active', 'disabled', 'suspended', 'revoked', 'rejected'],
        'sessions' => $capabilities['view_sessions'] ? mg_admin_account_sessions($pdo, $targetUserId) : [],
    ];
    return $detail;
}
