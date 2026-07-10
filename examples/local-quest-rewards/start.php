<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/webhook-storage.php';

$config = lqr_config();
$isUserSignedIn = lqr_is_authenticated();
$stateReady = true;
$stateError = '';
$state = [];
$webhookCount = 0;
try {
    $state = lqr_load_state();
    $webhookCount = lqr_webhook_delivery_count(lqr_sql_db($config));
} catch (Throwable $e) {
    $stateReady = false;
    $stateError = $e->getMessage();
}

$userId = $stateReady ? lqr_current_user_id($config) : '';
$user = $stateReady && $userId !== '' ? lqr_get_user($state, $config, $userId) : [];
$isLinked = trim((string)($user['linked_account_id'] ?? '')) !== '';
$rewardCount = count(is_array($user['rewards'] ?? null) ? $user['rewards'] : []);
$completedCount = count(is_array($user['completed_quests'] ?? null) ? $user['completed_quests'] : []);
$claimReported = false;
foreach ((array)($user['rewards'] ?? []) as $reward) {
    if (is_array($reward) && in_array((string)($reward['claim_report_status'] ?? ''), ['reported_to_microgifter', 'confirmed_by_microgifter_webhook'], true)) {
        $claimReported = true;
        break;
    }
}

function lqs_config_ok(array $config, string $key): bool
{
    $value = lqr_config_value($config, $key);
    return $value !== '' && !str_contains($value, 'replace_me') && !str_contains($value, 'replace_with');
}

$steps = [
    ['label' => 'Run runtime diagnostics', 'done' => $stateReady && lqs_config_ok($config, 'base_url'), 'href' => 'runtime-diagnostics.php', 'detail' => $stateReady ? 'Check SQL, API configuration, schema version, installer lock, and webhook readiness.' : $stateError],
    ['label' => 'Complete guarded installation', 'done' => is_file(__DIR__ . '/config.php') && is_file(__DIR__ . '/.installed.lock'), 'href' => 'install.php', 'detail' => 'The installer should remain locked after config.php and the owner account are created.'],
    ['label' => 'Review API configuration', 'done' => lqs_config_ok($config, 'base_url') && lqs_config_ok($config, 'api_key'), 'href' => 'developer-starter.php', 'detail' => 'Base URL and bearer credential must remain server-side.'],
    ['label' => 'Create or sign in as participant', 'done' => $isUserSignedIn && !empty($user['email']), 'href' => 'signin.php', 'detail' => 'The third-party app owns participant login and a stable external_user_id.'],
    ['label' => 'Link Microgifter account', 'done' => $isLinked, 'href' => 'developer-starter.php', 'detail' => 'Use sandbox linking in test mode or production consent in live mode.'],
    ['label' => 'Complete a verified quest action', 'done' => $completedCount > 0, 'href' => 'index.php', 'detail' => 'QR, signed code, and geolocation context can be captured by the app.'],
    ['label' => 'Issue a reward', 'done' => $rewardCount > 0, 'href' => 'index.php', 'detail' => 'Reward issue calls the Public Distribution API with idempotency controls.'],
    ['label' => 'Claim and report from wallet', 'done' => $claimReported, 'href' => 'wallet.php', 'detail' => 'Wallet claims report QR/geolocation evidence to Microgifter.'],
    ['label' => 'Verify a signed webhook', 'done' => $webhookCount > 0, 'href' => 'webhook.php', 'detail' => 'Webhook deliveries are stored in SQL and visible only to authenticated administrators.'],
];
$done = count(array_filter($steps, static fn(array $step): bool => !empty($step['done'])));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Local Quest Launch Console</title>
<link rel="stylesheet" href="assets/portal.css">
<style>.lq-launch-hero{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(300px,.9fr);gap:22px;align-items:stretch}.lq-launch-big{background:linear-gradient(135deg,#0f2f25,#18583f);color:#fff;border-radius:18px;padding:32px;box-shadow:var(--lq-shadow)}.lq-launch-big h1{font-size:clamp(34px,5vw,62px);line-height:.95;letter-spacing:-.07em;margin:12px 0}.lq-launch-big p{color:#d4e8dc;max-width:780px}.lq-launch-card{background:#fff;border:1px solid var(--lq-border);border-radius:18px;padding:22px;box-shadow:var(--lq-shadow)}.lq-launch-progress{height:12px;background:#edf2ee;border-radius:999px;overflow:hidden}.lq-launch-progress span{display:block;height:100%;background:#2b9a69;border-radius:999px}.lq-launch-steps{display:grid;gap:12px;margin-top:22px}.lq-launch-step{display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:12px;align-items:center;background:#fff;border:1px solid var(--lq-border);border-radius:12px;padding:14px}.lq-launch-step b{width:34px;height:34px;border-radius:999px;display:grid;place-items:center;background:#fff1f5;color:var(--lq-pink)}.lq-launch-step.done b{background:#e9f9ef;color:#137a3a}.lq-launch-step h3{margin:0;font-size:15px}.lq-launch-step p{margin:4px 0 0;color:var(--lq-muted);font-size:13px}@media(max-width:960px){.lq-launch-hero{grid-template-columns:1fr}.lq-launch-step{grid-template-columns:34px minmax(0,1fr)}}</style>
</head>
<body class="lq-portal"><div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="cover.php">Public site</a><a class="lq-upgrade" href="runtime-diagnostics.php">Diagnostics</a><a class="lq-upgrade" href="api-examples.php">API Examples</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link active" href="start.php"><span class="lq-side-icon">▶</span><span class="lq-side-label">Launcher</span></a><a class="lq-side-link" href="runtime-diagnostics.php"><span class="lq-side-icon">✓</span><span class="lq-side-label">Diagnostics</span></a><a class="lq-side-link" href="index.php"><span class="lq-side-icon">⌂</span><span class="lq-side-label">Quest board</span></a><a class="lq-side-link" href="wallet.php"><span class="lq-side-icon">◉</span><span class="lq-side-label">Wallet</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhooks</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a></aside>
<main class="lq-main">
<section class="lq-launch-hero"><div class="lq-launch-big"><span class="lq-eyebrow">Production foundation</span><h1>Verify the full Local Quest lifecycle.</h1><p>Use this protected developer console to validate installation, participant identity, Microgifter account linking, quest actions, reward issue, wallet claim reporting, signed webhooks, and operational readiness.</p><div class="lq-actions"><a class="lq-btn primary" href="runtime-diagnostics.php">Run diagnostics</a><a class="lq-btn soft" href="developer-starter.php">Developer Starter</a><a class="lq-btn soft" href="cover.php">View public landing page</a></div></div><div class="lq-launch-card"><h2>Launch progress</h2><p><?= number_format($done) ?> of <?= number_format(count($steps)) ?> checks are complete.</p><div class="lq-launch-progress"><span style="width:<?= count($steps) ? (int)round(($done / count($steps)) * 100) : 0 ?>%"></span></div><div class="lq-row"><span>Participant</span><strong><?= lqr_h((string)($user['email'] ?? 'Not signed in')) ?></strong></div><div class="lq-row"><span>Rewards</span><strong><?= number_format($rewardCount) ?></strong></div><div class="lq-row"><span>Webhook deliveries</span><strong><?= number_format($webhookCount) ?></strong></div></div></section>
<section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Guided validation path</h2><p>Complete these in order before partner handoff or deployment.</p></div><span class="lq-pill <?= $done === count($steps) ? 'green' : 'amber' ?>"><?= number_format($done) ?>/<?= number_format(count($steps)) ?></span></div><div class="lq-launch-steps"><?php foreach($steps as $index => $step): ?><article class="lq-launch-step <?= !empty($step['done']) ? 'done' : '' ?>"><b><?= !empty($step['done']) ? '✓' : ($index + 1) ?></b><div><h3><?= lqr_h($step['label']) ?></h3><p><?= lqr_h($step['detail']) ?></p></div><a class="lq-btn soft" href="<?= lqr_h($step['href']) ?>">Open</a></article><?php endforeach; ?></div></section>
</main></div></body></html>
