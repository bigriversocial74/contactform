<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management_common.php';

function mg_admin_management_models(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT um.code,um.name,um.description,um.is_system,um.is_assignable,um.requires_approval,um.default_status,
                uma.status,uma.requested_at,uma.enabled_at,uma.approved_at,uma.disabled_at,uma.rejected_at,uma.suspended_at,uma.revoked_at
         FROM user_models um
         LEFT JOIN user_model_assignments uma ON uma.user_model_id=um.id AND uma.user_id=?
         ORDER BY um.sort_order,um.code'
    );
    $stmt->execute([$userId]);

    return array_map(static fn(array $row): array => [
        'code' => (string)$row['code'],
        'name' => (string)$row['name'],
        'description' => $row['description'] !== null ? (string)$row['description'] : null,
        'is_system' => (bool)$row['is_system'],
        'is_assignable' => (bool)$row['is_assignable'],
        'requires_approval' => (bool)$row['requires_approval'],
        'default_status' => (string)$row['default_status'],
        'status' => $row['status'] !== null ? (string)$row['status'] : null,
        'requested_at' => $row['requested_at'] !== null ? (string)$row['requested_at'] : null,
        'enabled_at' => $row['enabled_at'] !== null ? (string)$row['enabled_at'] : null,
        'approved_at' => $row['approved_at'] !== null ? (string)$row['approved_at'] : null,
        'disabled_at' => $row['disabled_at'] !== null ? (string)$row['disabled_at'] : null,
        'rejected_at' => $row['rejected_at'] !== null ? (string)$row['rejected_at'] : null,
        'suspended_at' => $row['suspended_at'] !== null ? (string)$row['suspended_at'] : null,
        'revoked_at' => $row['revoked_at'] !== null ? (string)$row['revoked_at'] : null,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}
