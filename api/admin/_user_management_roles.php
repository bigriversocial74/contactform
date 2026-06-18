<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management_common.php';

function mg_admin_management_roles(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT r.slug,r.name,
                EXISTS(SELECT 1 FROM user_roles ur WHERE ur.user_id=? AND ur.role_id=r.id) AS assigned
         FROM roles r ORDER BY r.slug'
    );
    $stmt->execute([$userId]);

    return array_map(static fn(array $row): array => [
        'slug' => (string)$row['slug'],
        'name' => (string)$row['name'],
        'assigned' => (bool)$row['assigned'],
        'privileged' => in_array((string)$row['slug'], ['admin','super_admin'], true),
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}
