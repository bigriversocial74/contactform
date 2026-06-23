<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
$config = lqr_config();
$state = lqr_load_state();
$message = null;
$error = null;
try {
    $user = lqr_complete_account_link($state, $config, $_GET);
    lqr_save_state($state);
    $message = 'Microgifter account connected. You can now complete quests and receive rewards.';
} catch (Throwable $e) {
    $error = $e->getMessage();
    lqr_add_event($state, 'microgifter.account_link_error', $error);
    lqr_save_state($state);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Account Link | Local Quest Rewards</title>
<style>
body{margin:0;background:#071225;color:#f5f9ff;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{width:min(720px,92%);margin:0 auto;min-height:100vh;display:grid;place-items:center}.card{background:#0d1b2f;border:1px solid #24415f;border-radius:26px;padding:30px;box-shadow:0 24px 70px rgba(0,0,0,.28)}h1{margin:0;font-size:42px;letter-spacing:-.06em}p{color:#9db3cc;line-height:1.6}.notice{margin-top:18px;padding:14px;border-radius:16px;background:rgba(74,222,128,.13);border:1px solid rgba(74,222,128,.35);color:#d8ffe5}.error{background:rgba(251,113,133,.13);border-color:rgba(251,113,133,.35);color:#ffd7df}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border-radius:13px;background:#4ade80;color:#062113;font-weight:950;text-decoration:none;margin-top:18px}
</style>
</head>
<body><div class="wrap"><main class="card">
<h1><?= $error ? 'Link failed' : 'Account connected' ?></h1>
<div class="notice <?= $error ? 'error' : '' ?>"><?= lqr_h($error ?: (string)$message) ?></div>
<a class="btn" href="index.php">Open quest board</a>
</main></div></body></html>
