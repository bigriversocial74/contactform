<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

mg_require_method('GET');
$actor = mg_require_permission('admin.roles.manage');
$actorId = (int)$actor['id'];
mg_rate_limit('admin.roles.read', 'user:' . $actorId, 120, 60);

try {
    $pdo = mg_db();
    $roles = $pdo->query('SELECT id, slug, name, created_at FROM roles ORDER BY slug')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $permissions = $pdo->query('SELECT id, slug, name, created_at FROM permissions ORDER BY slug')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $assignedRows = $pdo->query(
        'SELECT r.slug AS role_slug, p.slug AS permission_slug
         FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.role_id
         INNER JOIN permissions p ON p.id = rp.permission_id
         ORDER BY r.slug, p.slug'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $assigned = [];
    foreach ($assignedRows as $row) {
        $assigned[(string)$row['role_slug']][] = (string)$row['permission_slug'];
    }

    $data = [
        'roles' => array_map(static fn(array $role): array => [
            'slug' => (string)$role['slug'],
            'name' => (string)$role['name'],
            'is_protected' => in_array((string)$role['slug'], ['admin', 'super_admin'], true),
            'created_at' => (string)$role['created_at'],
            'permissions' => array_values($assigned[(string)$role['slug']] ?? []),
        ], $roles),
        'permissions' => array_map(static fn(array $permission): array => [
            'slug' => (string)$permission['slug'],
            'name' => (string)$permission['name'],
            'group' => explode('.', (string)$permission['slug'])[0] ?: 'system',
            'created_at' => (string)$permission['created_at'],
        ], $permissions),
        'can_manage_elevated' => mg_admin_account_actor_is_super($actor),
    ];
} catch (Throwable $error) {
    mg_security_log('error', 'admin.roles.read_failed', 'Admin roles query failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to load roles and permissions.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($data, 'Roles loaded.');
