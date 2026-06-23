<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$config = lqr_config();
$state = lqr_load_state();
$message = null;
$error = null;
$mode = (string)($_GET['mode'] ?? 'signin');
try {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'register') {
        $user = lqr_action_register($state, $config);
        lqr_save_state($state);
        header('Location: index.php');
        exit;
    }
    if ($action === 'login') {
        $user = lqr_action_login($state, $config);
        lqr_save_state($state);
        header('Location: index.php');
        exit;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    lqr_add_event($state, 'auth.error', $error);
    lqr_save_state($state);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in | <?= lqr_h((string)($config['app_name'] ?? 'Local Quest Rewards')) ?></title>
<style>
:root{--bg:#071225;--panel:#0d1b2f;--line:#24415f;--text:#f5f9ff;--muted:#9db3cc;--blue:#5aa7ff;--green:#4ade80;--red:#fb7185}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 18% 0,rgba(90,167,255,.23),transparent 32%),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{width:min(980px,92%);margin:0 auto;min-height:100vh;display:grid;align-items:center;padding:50px 0}.shell{display:grid;grid-template-columns:1fr 420px;gap:22px;align-items:stretch}.panel{background:rgba(13,27,47,.92);border:1px solid rgba(148,180,213,.22);border-radius:26px;padding:28px;box-shadow:0 24px 70px rgba(0,0,0,.28)}h1{margin:0;font-size:clamp(42px,6vw,70px);line-height:.94;letter-spacing:-.07em}h2{margin:0 0 16px;font-size:26px}p{color:var(--muted);line-height:1.62}.tabs{display:flex;gap:8px;margin-bottom:16px}.tabs a{flex:1;min-height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;text-decoration:none;font-weight:900;color:var(--text);background:rgba(255,255,255,.07);border:1px solid var(--line)}.tabs a.active{background:var(--blue);color:#06111f}label{display:block;margin-top:12px;color:#c8dbef;font-size:13px;font-weight:900}input{width:100%;min-height:44px;margin-top:6px;border-radius:13px;border:1px solid var(--line);background:#07192d;color:var(--text);font:inherit;padding:0 12px}button,.btn{width:100%;min-height:46px;margin-top:14px;border:0;border-radius:13px;background:var(--green);color:#062113;font-weight:950;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center}.btn{background:rgba(255,255,255,.08);border:1px solid var(--line);color:var(--text)}.notice{padding:12px 14px;border-radius:14px;background:rgba(251,113,133,.13);border:1px solid rgba(251,113,133,.4);color:#ffd7df;margin-bottom:14px}.small{font-size:13px}@media(max-width:820px){.shell{grid-template-columns:1fr}h1{font-size:42px}}
</style>
</head>
<body>
<div class="wrap">
  <main class="shell">
    <section class="panel">
      <h1>Sign in before you play.</h1>
      <p>The Quest app is its own working application. Participants need a Local Quest account before completing quests or receiving rewards.</p>
      <p class="small">After sign-in, users connect their Microgifter account through Microgifter consent. The Quest app should not silently create Microgifter accounts.</p>
      <a class="btn" href="cover.php">Back to cover</a>
    </section>
    <section class="panel">
      <div class="tabs">
        <a class="<?= $mode !== 'signup' ? 'active' : '' ?>" href="signin.php">Sign in</a>
        <a class="<?= $mode === 'signup' ? 'active' : '' ?>" href="signin.php?mode=signup">Create account</a>
      </div>
      <?php if ($error): ?><div class="notice"><?= lqr_h($error) ?></div><?php endif; ?>
      <?php if ($mode === 'signup'): ?>
        <form method="post">
          <h2>Create Local Quest account</h2>
          <label>Name<input name="display_name" autocomplete="name" required></label>
          <label>Email<input name="email" type="email" autocomplete="email" required></label>
          <label>Password<input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
          <button name="action" value="register">Create account</button>
        </form>
      <?php else: ?>
        <form method="post">
          <h2>Sign in</h2>
          <label>Email<input name="email" type="email" autocomplete="email" required></label>
          <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
          <button name="action" value="login">Sign in</button>
        </form>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
