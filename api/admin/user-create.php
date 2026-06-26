<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

mg_require_method('POST');
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
mg_rate_limit('admin.user_create.write', 'user:' . $actorId, 40, 60);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();

function mg_admin_create_user_text(mixed $value, int $min, int $max, string $label): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    $length = mb_strlen($text);
    if ($length < $min || $length > $max) {
        throw new MgAdminAccountException($label . ' must be between ' . $min . ' and ' . $max . ' characters.', 422);
    }
    return $text;
}

function mg_admin_create_user_password(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $password = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < 18; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}

function mg_admin_create_user_roles(PDO $pdo, array $actor, mixed $roles): array
{
    $requested = is_array($roles) ? $roles : [];
    $requested = array_values(array_unique(array_filter(array_map(
        static fn(mixed $role): string => strtolower(trim((string)$role)),
        $requested
    ))));
    if ($requested === []) {
        $requested = ['customer'];
    }

    $available = array_column(mg_admin_account_available_roles($pdo, $actor), 'slug');
    $availableSet = array_fill_keys($available, true);
    foreach ($requested as $role) {
        if (!isset($availableSet[$role])) {
            throw new MgAdminAccountException('You cannot assign one or more selected roles.', 403);
        }
    }
    return $requested;
}

try {
    if (!mg_admin_account_actor_has($actor, 'admin.users.manage')) {
        mg_audit('permission_denied', 'security', ['permission' => 'admin.users.manage', 'action' => 'create_user'], $actorId);
        mg_fail('Permission denied.', 403);
    }

    $email = strtolower(trim((string)($input['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new MgAdminAccountException('Enter a valid email address.', 422);
    }
    $fullName = mg_admin_create_user_text($input['full_name'] ?? '', 1, 160, 'Full name');
    $displayName = trim((string)($input['display_name'] ?? ''));
    if ($displayName === '') {
        $displayName = $fullName;
    }
    if (mb_strlen($displayName) > 160) {
        throw new MgAdminAccountException('Display name cannot exceed 160 characters.', 422);
    }

    $status = strtolower(trim((string)($input['status'] ?? 'active')));
    if (!in_array($status, ['active', 'pending', 'disabled'], true)) {
        throw new MgAdminAccountException('Invalid account status.', 422);
    }

    $reason = mg_admin_account_reason($input['reason'] ?? null);
    $roles = mg_admin_create_user_roles($pdo, $actor, $input['roles'] ?? []);
    $providedPassword = trim((string)($input['password'] ?? ''));
    $generatedPassword = false;
    if ($providedPassword === '') {
        $providedPassword = mg_admin_create_user_password();
        $generatedPassword = true;
    }
    if (strlen($providedPassword) < 12 || strlen($providedPassword) > 120) {
        throw new MgAdminAccountException('Temporary password must be between 12 and 120 characters.', 422);
    }
    $hash = password_hash($providedPassword, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new MgAdminAccountException('Unable to secure password.', 500);
    }

    $emailVerified = filter_var($input['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
    $find->execute([$email]);
    if ($find->fetch()) {
        throw new MgAdminAccountException('An account already exists for this email.', 409);
    }

    $stmt = $pdo->prepare('INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
    $stmt->execute([$email, $hash, $fullName, $displayName, $status, $emailVerified ? date('Y-m-d H:i:s') : null]);
    $targetUserId = (int)$pdo->lastInsertId();

    $roleStmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id, created_at) SELECT ?, id, NOW() FROM roles WHERE slug = ? ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)');
    foreach ($roles as $role) {
        $roleStmt->execute([$targetUserId, $role]);
    }
    $pdo->prepare('INSERT IGNORE INTO user_profiles (user_id,created_at,updated_at) VALUES (?,NOW(),NOW())')->execute([$targetUserId]);

    $metadata = [
        'target_user_id' => $targetUserId,
        'email' => $email,
        'status' => $status,
        'roles' => $roles,
        'email_verified' => $emailVerified,
        'generated_password' => $generatedPassword,
        'reason' => $reason,
    ];
    mg_audit('admin_user_create', 'user', $metadata, $actorId);
    mg_event('admin.user.create', $metadata + ['admin_user_id' => $actorId], $actorId);
    mg_security_log('info', 'admin.user_create.completed', 'Admin-created user account.', [
        'target_user_id' => $targetUserId,
        'roles' => $roles,
    ], $actorId);

    $detail = mg_admin_account_context($pdo, $actor, $targetUserId);
    $pdo->commit();
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('warning', 'admin.user_create.rejected', 'Admin user creation rejected.', [
        'reason' => $error->getMessage(),
    ], $actorId);
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.user_create.failed', 'Admin user creation failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to create user.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok([
    'user' => $detail,
    'temporary_password' => $generatedPassword ? $providedPassword : null,
], 'User created.');
