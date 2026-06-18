<?php
declare(strict_types=1);

require_once __DIR__ . '/_users.php';

function mg_admin_user_detail_id(mixed $value): int
{
    $raw = trim((string)$value);
    if ($raw === '' || preg_match('/^[1-9][0-9]{0,19}$/', $raw) !== 1) {
        throw new InvalidArgumentException('Invalid user identifier.');
    }

    $userId = filter_var($raw, FILTER_VALIDATE_INT);
    if ($userId === false || $userId < 1) {
        throw new InvalidArgumentException('Invalid user identifier.');
    }

    return (int)$userId;
}

function mg_admin_user_detail_roles(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.slug, r.name
         FROM roles r
         INNER JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = ?
         ORDER BY r.slug'
    );
    $stmt->execute([$userId]);

    return array_map(static fn(array $role): array => [
        'slug' => (string)$role['slug'],
        'name' => (string)$role['name'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_user_detail_models(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT um.code, um.name, um.requires_approval, um.is_system, um.is_assignable,
                uma.status, uma.reason, uma.requested_at, uma.enabled_at,
                uma.approved_at, uma.disabled_at, uma.rejected_at,
                uma.suspended_at, uma.revoked_at
         FROM user_model_assignments uma
         INNER JOIN user_models um ON um.id = uma.user_model_id
         WHERE uma.user_id = ?
         ORDER BY um.sort_order, um.code'
    );
    $stmt->execute([$userId]);

    return array_map(static fn(array $model): array => [
        'code' => (string)$model['code'],
        'name' => (string)$model['name'],
        'requires_approval' => (bool)$model['requires_approval'],
        'is_system' => (bool)$model['is_system'],
        'is_assignable' => (bool)$model['is_assignable'],
        'status' => (string)$model['status'],
        'reason' => $model['reason'] !== null ? (string)$model['reason'] : null,
        'requested_at' => $model['requested_at'] !== null ? (string)$model['requested_at'] : null,
        'enabled_at' => $model['enabled_at'] !== null ? (string)$model['enabled_at'] : null,
        'approved_at' => $model['approved_at'] !== null ? (string)$model['approved_at'] : null,
        'disabled_at' => $model['disabled_at'] !== null ? (string)$model['disabled_at'] : null,
        'rejected_at' => $model['rejected_at'] !== null ? (string)$model['rejected_at'] : null,
        'suspended_at' => $model['suspended_at'] !== null ? (string)$model['suspended_at'] : null,
        'revoked_at' => $model['revoked_at'] !== null ? (string)$model['revoked_at'] : null,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_user_detail_profile(array $row): ?array
{
    if ($row['profile_public_id'] === null) {
        return null;
    }

    return [
        'id' => (string)$row['profile_public_id'],
        'slug' => (string)$row['profile_slug'],
        'display_name' => (string)$row['profile_display_name'],
        'profile_type' => (string)$row['profile_type'],
        'visibility' => (string)$row['profile_visibility'],
        'status' => (string)$row['profile_status'],
        'completion_score' => (int)$row['profile_completion_score'],
        'published_at' => $row['profile_published_at'] !== null ? (string)$row['profile_published_at'] : null,
        'updated_at' => $row['profile_updated_at'] !== null ? (string)$row['profile_updated_at'] : null,
        'url' => '/profile.php?slug=' . rawurlencode((string)$row['profile_slug']),
    ];
}

function mg_admin_user_detail_read(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
           u.id, u.email, u.full_name, u.display_name, u.status,
           u.email_verified_at, u.created_at, u.updated_at,
           pp.public_id AS profile_public_id,
           pp.slug AS profile_slug,
           pp.display_name AS profile_display_name,
           pp.profile_type,
           pp.visibility AS profile_visibility,
           pp.status AS profile_status,
           pp.completion_score AS profile_completion_score,
           pp.published_at AS profile_published_at,
           pp.updated_at AS profile_updated_at
         FROM users u
         LEFT JOIN public_profiles pp ON pp.user_id = u.id
         WHERE u.id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'full_name' => (string)$row['full_name'],
        'display_name' => (string)($row['display_name'] ?? $row['full_name']),
        'status' => (string)$row['status'],
        'email_verified_at' => $row['email_verified_at'] !== null ? (string)$row['email_verified_at'] : null,
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
        'roles' => mg_admin_user_detail_roles($pdo, $userId),
        'models' => mg_admin_user_detail_models($pdo, $userId),
        'profile' => mg_admin_user_detail_profile($row),
    ];
}
