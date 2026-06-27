<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
$isUserSignedIn = lqr_is_authenticated();
$stateReady = true;
$stateError = '';
$state = [];
try {
    $state = lqr_load_state();
} catch (Throwable $e) {
    $stateReady = false;
    $stateError = $e->getMessage();
}
$userId = $stateReady ? lqr_current_user_id($config) : '';
$user = $stateReady && $userId !== '' ? lqr_get_user($state, $config, $userId) : [];
$isLinked = trim((string)($user['linked_account_id'] ?? '')) !== '';
$rewardCount = count(is_array($user['rewards'] ?? null) ? $user['rewards'] : []);
$completedCount = count(is_array($user['completed_quests'] ?? null) ? $user['completed_quests'] : []);

function lqs_config_ok(array $config, string $key): bool
{
    $value = lqr_config_value($config, $key);
    return $value !== '' && !str_contains($value, 'replace_me') && !str_contains($value, 'replace_with');
}

$steps = [
    ['label'=>'Run runtime diagnostics','done'=>$stateReady && lqs_config_ok($config, 'base_url'),'href'=>'runtime-diagnostics.php','detail'=>$stateReady ? 'Check SQL, API config, program/template, and webhook readiness.' : $stateError],
    ['label'=>'Run installer / SQL setup','done'=>$stateReady,'href'=>'install.php','detail'=>$stateReady ? 'SQL runtime is reachable.' : $stateError],
    ['label'=>'Review API configuration','done'=>lqs_config_ok($config, 'base_url') && lqs_config_ok($config, 'api_key'),'href'=>'developer-starter.php','detail'=>'Base URL and bearer credential must be configured server-side.'],
    ['label'=>'Create or sign in as participant','done'=>$isUserSignedIn && !empty($user['email']),'href'=>'signin.php','detail'=>'The third-party app owns participant login and stable external_user_id.'],
    ['label'=>'Link Microgifter account','done'=>$isLinked,'href'=>'developer-starter.php','detail'=>'Use sandbox link for test mode or production consent for live mode.'],
    ['label'=>'Complete a quest action','done'=>$completedCount > 0,'href'=>'index.php','detail'=>'QR/geolocation task context is captured by the demo app.'],
    ['label'=>'Issue a reward','done'=>$rewardCount > 0,'href'=>'index.php','detail'=>'Reward issue calls the Public Distribution API with an idempotency key.'],
    ['label'=>'Claim/report from wallet','done'=>false,'href'=>'wallet.php','detail'=>'Wallet claim sends QR/geolocation evidence to the claim endpoint.'],
    ['label'=>'Generate webhook test payload','done'=>is_file(__DIR__ . '/webhook-events.log'),'href'=>'webhook-tools.php','detail'=>'Use local signed payload tools before sending real Developer API webhooks.'],
    ['label'=>'Verify webhook delivery','done'=>is_file(__DIR__ . '/webhook-events.log'),'href'=>'webhook.php','detail'=>'Signed lifecycle events reconcile back into Local Quest wallet state.'],
];
$done = 0;
foreach ($steps as $step) if (!empty($step['done'])) $done++;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Local Quest Demo Launcher</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-launch-hero{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(300px,.9fr);gap:22px;align-items:stretch}.lq-launch-big{background:linear-gradient(135deg,#211816,#4b1729);color:#fff;border-radius:14px;padding:30px;box-shadow:var(--lq-shadow)}.lq-launch-big h1{font-size:clamp(34px,5vw,62px);line-height:.95;letter-spacing:-.07em;margin:12px 0}.lq-launch-big p{color:#ffe6ef;max-width:780px}.lq-launch-card{background:#fff;border:1px solid var(--lq-border);border-radius:14px;padding:22px;box-shadow:var(--lq-shadow)}.lq-launch-progress{height:12px;background:#f1f5f9;border-radius:999px;overflow:hidden}.lq-launch-progress span{display:block;height:100%;background:var(--lq-pink);border-radius:999px}.lq-launch-steps{display:grid;gap:12px;margin-top:22px}.lq-launch-step{display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:12px;align-items:center;background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:14px}.lq-launch-step b{width:34px;height:34px;border-radius:999px;display:grid;place-items:center;background:#fff1f5;color:var(--lq-pink)}.lq-launch-step.done b{background:#e9f9ef;color:#137a3a}.lq-launch-step h3{margin:0;font-size:15px}.lq-launch-step p{margin:4px 0 0;color:var(--lq-muted);font-size:13px}.lq-launch-links{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-top:22px}.lq-launch-link{background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:16px;text-decoration:none;color:var(--lq-text);box-shadow:var(--lq-shadow)}.lq-launch-link strong{display:block;margin-bottom:6px}.lq-launch-link span{color:var(--lq-muted);font-size:13px}@media(max-width:1180px){.lq-launch-links{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:960px){.lq-launch-hero,.lq-launch-links{grid-template-columns:1fr}.lq-launch-step{grid-template-columns:34px minmax(0,1fr)}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="runtime-diagnostics.php">Diagnostics</a><a class="lq-upgrade" href="developer-starter.php">Developer Starter</a><a class="lq-upgrade" href="api-examples.php">API Examples</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link active" href="start.php"><span class="lq-side-icon">▶</span><span class="lq-side-label">Launcher</span></a><a class="lq-side-link" href="runtime-diagnostics.php"><span class="lq-side-icon">✓</span><span class="lq-side-label">Diagnostics</span></a><a class="lq-side-link" href="index.php"><span class="lq-side-icon">⌂</span><span class="lq-side-label">Quest board</span></a><a class="lq-side-link" href="wallet.php"><span class="lq-side-icon">◉</span><span class="lq-side-label">Wallet</span></a><a class="lq-side-link" href="developer-starter.php"><span class="lq-side-icon">API</span><span class="lq-side-label">Developer Starter</span></a><a class="lq-side-link" href="webhook-tools.php"><span class="lq-side-icon">◇</span><span class="lq-side-label">Webhook Tools</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a><a class="lq-side-link" href="admin-demo-tools.php"><span class="lq-side-icon">DB</span><span class="lq-side-label">Demo tools</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Admin readiness</span></a></aside>
<main class="lq-main">
<section class="lq-launch-hero">
  <div class="lq-launch-big"><span class="lq-eyebrow">Microgifter starter app</span><h1>Run the full Local Quest API demo.</h1><p>This launcher turns the demo into a guided outside-developer proof: diagnostics, setup, sign in, link, quest, reward issue, wallet claim, webhook verification, and launch QA.</p><div class="lq-actions"><a class="lq-btn primary" href="runtime-diagnostics.php">Run diagnostics</a><a class="lq-btn soft" href="developer-starter.php">Open Developer Starter</a><a class="lq-btn soft" href="api-examples.php">Copy API examples</a></div></div>
  <div class="lq-launch-card"><h2>Demo progress</h2><p><?= number_format($done) ?> of <?= number_format(count($steps)) ?> launcher checks are complete.</p><div class="lq-launch-progress"><span style="width:<?= count($steps) ? (int)round(($done/count($steps))*100) : 0 ?>%"></span></div><div class="lq-row"><span>Participant</span><strong><?= lqr_h((string)($user['email'] ?? 'Not signed in')) ?></strong></div><div class="lq-row"><span>Linked account</span><strong><?= lqr_h((string)($user['linked_account_id'] ?? 'Not linked')) ?></strong></div><div class="lq-row"><span>Rewards</span><strong><?= number_format($rewardCount) ?></strong></div></div>
</section>
<section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Guided demo path</h2><p>Complete these in order to prove the platform is cloneable for third-party apps.</p></div><span class="lq-pill <?= $done === count($steps) ? 'green' : 'amber' ?>"><?= number_format($done) ?>/<?= number_format(count($steps)) ?></span></div><div class="lq-launch-steps"><?php foreach ($steps as $i => $step): ?><article class="lq-launch-step <?= !empty($step['done']) ? 'done' : '' ?>"><b><?= !empty($step['done']) ? '✓' : ($i + 1) ?></b><div><h3><?= lqr_h($step['label']) ?></h3><p><?= lqr_h($step['detail']) ?></p></div><a class="lq-btn soft" href="<?= lqr_h($step['href']) ?>">Open</a></article><?php endforeach; ?></div></section>
<section class="lq-launch-links"><a class="lq-launch-link" href="runtime-diagnostics.php"><strong>Runtime diagnostics</strong><span>Check SQL/API setup.</span></a><a class="lq-launch-link" href="signin.php"><strong>Participant sign in</strong><span>Create the third-party app user.</span></a><a class="lq-launch-link" href="index.php"><strong>Quest board</strong><span>Complete QR/location tasks.</span></a><a class="lq-launch-link" href="wallet.php"><strong>Reward wallet</strong><span>Refresh status and claim.</span></a><a class="lq-launch-link" href="webhook-tools.php"><strong>Webhook tools</strong><span>Generate signed test payloads.</span></a><a class="lq-launch-link" href="admin-demo-tools.php"><strong>Demo seed tools</strong><span>Prepare a handoff demo.</span></a></section>
</main>
</div>
</body>
</html>
