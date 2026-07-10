<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$config = lqr_config();
$state = lqr_load_state();
$error = null;
$mode = (string)($_GET['mode'] ?? 'signin');
try {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'register') {
        lqr_action_register($state, $config);
        session_regenerate_id(true);
        lqr_save_state($state);
        header('Location: index.php');
        exit;
    }
    if ($action === 'login') {
        lqr_action_login($state, $config);
        session_regenerate_id(true);
        lqr_save_state($state);
        header('Location: index.php');
        exit;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    lqr_add_event($state, 'auth.error', 'Participant authentication failed.');
    lqr_save_state($state);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in | <?= lqr_h((string)($config['app_name'] ?? 'Local Quest Rewards')) ?></title><style>:root{--bg:#f3f7f4;--panel:#fff;--line:#dce6df;--text:#15201a;--muted:#66736b;--green:#155f44;--lime:#e1f56f;--red:#b42318}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 12% 0,rgba(225,245,111,.42),transparent 28%),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{width:min(1040px,92%);margin:0 auto;min-height:100vh;display:grid;align-items:center;padding:50px 0}.shell{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:22px;align-items:stretch}.panel{background:var(--panel);border:1px solid var(--line);border-radius:28px;padding:30px;box-shadow:0 24px 70px rgba(25,54,40,.1)}.intro{background:linear-gradient(145deg,#0d3d2c,#166044);color:#fff;display:flex;flex-direction:column;justify-content:flex-end;min-height:560px}.mark{width:48px;height:48px;border-radius:15px;background:var(--lime);color:#17321c;display:grid;place-items:center;font-weight:950;margin-bottom:auto}h1{margin:0;font-size:clamp(45px,6vw,72px);line-height:.92;letter-spacing:-.075em}h2{margin:0 0 16px;font-size:27px;letter-spacing:-.04em}p{color:var(--muted);line-height:1.65}.intro p{color:#d6e8dd}.tabs{display:flex;gap:8px;margin-bottom:18px}.tabs a{flex:1;min-height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;text-decoration:none;font-weight:900;color:var(--text);background:#f4f7f5;border:1px solid var(--line)}.tabs a.active{background:var(--green);color:#fff}label{display:block;margin-top:12px;color:#34463d;font-size:12px;font-weight:900}input{width:100%;min-height:46px;margin-top:7px;border-radius:13px;border:1px solid var(--line);background:#fff;color:var(--text);font:inherit;padding:0 12px}button,.btn{width:100%;min-height:48px;margin-top:15px;border:0;border-radius:13px;background:var(--lime);color:#17321c;font-weight:950;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center}.btn{background:#fff;border:1px solid rgba(255,255,255,.3);color:#fff}.notice{padding:12px 14px;border-radius:14px;background:#fff1f0;border:1px solid #f3c7c3;color:var(--red);margin-bottom:14px}@media(max-width:820px){.shell{grid-template-columns:1fr}.intro{min-height:380px}h1{font-size:46px}}</style></head><body><div class="wrap"><main class="shell"><section class="panel intro"><span class="mark">LQ</span><h1>Sign in before you quest.</h1><p>Create a Local Quest account, connect Microgifter with consent, complete verified local actions, and follow your rewards from one wallet.</p><a class="btn" href="cover.php">Back to public site</a></section><section class="panel"><div class="tabs"><a class="<?= $mode !== 'signup' ? 'active' : '' ?>" href="signin.php">Sign in</a><a class="<?= $mode === 'signup' ? 'active' : '' ?>" href="signin.php?mode=signup">Create account</a></div><?php if($error): ?><div class="notice"><?= lqr_h($error) ?></div><?php endif; ?><?php if($mode === 'signup'): ?><form method="post"><h2>Create your Quest account</h2><label>Name<input name="display_name" autocomplete="name" required></label><label>Email<input name="email" type="email" autocomplete="email" required></label><label>Password<input name="password" type="password" autocomplete="new-password" minlength="8" required></label><button name="action" value="register">Create account</button></form><?php else: ?><form method="post"><h2>Welcome back</h2><label>Email<input name="email" type="email" autocomplete="email" required></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><button name="action" value="login">Sign in</button></form><?php endif; ?></section></main></div></body></html>
