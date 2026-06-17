<?php
declare(strict_types=1);

function mg_requestable_user_models(): array
{
    return ['creator', 'merchant', 'marketing_affiliate'];
}

function mg_admin_assignable_user_models(): array
{
    return ['moderator', 'vendor_manager', 'trader', 'admin'];
}

function mg_all_supported_context_models(): array
{
    return ['customer', 'creator', 'merchant', 'moderator', 'vendor_manager', 'marketing_affiliate', 'trader', 'admin', 'super_admin'];
}

function mg_list_user_models_with_user_state(?int $userId = null): array
{
    $pdo = mg_db();
    $stmt = $pdo->query(
        'SELECT id, code, name, description, is_system, is_assignable, requires_approval, default_status, sort_order
         FROM user_models
         ORDER BY sort_order, code'
    );
    $models = $stmt->fetchAll() ?: [];

    $stateByCode = [];
    if ($userId) {
        foreach (mg_user_model_assignments($userId) as $assignment) {
            $stateByCode[(string) $assignment['code']] = $assignment;
        }
    }

    return array_map(static function (array $model) use ($stateByCode): array {
        $code = (string) $model['code'];
        return [
            'code' => $code,
            'name' => (string) $model['name'],
            'description' => $model['description'] ?? null,
            'is_system' => (bool) $model['is_system'],
            'is_assignable' => (bool) $model['is_assignable'],
            'requires_approval' => (bool) $model['requires_approval'],
            'default_status' => (string) $model['default_status'],
            'status' => $stateByCode[$code]['status'] ?? null,
            'requested_at' => $stateByCode[$code]['requested_at'] ?? null,
            'enabled_at' => $stateByCode[$code]['enabled_at'] ?? null,
            'approved_at' => $stateByCode[$code]['approved_at'] ?? null,
        ];
    }, $models);
}

function mg_create_profile_for_model(int $userId, string $modelCode): void
{
    $pdo = mg_db();
    $publicId = mg_model_public_id('profile');

    if ($modelCode === 'creator') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO creator_profiles (public_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$publicId, $userId, 'draft']);
        return;
    }

    if ($modelCode === 'merchant') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO merchant_profiles (public_id, user_id, onboarding_status, verification_status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$publicId, $userId, 'draft', 'unverified']);
        return;
    }

    if ($modelCode === 'moderator') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO moderator_profiles (public_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$publicId, $userId, 'active']);
        return;
    }

    if ($modelCode === 'vendor_manager') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO vendor_manager_profiles (public_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$publicId, $userId, 'active']);
        return;
    }

    if ($modelCode === 'marketing_affiliate') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO marketing_affiliate_profiles (public_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$publicId, $userId, 'active']);
        return;
    }

    if ($modelCode === 'trader') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO trader_profiles (public_id, user_id, status, risk_status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$publicId, $userId, 'pending', 'pending_review']);
    }
}

function mg_apply_default_roles_for_model(int $userId, string $modelCode): void
{
    $pdo = mg_db();
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO user_roles (user_id, role_id, created_at)
         SELECT ?, mdr.role_id, NOW()
         FROM model_default_roles mdr
         INNER JOIN user_models um ON um.id = mdr.user_model_id
         WHERE um.code = ?'
    );
    $stmt->execute([$userId, $modelCode]);
}

function mg_request_user_model(int $userId, string $modelCode): array
{
    if (!in_array($modelCode, mg_requestable_user_models(), true)) {
        mg_fail('This user model cannot be requested from this endpoint.', 403);
    }

    $changed = mg_assign_user_model(
        $userId,
        $modelCode,
        'pending',
        $userId,
        'user requested model',
        ['source' => 'user-model-request']
    );

    mg_audit('user_model.requested', 'user_model', ['model' => $modelCode, 'changed' => $changed], $userId);
    mg_event('user_model.requested', ['model' => $modelCode, 'changed' => $changed], $userId);

    return ['model' => $modelCode, 'status' => 'pending', 'changed' => $changed];
}

function mg_admin_set_user_model_status(int $targetUserId, string $modelCode, string $status, int $actorUserId, ?string $reason = null): array
{
    $allowed = ['active', 'rejected', 'disabled', 'suspended', 'revoked'];
    if (!in_array($status, $allowed, true)) {
        mg_fail('Invalid model status action.', 422);
    }

    $changed = mg_assign_user_model(
        $targetUserId,
        $modelCode,
        $status,
        $actorUserId,
        $reason ?: 'admin model status update',
        ['source' => 'admin-user-model-action']
    );

    if ($status === 'active') {
        mg_apply_default_roles_for_model($targetUserId, $modelCode);
        mg_create_profile_for_model($targetUserId, $modelCode);
    }

    mg_audit('user_model.' . $status, 'user_model', [
        'target_user_id' => $targetUserId,
        'model' => $modelCode,
        'changed' => $changed,
    ], $actorUserId);

    mg_event('user_model.' . $status, [
        'target_user_id' => $targetUserId,
        'model' => $modelCode,
        'changed' => $changed,
    ], $actorUserId);

    return ['user_id' => $targetUserId, 'model' => $modelCode, 'status' => $status, 'changed' => $changed];
}

function mg_set_active_model_context(int $userId, string $modelCode): array
{
    if (!in_array($modelCode, mg_all_supported_context_models(), true)) {
        mg_fail('Unknown user model context.', 422);
    }

    if (!mg_user_has_active_model($userId, $modelCode)) {
        mg_fail('You do not have this model active.', 403);
    }

    $_SESSION['mg_active_model'] = $modelCode;
    mg_audit('user_model.context_switched', 'user_model', ['model' => $modelCode], $userId);
    return ['active_model' => $modelCode];
}

function mg_current_active_model_context(int $userId): string
{
    $active = $_SESSION['mg_active_model'] ?? null;
    if (is_string($active) && mg_user_has_active_model($userId, $active)) {
        return $active;
    }

    if (mg_user_has_active_model($userId, 'customer')) {
        $_SESSION['mg_active_model'] = 'customer';
        return 'customer';
    }

    $models = mg_user_active_model_codes($userId);
    $fallback = $models[0] ?? 'customer';
    $_SESSION['mg_active_model'] = $fallback;
    return $fallback;
}
