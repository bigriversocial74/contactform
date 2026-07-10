<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/admin-auth.php';
require __DIR__ . '/admin-roles.php';

$config = lqr_config();
$state = lqr_load_state();
$message = null;
$error = null;
$recoveryLink = '';

function lqac_active_owner_count(array $admins): int
{
    $count = 0;
    foreach ($admins as $admin) {
        if (is_array($admin) && ($admin['role_key'] ?? '') === 'owner' && ($admin['status'] ?? '') === 'active') $count++;
    }
    return $count;
}

try {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'admin_login') {
        lqr_admin_login($state, $config, (string)($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''));
        lqr_save_state($state);
        header('Location: admin-credentials.php');
        exit;
    }
    if ($action === 'admin_logout') {
        lqr_admin_logout($state);
        lqr_save_state($state);
        header('Location: admin-credentials.php');
        exit;
    }

    $current = null;
    if ($action !== '') $current = lqr_admin_require($state, $config);

    if ($action === 'create_admin') {
        lqr_admin_require_role($current ?? [], 'owner');
        lqr_admin_create_user(
            $state,
            $config,
            (string)($_POST['username'] ?? ''),
            (string)($_POST['email'] ?? ''),
            (string)($_POST['new_password'] ?? ''),
            (string)($_POST['display_name'] ?? ''),
            (string)($_POST['role_key'] ?? 'admin')
        );
        lqr_save_state($state);
        $message = 'Admin user created.';
    } elseif ($action === 'change_password') {
        $current = $current ?? lqr_admin_current($state, $config);
        if (!$current) throw new RuntimeException('Admin login required.');
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$current['password_hash'])) throw new RuntimeException('Current password is incorrect.');
        $new = (string)($_POST['new_password'] ?? '');
        if ($new !== (string)($_POST['confirm_password'] ?? '')) throw new RuntimeException('Password confirmation does not match.');
        lqr_admin_update_password($state, $current, $new);
        lqr_save_state($state);
        $message = 'Password changed.';
    } elseif ($action === 'create_recovery') {
        lqr_admin_require_role($current ?? [], 'owner');
        $reset = lqr_admin_create_reset_token($state, $config, (string)($_POST['login'] ?? ''));
        lqr_save_state($state);
        $base = rtrim(lqr_config_value($config, 'app_public_url'), '/');
        $recoveryLink = ($base ?: '.') . '/admin-password-reset.php?token=' . rawurlencode((string)$reset['token']);
        $message = 'Recovery link created. Copy it now; the token is shown once.';
    } elseif ($action === 'set_status') {
        lqr_admin_require_role($current ?? [], 'owner');
        $adminId = (string)($_POST['admin_id'] ?? '');
        $status = (string)($_POST['status'] ?? 'active');
        if ($current && $adminId === (string)$current['id'] && $status !== 'active') throw new RuntimeException('You cannot disable your own active admin account.');
        $target = is_array($state['admin_users'][$adminId] ?? null) ? $state['admin_users'][$adminId] : [];
        if (($target['role_key'] ?? '') === 'owner' && $status === 'disabled' && lqac_active_owner_count($state['admin_users']) <= 1) {
            throw new RuntimeException('The final active owner cannot be disabled.');
        }
        lqr_admin_set_status($state, $adminId, $status);
        lqr_save_state($state);
        $message = 'Admin status updated.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$admins = lqr_admin_users($state, $config);
lqr_save_state($state);
$current = lqr_admin_current($state, $config);
$isOwner = $current ? lqr_admin_can_manage_admins($current) : false;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Access | Local Quest Rewards</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-access-hero{background:linear-gradient(135deg,#0f2f25,#164f39);color:#fff;border-radius:18px;padding:30px;margin-bottom:22px}.lq-access-hero h1{font-size:clamp(34px,5vw,58px);line-height:.96;letter-spacing:-.065em;margin:10px 0}.lq-access-hero p{color:#d3e8dc;max-width:860px}.lq-access-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.lq-access-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid var(--lq-border)}.lq-role-note{padding:13px;border-radius:10px;background:#f7f8fb;color:var(--lq-muted);font-size:13px}@media(max-width:900px){.lq-access-grid{grid-template-columns:1fr}.lq-access-row{grid-template-columns:1fr}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="cover.php">Public site</a><?php if(lqr_admin_is_authed()): ?><a class="lq-upgrade" href="admin.php">Admin home</a><?php endif; ?></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link active" href="admin-credentials.php"><span class="lq-side-icon">ID</span><span class="lq-side-label">Access</span></a><a class="lq-side-link" href="admin-quest-controls.php"><span class="lq-side-icon">Q</span><span class="lq-side-label">Quest controls</span></a><a class="lq-side-link" href="runtime-diagnostics.php"><span class="lq-side-icon">✓</span><span class="lq-side-label">Diagnostics</span></a></aside>
<main class="lq-main">
<?php if (!lqr_admin_is_authed()): ?>
<section class="lq-access-hero"><span class="lq-eyebrow">Protected administration</span><h1>Sign in to manage Local Quest.</h1><p>Administrative access is separate from participant accounts and uses role-controlled permissions.</p></section>
<section class="lq-card" style="max-width:520px"><h2>Admin sign in</h2><?php if($error):?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif;?><form method="post"><label class="lq-label">Username or email</label><input class="lq-input" name="login" autocomplete="username" required><label class="lq-label">Password</label><input class="lq-input" type="password" name="password" autocomplete="current-password" required><button class="lq-btn primary" name="action" value="admin_login" style="width:100%;margin-top:14px">Sign in</button></form><div class="lq-actions"><a class="lq-btn soft" href="admin-password-reset.php">Use a recovery token</a><a class="lq-btn soft" href="cover.php">Back to site</a></div></section>
<?php else: ?>
<section class="lq-access-hero"><span class="lq-eyebrow">Role-controlled access</span><h1>Admin credentials and recovery.</h1><p>Owners can manage administrators and issue recovery links. Every administrator can securely change their own password.</p><div class="lq-actions"><span class="lq-pill green"><?= lqr_h(lqr_admin_role_label((string)($current['role_key'] ?? ''))) ?></span><form method="post"><button class="lq-btn soft" name="action" value="admin_logout">Sign out</button></form></div></section>
<?php if($message):?><div class="lq-notice"><?= lqr_h($message) ?></div><?php endif;?><?php if($error):?><div class="lq-notice error"><?= lqr_h($error) ?></div><?php endif;?><?php if($recoveryLink):?><div class="lq-notice"><strong>One-time recovery link</strong><br><code><?= lqr_h($recoveryLink) ?></code></div><?php endif;?>

<div class="lq-access-grid">
  <section class="lq-card"><h2>Change my password</h2><p>Use at least 12 characters. A new session identifier is issued at your next sign-in.</p><form method="post"><label class="lq-label">Current password</label><input class="lq-input" type="password" name="current_password" required><label class="lq-label">New password</label><input class="lq-input" type="password" name="new_password" minlength="12" required><label class="lq-label">Confirm new password</label><input class="lq-input" type="password" name="confirm_password" minlength="12" required><button class="lq-btn primary" name="action" value="change_password" style="width:100%;margin-top:14px">Update password</button></form></section>
  <section class="lq-card"><h2>Your access</h2><div class="lq-row"><span>Username</span><strong><?= lqr_h((string)($current['username'] ?? '')) ?></strong></div><div class="lq-row"><span>Role</span><strong><?= lqr_h(lqr_admin_role_label((string)($current['role_key'] ?? ''))) ?></strong></div><div class="lq-row"><span>Status</span><strong><?= lqr_h((string)($current['status'] ?? '')) ?></strong></div><p class="lq-role-note"><?= lqr_h((string)(lqr_admin_role_map()[$current['role_key']]['description'] ?? 'Administrative access.')) ?></p></section>
</div>

<?php if ($isOwner): ?>
<div class="lq-access-grid" style="margin-top:18px">
  <section class="lq-card"><h2>Create administrator</h2><form method="post"><label class="lq-label">Username</label><input class="lq-input" name="username" required><label class="lq-label">Email</label><input class="lq-input" type="email" name="email"><label class="lq-label">Display name</label><input class="lq-input" name="display_name"><label class="lq-label">Role</label><select class="lq-input" name="role_key"><?= lqr_admin_role_options('admin') ?></select><label class="lq-label">Initial password</label><input class="lq-input" type="password" name="new_password" minlength="12" required><button class="lq-btn primary" name="action" value="create_admin" style="width:100%;margin-top:14px">Create administrator</button></form></section>
  <section class="lq-card"><h2>Create recovery link</h2><p>Recovery links are one-time tokens and are displayed only once.</p><form method="post"><label class="lq-label">Username or email</label><input class="lq-input" name="login" required><button class="lq-btn soft" name="action" value="create_recovery" style="width:100%;margin-top:14px">Create recovery link</button></form></section>
</div>

<section class="lq-card" style="margin-top:18px"><div class="lq-card-head"><div><h2>Administrative users</h2><p>Only owners can change administrative account status.</p></div><span class="lq-pill green"><?= number_format(count($admins)) ?> users</span></div><?php foreach($admins as $admin): if(!is_array($admin)) continue; ?><div class="lq-access-row"><div><strong><?= lqr_h((string)$admin['display_name']) ?></strong><div class="lq-meta"><?= lqr_h((string)$admin['username']) ?> · <?= lqr_h((string)($admin['email'] ?? '')) ?> · <?= lqr_h(lqr_admin_role_label((string)$admin['role_key'])) ?> · <?= lqr_h((string)$admin['status']) ?></div></div><form method="post"><input type="hidden" name="admin_id" value="<?= lqr_h((string)$admin['id']) ?>"><input type="hidden" name="status" value="<?= ($admin['status'] ?? '') === 'active' ? 'disabled' : 'active' ?>"><button class="lq-btn <?= ($admin['status'] ?? '') === 'active' ? 'dark' : 'primary' ?>" name="action" value="set_status"><?= ($admin['status'] ?? '') === 'active' ? 'Disable' : 'Enable' ?></button></form></div><?php endforeach; ?></section>
<?php endif; ?>
<?php endif; ?>
</main>
</div>
</body>
</html>
