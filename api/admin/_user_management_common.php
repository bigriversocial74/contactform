<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_detail.php';

final class MgAdminUserManagementException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_admin_management_text(mixed $value, int $maxLength = 255): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if (mb_strlen($text) > $maxLength) {
        throw new InvalidArgumentException('Administrative input is too long.');
    }
    return $text;
}

function mg_admin_management_reason(mixed $value): string
{
    $reason = mg_admin_management_text($value, 255);
    if (mb_strlen($reason) < 8) {
        throw new InvalidArgumentException('Provide a reason of at least 8 characters.');
    }
    return $reason;
}

function mg_admin_management_slug(mixed $value, string $label): string
{
    $slug = strtolower(mg_admin_management_text($value, 80));
    if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $slug) !== 1) {
        throw new InvalidArgumentException('Invalid ' . $label . '.');
    }
    return $slug;
}

function mg_admin_management_actor_has(array $actor, string $permission): bool
{
    $roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
    if (in_array('super_admin', $roles, true)) {
        return true;
    }
    $permissions = is_array($actor['permissions'] ?? null) ? $actor['permissions'] : [];
    return in_array($permission, $permissions, true);
}

function mg_admin_management_actor_is_super(array $actor): bool
{
    return in_array('super_admin', is_array($actor['roles'] ?? null) ? $actor['roles'] : [], true);
}

function mg_admin_management_target(PDO $pdo, int $userId, bool $lock = false): ?array
{
    $sql = 'SELECT u.id,u.email,u.display_name,u.full_name,u.status,
                   EXISTS(
                     SELECT 1 FROM user_roles ur
                     INNER JOIN roles r ON r.id=ur.role_id
                     WHERE ur.user_id=u.id AND r.slug="super_admin"
                   ) AS is_super_admin
            FROM users u WHERE u.id=? LIMIT 1';
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_admin_management_require_target(PDO $pdo, int $userId, bool $lock = false): array
{
    $target = mg_admin_management_target($pdo, $userId, $lock);
    if (!$target) {
        throw new MgAdminUserManagementException('User not found.', 404);
    }
    return $target;
}

function mg_admin_management_guard_target(array $actor, array $target, string $permission): void
{
    if (!mg_admin_management_actor_has($actor, $permission)) {
        throw new MgAdminUserManagementException('Permission denied.', 403);
    }
    if ((int)$actor['id'] === (int)$target['id']) {
        throw new MgAdminUserManagementException('You cannot use this control on your own account.', 409);
    }
    if ((bool)$target['is_super_admin'] && !mg_admin_management_actor_is_super($actor)) {
        throw new MgAdminUserManagementException('Only a super administrator can manage this account.', 403);
    }
}

function mg_admin_management_active_super_admin_count(PDO $pdo): int
{
    $stmt = $pdo->query(
        'SELECT COUNT(DISTINCT u.id)
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id=u.id
         INNER JOIN roles r ON r.id=ur.role_id
         WHERE r.slug="super_admin" AND u.status="active"'
    );
    return (int)$stmt->fetchColumn();
}
