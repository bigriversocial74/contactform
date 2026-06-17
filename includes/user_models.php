<?php
/**
 * User model helpers.
 *
 * User models are enableable operating modes attached to one login identity.
 * They are not separate account types and they do not replace roles,
 * permissions, or object-level authorization.
 */
declare(strict_types=1);

function mg_model_public_id(string $prefix = 'uma'): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

function mg_get_user_model_by_code(string $code): ?array
{
    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT * FROM user_models WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $model = $stmt->fetch();
    return $model ?: null;
}

function mg_user_model_assignments(int $userId): array
{
    $pdo = mg_db();
    $stmt = $pdo->prepare(
        'SELECT um.code, um.name, um.requires_approval, uma.status, uma.requested_at, uma.enabled_at, uma.approved_at, uma.disabled_at
         FROM user_model_assignments uma
         INNER JOIN user_models um ON um.id = uma.user_model_id
         WHERE uma.user_id = ?
         ORDER BY um.sort_order, um.code'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll() ?: [];
}

function mg_user_active_model_codes(int $userId): array
{
    $assignments = mg_user_model_assignments($userId);
    $codes = [];
    foreach ($assignments as $assignment) {
        if (($assignment['status'] ?? '') === 'active') {
            $codes[] = (string) $assignment['code'];
        }
    }
    return array_values(array_unique($codes));
}

function mg_user_has_active_model(int $userId, string $modelCode): bool
{
    $pdo = mg_db();
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM user_model_assignments uma
         INNER JOIN user_models um ON um.id = uma.user_model_id
         WHERE uma.user_id = ? AND um.code = ? AND uma.status = ?
         LIMIT 1'
    );
    $stmt->execute([$userId, $modelCode, 'active']);
    return (bool) $stmt->fetchColumn();
}

function mg_record_user_model_event(
    int $userId,
    int $modelId,
    ?int $assignmentId,
    string $eventType,
    ?string $fromStatus,
    ?string $toStatus,
    ?int $actorUserId = null,
    ?string $reason = null,
    array $metadata = []
): void {
    $pdo = mg_db();
    $stmt = $pdo->prepare(
        'INSERT INTO user_model_events
         (public_id, user_id, user_model_id, assignment_id, event_type, from_status, to_status, actor_user_id, reason, metadata_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        mg_model_public_id('ume'),
        $userId,
        $modelId,
        $assignmentId,
        $eventType,
        $fromStatus,
        $toStatus,
        $actorUserId,
        $reason,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
}

function mg_assign_user_model(
    int $userId,
    string $modelCode,
    string $status = 'active',
    ?int $actorUserId = null,
    string $reason = 'system assignment',
    array $metadata = []
): bool {
    $allowed = ['pending', 'active', 'disabled', 'suspended', 'revoked', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid user model status.');
    }

    $pdo = mg_db();
    $model = mg_get_user_model_by_code($modelCode);
    if (!$model) {
        mg_security_log('warning', 'user_model.missing', 'User model code does not exist.', ['model' => $modelCode], $userId);
        return false;
    }

    $existingStmt = $pdo->prepare('SELECT id, status FROM user_model_assignments WHERE user_id = ? AND user_model_id = ? LIMIT 1');
    $existingStmt->execute([$userId, (int) $model['id']]);
    $existing = $existingStmt->fetch();

    if ($existing) {
        if (($existing['status'] ?? '') === $status) {
            return false;
        }

        $fromStatus = (string) $existing['status'];
        $updates = [
            'status = ?',
            'reason = ?',
            'metadata_json = ?',
            'updated_at = NOW()',
        ];
        $params = [
            $status,
            $reason,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        if ($status === 'active') {
            $updates[] = 'enabled_at = COALESCE(enabled_at, NOW())';
            $updates[] = 'approved_at = COALESCE(approved_at, NOW())';
            $updates[] = 'approved_by_user_id = COALESCE(approved_by_user_id, ?)';
            $params[] = $actorUserId;
        } elseif ($status === 'disabled') {
            $updates[] = 'disabled_at = NOW()';
            $updates[] = 'disabled_by_user_id = ?';
            $params[] = $actorUserId;
        }

        $params[] = (int) $existing['id'];
        $update = $pdo->prepare('UPDATE user_model_assignments SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $update->execute($params);

        mg_record_user_model_event(
            $userId,
            (int) $model['id'],
            (int) $existing['id'],
            'user_model.' . $status,
            $fromStatus,
            $status,
            $actorUserId,
            $reason,
            $metadata
        );
        return true;
    }

    $requestedAt = $status === 'pending' ? date('Y-m-d H:i:s') : null;
    $enabledAt = $status === 'active' ? date('Y-m-d H:i:s') : null;
    $approvedAt = $status === 'active' ? date('Y-m-d H:i:s') : null;

    $insert = $pdo->prepare(
        'INSERT INTO user_model_assignments
         (public_id, user_id, user_model_id, status, requested_at, enabled_at, approved_at, approved_by_user_id, reason, metadata_json, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $insert->execute([
        mg_model_public_id('uma'),
        $userId,
        (int) $model['id'],
        $status,
        $requestedAt,
        $enabledAt,
        $approvedAt,
        $actorUserId,
        $reason,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $assignmentId = (int) $pdo->lastInsertId();
    mg_record_user_model_event(
        $userId,
        (int) $model['id'],
        $assignmentId,
        $status === 'active' ? 'user_model.enabled' : 'user_model.' . $status,
        null,
        $status,
        $actorUserId,
        $reason,
        $metadata
    );

    return true;
}

function mg_assign_default_customer_model(int $userId): bool
{
    return mg_assign_user_model(
        $userId,
        'customer',
        'active',
        null,
        'registration default customer model',
        ['source' => 'registration']
    );
}
