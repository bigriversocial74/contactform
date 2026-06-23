<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/admin-auth.php';

$config = lqr_config();
$state = lqr_load_state();
$message = null;
$error = null;
$recoveryLink = '';

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
    if ($action !== '') lqr_admin_require($state, $config);
    if ($action === 'create_admin') {
        lqr_admin_create_user($state, $config, (string)($_POST['username'] ?? ''), (string)($_POST['email'] ?? ''), (string)($_POST['new_password'] ?? ''), (string)($_POST['display_name'] ?? ''), (string)($_POST['role_key'] ?? 'admin'));
        lqr_save_state($state);
        $message = 'Admin user created.';
    } elseif ($action === 'change_password') {
        $admin = lqr_admin_current($state, $config);
        if (!$admin) throw new RuntimeException('Admin login required.');
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$admin['password_hash'])) throw new RuntimeException('Current password is incorrect.');
        $new = (string)($_POST['new_password'] ?? '');
        if ($new !== (string)($_POST['confirm_password'] ?? '')) throw new RuntimeException('Password confirmation does not match.');
        lqr_admin_update_password($state, $admin, $new);
        lqr_save_state($state);
        $message = 'Password changed.';
    } elseif ($action === 'create_recovery') {
        $reset = lqr_admin_create_reset_token($state, $config, (string)($_POST['login'] ?? ''));
        lqr_save_state($state);
        $base = rtrim(lqr_config_value($config, 'app_public_url'), '/');
        $recoveryLink = ($base ?: '.') . '/admin-password-reset.php?token=' . rawurlencode((string)$reset['token']);
        $message = 'Recovery link created. Copy it now; the token is shown once.';
    } elseif ($action === 'set_status') {
        $adminId = (string)($_POST['admin_id'] ?? '');
        $status = (string)($_POST['status'] ?? 'active');
        $current = lqr_admin_current($state, $config);
        if ($current && $adminId === (string)$current['id'] && $status !== 'active') throw new RuntimeException('You cannot change your own active admin session status.');
        lqr_admin_set_status($state, $adminId, $status);
        lqr_save_state($state);
        $message = 'Admin status updated.';
    }
} catch (Throwable $e) { $error = $e->getMessage(); }

$admins = lqr_admin_users($state, $config);
lqr_save_state($state);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Credentials</title><style>body{margin:0;background:#071225;color:#f5f9ff;font-family:Arial,sans-serif}.wrap{width:min(1100px,94%);margin:0 auto;padding:32px 0}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:18px;padding:18px;margin:12px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.btn,button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border-radius:12px;background:#172b47;color:#f5f9ff;border:1px solid #24415f;font-weight:800;text-decoration:none;cursor:pointer}.green{background:#4ade80;color:#062113;border:0}.red{background:#fb7185;color:#28050c;border:0}input,select{width:100%;min-height:38px;margin-top:5px;border-radius:10px;border:1px solid #24415f;background:#07192d;color:#f5f9ff;padding:6px 9px}label{display:block;margin-top:9px;font-size:12px;font-weight:800;color:#c8dbef}.notice{padding:10px 12px;border-radius:12px;background:#10243d;word-break:break-word}.error{background:rgba(251,113,133,.16);color:#ffd7df}.row{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.1)}p,small{color:#9db3cc}@media(max-width:900px){.grid{display:block}}</style></head><body><div class="wrap">
<?php if (!lqr_admin_is_authed()): ?><section class="card"><h1>Admin login</h1><?php if($error):?><p class="notice error"><?= lqr_h($error) ?></p><?php endif;?><form method="post"><label>Username or email<input name="login" required></label><label>Password<input type="password" name="password" required></label><button class="green" name="action" value="admin_login" style="width:100%;margin-top:12px">Sign in</button></form><p><a class="btn" href="admin-password-reset.php">Recovery</a> <a class="btn" href="cover.php">Back</a></p></section><?php exit; endif; ?>
<header><h1>Admin Credentials</h1><p><a class="btn" href="admin.php">Admin home</a> <a class="btn" href="admin-quest-controls.php">Quest controls</a></p><form method="post"><button class="red" name="action" value="admin_logout">Sign out</button></form></header><?php if($message):?><p class="notice"><?= lqr_h($message) ?></p><?php endif;?><?php if($error):?><p class="notice error"><?= lqr_h($error) ?></p><?php endif;?><?php if($recoveryLink):?><p class="notice"><strong>Recovery link:</strong><br><?= lqr_h($recoveryLink) ?></p><?php endif;?>
<section class="grid"><div class="card"><h2>Create admin</h2><form method="post"><label>Username<input name="username" required></label><label>Email<input type="email" name="email"></label><label>Display name<input name="display_name"></label><label>Role<select name="role_key"><option value="admin">Admin</option><option value="owner">Owner</option></select></label><label>Initial password<input type="password" name="new_password" minlength="12" required></label><button class="green" name="action" value="create_admin" style="width:100%;margin-top:12px">Create</button></form></div><div class="card"><h2>Change my password</h2><form method="post"><label>Current password<input type="password" name="current_password" required></label><label>New password<input type="password" name="new_password" minlength="12" required></label><label>Confirm<input type="password" name="confirm_password" minlength="12" required></label><button class="green" name="action" value="change_password" style="width:100%;margin-top:12px">Update</button></form></div></section>
<section class="card"><h2>Recovery link</h2><form method="post"><label>Username or email<input name="login" required></label><button name="action" value="create_recovery" style="margin-top:12px">Create recovery link</button></form></section>
<section class="card"><h2>Admin users</h2><?php foreach($admins as $admin): if(!is_array($admin)) continue; ?><div class="row"><span><strong><?= lqr_h((string)$admin['username']) ?></strong><br><small><?= lqr_h((string)($admin['email'] ?? '')) ?> · <?= lqr_h((string)$admin['role_key']) ?> · <?= lqr_h((string)$admin['status']) ?></small></span><form method="post"><input type="hidden" name="admin_id" value="<?= lqr_h((string)$admin['id']) ?>"><input type="hidden" name="status" value="<?= ($admin['status'] ?? '') === 'active' ? 'disabled' : 'active' ?>"><button class="<?= ($admin['status'] ?? '') === 'active' ? 'red' : 'green' ?>" name="action" value="set_status"><?= ($admin['status'] ?? '') === 'active' ? 'Disable' : 'Enable' ?></button></form></div><?php endforeach; ?></section>
</div></body></html>
