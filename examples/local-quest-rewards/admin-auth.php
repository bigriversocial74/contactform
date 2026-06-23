<?php
declare(strict_types=1);

function lqr_admin_settings(array $config): array
{
    $admin = is_array($config['admin'] ?? null) ? $config['admin'] : [];
    return array_replace([
        'username' => 'admin',
        'email' => 'admin@example.test',
        'password' => 'change-me-admin-password',
        'password_hash' => '',
        'bootstrap_enabled' => true,
        'reset_token_ttl_minutes' => 30,
    ], $admin);
}

function lqr_admin_users(array &$state, array $config): array
{
    if (!isset($state['admin_users']) || !is_array($state['admin_users'])) $state['admin_users'] = [];
    $settings = lqr_admin_settings($config);
    if (empty($state['admin_users']) && !empty($settings['bootstrap_enabled'])) {
        $username = strtolower(trim((string)$settings['username']));
        $hash = (string)$settings['password_hash'];
        if ($hash === '') $hash = password_hash((string)$settings['password'], PASSWORD_DEFAULT);
        $publicId = 'admin_' . substr(hash('sha256', $username . '|local-quest-admin'), 0, 16);
        $state['admin_users'][$publicId] = [
            'id' => $publicId,
            'username' => $username,
            'email' => strtolower(trim((string)$settings['email'])),
            'display_name' => 'Admin',
            'password_hash' => $hash,
            'role_key' => 'admin',
            'status' => 'active',
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'last_login_at' => '',
        ];
    }
    return $state['admin_users'];
}

function lqr_admin_find_user(array &$state, array $config, string $login): ?array
{
    $login = strtolower(trim($login));
    foreach (lqr_admin_users($state, $config) as $admin) {
        if (!is_array($admin)) continue;
        if (strtolower((string)$admin['username']) === $login || strtolower((string)($admin['email'] ?? '')) === $login) return $admin;
    }
    return null;
}

function lqr_admin_is_authed(): bool
{
    return !empty($_SESSION['lqr_admin_authed']) && !empty($_SESSION['lqr_admin_user_id']);
}

function lqr_admin_current(array &$state, array $config): ?array
{
    $adminId = (string)($_SESSION['lqr_admin_user_id'] ?? '');
    $admins = lqr_admin_users($state, $config);
    return is_array($admins[$adminId] ?? null) ? $admins[$adminId] : null;
}

function lqr_admin_login(array &$state, array $config, string $login, string $password): array
{
    $admin = lqr_admin_find_user($state, $config, $login);
    if (!$admin || ($admin['status'] ?? '') !== 'active' || !password_verify($password, (string)$admin['password_hash'])) {
        throw new RuntimeException('Invalid admin credentials.');
    }
    $admin['last_login_at'] = gmdate('c');
    $admin['updated_at'] = gmdate('c');
    $state['admin_users'][(string)$admin['id']] = $admin;
    $_SESSION['lqr_admin_authed'] = true;
    $_SESSION['lqr_admin_user_id'] = (string)$admin['id'];
    $_SESSION['lqr_admin_username'] = (string)$admin['username'];
    lqr_add_event($state, 'admin.login', 'Admin signed in.', ['admin_id'=>$admin['id'], 'username'=>$admin['username']]);
    return $admin;
}

function lqr_admin_logout(array &$state): void
{
    $username = (string)($_SESSION['lqr_admin_username'] ?? '');
    unset($_SESSION['lqr_admin_authed'], $_SESSION['lqr_admin_user_id'], $_SESSION['lqr_admin_username']);
    lqr_add_event($state, 'admin.logout', 'Admin signed out.', ['username'=>$username]);
}

function lqr_admin_require(array &$state, array $config): array
{
    $admin = lqr_admin_current($state, $config);
    if (!lqr_admin_is_authed() || !$admin || ($admin['status'] ?? '') !== 'active') throw new RuntimeException('Admin login required.');
    return $admin;
}

function lqr_admin_create_user(array &$state, array $config, string $username, string $email, string $password, string $displayName = 'Admin', string $role = 'admin'): array
{
    $username = strtolower(trim($username));
    $email = strtolower(trim($email));
    if (!preg_match('/^[a-z0-9_.-]{3,80}$/', $username)) throw new RuntimeException('Admin username must be 3-80 characters and use letters, numbers, dots, dashes, or underscores.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid admin email.');
    if (strlen($password) < 12) throw new RuntimeException('Admin password must be at least 12 characters.');
    if (lqr_admin_find_user($state, $config, $username) || ($email !== '' && lqr_admin_find_user($state, $config, $email))) throw new RuntimeException('Admin user already exists.');
    $publicId = 'admin_' . bin2hex(random_bytes(8));
    $admin = [
        'id' => $publicId,
        'username' => $username,
        'email' => $email,
        'display_name' => trim($displayName) ?: $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role_key' => $role === 'owner' ? 'owner' : 'admin',
        'status' => 'active',
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
        'last_login_at' => '',
    ];
    $state['admin_users'][$publicId] = $admin;
    lqr_add_event($state, 'admin.user_created', 'Admin user created.', ['admin_id'=>$publicId, 'username'=>$username]);
    return $admin;
}

function lqr_admin_update_password(array &$state, array $admin, string $password): void
{
    if (strlen($password) < 12) throw new RuntimeException('Admin password must be at least 12 characters.');
    $admin['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    $admin['updated_at'] = gmdate('c');
    $state['admin_users'][(string)$admin['id']] = $admin;
    lqr_add_event($state, 'admin.password_changed', 'Admin password changed.', ['admin_id'=>$admin['id'], 'username'=>$admin['username']]);
}

function lqr_admin_create_reset_token(array &$state, array $config, string $login): array
{
    $admin = lqr_admin_find_user($state, $config, $login);
    if (!$admin || ($admin['status'] ?? '') !== 'active') throw new RuntimeException('Active admin user not found.');
    if (!isset($state['admin_password_resets']) || !is_array($state['admin_password_resets'])) $state['admin_password_resets'] = [];
    $token = bin2hex(random_bytes(24));
    $settings = lqr_admin_settings($config);
    $ttl = max(5, min(240, (int)$settings['reset_token_ttl_minutes']));
    $record = [
        'admin_id' => (string)$admin['id'],
        'token_hash' => hash('sha256', $token),
        'created_at' => gmdate('c'),
        'expires_at' => gmdate('c', time() + ($ttl * 60)),
        'used_at' => '',
    ];
    $state['admin_password_resets'][] = $record;
    lqr_add_event($state, 'admin.reset_token_created', 'Admin password reset token created.', ['admin_id'=>$admin['id'], 'username'=>$admin['username'], 'expires_at'=>$record['expires_at']]);
    return ['token'=>$token, 'record'=>$record, 'admin'=>$admin];
}

function lqr_admin_reset_password_with_token(array &$state, string $token, string $password): array
{
    $tokenHash = hash('sha256', trim($token));
    $resets = is_array($state['admin_password_resets'] ?? null) ? $state['admin_password_resets'] : [];
    foreach ($resets as $i => $record) {
        if (!is_array($record)) continue;
        if (!hash_equals((string)$record['token_hash'], $tokenHash)) continue;
        if (!empty($record['used_at'])) throw new RuntimeException('Reset token has already been used.');
        if (strtotime((string)$record['expires_at']) < time()) throw new RuntimeException('Reset token has expired.');
        $adminId = (string)$record['admin_id'];
        if (empty($state['admin_users'][$adminId]) || !is_array($state['admin_users'][$adminId])) throw new RuntimeException('Admin user not found.');
        $admin = $state['admin_users'][$adminId];
        lqr_admin_update_password($state, $admin, $password);
        $state['admin_password_resets'][$i]['used_at'] = gmdate('c');
        lqr_add_event($state, 'admin.password_reset_completed', 'Admin password reset completed.', ['admin_id'=>$adminId]);
        return $state['admin_users'][$adminId];
    }
    throw new RuntimeException('Invalid reset token.');
}

function lqr_admin_set_status(array &$state, string $adminId, string $status): void
{
    if (empty($state['admin_users'][$adminId]) || !is_array($state['admin_users'][$adminId])) throw new RuntimeException('Admin user not found.');
    $state['admin_users'][$adminId]['status'] = $status === 'disabled' ? 'disabled' : 'active';
    $state['admin_users'][$adminId]['updated_at'] = gmdate('c');
    lqr_add_event($state, 'admin.status_changed', 'Admin status changed.', ['admin_id'=>$adminId, 'status'=>$state['admin_users'][$adminId]['status']]);
}
