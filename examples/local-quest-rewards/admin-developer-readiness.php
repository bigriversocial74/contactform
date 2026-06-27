<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
$state = lqr_load_state();
$quests = lqr_quests();
if (empty($_SESSION['lqr_admin_authed'])) {
    header('Location: admin.php');
    exit;
}

function lqadr_config_ok(array $config, string $key): bool
{
    $value = lqr_config_value($config, $key);
    return $value !== '' && !str_contains($value, 'replace_me') && !str_contains($value, 'replace_with');
}

function lqadr_webhooks(): array
{
    $path = __DIR__ . '/webhook-events.log';
    if (!is_file($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    $entries = [];
    foreach (array_reverse(array_slice($lines, -10)) as $line) {
        $decoded = json_decode($line, true);
        $entries[] = is_array($decoded) ? $decoded : ['body' => $line];
    }
    return $entries;
}

$users = is_array($state['users'] ?? null) ? $state['users'] : [];
$linked = 0; $completions = 0; $rewards = 0; $claims = 0; $reportedClaims = 0; $failedClaims = 0; $retryClaims = 0; $webhookSyncedClaims = 0;
$claimEvidence = [];
foreach ($users as $user) {
    if (!is_array($user)) continue;
    if (!empty($user['linked_account_id'])) $linked++;
    $completions += count(is_array($user['completed_quests'] ?? null) ? $user['completed_quests'] : []);
    foreach ((is_array($user['rewards'] ?? null) ? $user['rewards'] : []) as $questId => $reward) {
        if (!is_array($reward)) continue;
        $rewards++;
        if (($reward['claim_status'] ?? '') === 'claimed_in_quest_app') $claims++;
        if (in_array((string)($reward['claim_report_status'] ?? ''), ['reported_to_microgifter','confirmed_by_microgifter_webhook'], true)) $reportedClaims++;
        if (str_contains((string)($reward['claim_report_status'] ?? ''), 'failed')) $failedClaims++;
        if (!empty($reward['claim_retry_available'])) $retryClaims++;
        if (!empty($reward['last_webhook_delivery'])) $webhookSyncedClaims++;
        if (!empty($reward['claim_report_status']) || !empty($reward['last_webhook_delivery'])) {
            $claimEvidence[] = ['user'=>(string)($user['email'] ?? $user['id'] ?? ''), 'quest'=>(string)$questId, 'reward_id'=>(string)($reward['reward_id'] ?? ''), 'report_status'=>(string)($reward['claim_report_status'] ?? 'not_reported'), 'sync_status'=>(string)($reward['claim_sync_status'] ?? 'not_synced'), 'attempts'=>(int)($reward['claim_report_attempts'] ?? 0), 'last_report'=>(string)($reward['last_claim_report_at'] ?? ''), 'webhook_delivery'=>(string)($reward['last_webhook_delivery'] ?? '')];
        }
    }
}
$claimEvidence = array_slice(array_reverse($claimEvidence), 0, 8);
$webhooks = lqadr_webhooks();
$verifiedWebhooks = 0;
foreach ($webhooks as $hook) if (!empty($hook['verified'])) $verifiedWebhooks++;

$checks = [
    ['label'=>'Base URL configured','ok'=>lqadr_config_ok($config, 'base_url'),'detail'=>(string)($config['base_url'] ?? '')],
    ['label'=>'Bearer credential configured','ok'=>lqadr_config_ok($config, 'api_key'),'detail'=>'Server-side credential required.'],
    ['label'=>'Default program configured','ok'=>lqadr_config_ok($config, 'default_program_id'),'detail'=>(string)($config['default_program_id'] ?? '')],
    ['label'=>'Default template configured','ok'=>lqadr_config_ok($config, 'default_template_id'),'detail'=>(string)($config['default_template_id'] ?? '')],
    ['label'=>'Webhook signing value configured','ok'=>lqadr_config_ok($config, 'webhook_secret'),'detail'=>'Required for signed callback verification.'],
    ['label'=>'Participants exist','ok'=>count($users) > 0,'detail'=>number_format(count($users)) . ' local users.'],
    ['label'=>'Linked accounts exist','ok'=>$linked > 0,'detail'=>number_format($linked) . ' linked users.'],
    ['label'=>'Quest completions exist','ok'=>$completions > 0,'detail'=>number_format($completions) . ' completions.'],
    ['label'=>'Rewards issued','ok'=>$rewards > 0,'detail'=>number_format($rewards) . ' local reward records.'],
    ['label'=>'Claims reported','ok'=>$reportedClaims > 0,'detail'=>number_format($reportedClaims) . ' reported or webhook-confirmed claims.'],
    ['label'=>'Claim retry queue clear','ok'=>$retryClaims === 0,'detail'=>number_format($retryClaims) . ' claims need retry.'],
    ['label'=>'Webhook-synced claims','ok'=>$webhookSyncedClaims > 0,'detail'=>number_format($webhookSyncedClaims) . ' rewards have webhook sync evidence.'],
    ['label'=>'Verified webhooks received','ok'=>$verifiedWebhooks > 0,'detail'=>number_format($verifiedWebhooks) . ' verified recent webhooks.'],
];
$done = 0;
foreach ($checks as $check) if (!empty($check['ok'])) $done++;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Developer Readiness | Local Quest Rewards</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-readiness-score{background:linear-gradient(135deg,#211816,#3b1022);color:#fff;border-radius:14px;padding:26px;box-shadow:var(--lq-shadow)}.lq-readiness-score h1{font-size:clamp(34px,5vw,58px);line-height:.95;letter-spacing:-.07em;margin:10px 0}.lq-readiness-score p{color:#ffe6ef}.lq-readiness-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:22px 0}.lq-readiness-checks{display:grid;gap:12px}.lq-readiness-check{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;background:#fff;border:1px solid var(--lq-border);border-radius:10px;padding:14px}.lq-readiness-check h3{margin:0;font-size:15px}.lq-readiness-check p{margin:4px 0 0;color:var(--lq-muted);font-size:13px}.lq-webhook-mini{background:#fff;border:1px solid var(--lq-border);border-radius:10px;padding:14px}.lq-webhook-mini pre{background:#f7f8fb;border-radius:8px;padding:12px;overflow:auto;font-size:12px;white-space:pre-wrap}.lq-evidence-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}@media(max-width:960px){.lq-readiness-grid,.lq-evidence-grid{grid-template-columns:1fr 1fr}.lq-readiness-check{grid-template-columns:1fr}}@media(max-width:640px){.lq-readiness-grid,.lq-evidence-grid{grid-template-columns:1fr}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="admin.php">Admin</a><a class="lq-upgrade" href="wallet.php">Wallet</a><a class="lq-upgrade" href="developer-starter.php">Developer Starter</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link active" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a><a class="lq-side-link" href="admin-programs.php"><span class="lq-side-icon">BLD</span><span class="lq-side-label">Program Builder</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a></aside>
<main class="lq-main">
<section class="lq-readiness-score"><span class="lq-eyebrow">Admin QA</span><h1>Developer readiness review.</h1><p>Use this admin page to review whether Local Quest has completed the full reward lifecycle: issue, wallet claim report, retry state, webhook confirmation, and local sync evidence.</p><div class="lq-actions"><a class="lq-btn primary" href="wallet.php">Review wallet claims</a><a class="lq-btn soft" href="webhook.php">Webhook status</a></div></section>
<div class="lq-readiness-grid"><div class="lq-kpi"><span>Checks</span><strong><?= number_format($done) ?>/<?= number_format(count($checks)) ?></strong></div><div class="lq-kpi"><span>Reported</span><strong><?= number_format($reportedClaims) ?></strong></div><div class="lq-kpi"><span>Retry queue</span><strong><?= number_format($retryClaims) ?></strong></div><div class="lq-kpi"><span>Webhook synced</span><strong><?= number_format($webhookSyncedClaims) ?></strong></div></div>
<section class="lq-card"><div class="lq-card-head"><div><h2>Launch readiness checks</h2><p>These mirror the Local Quest demo flow and claim sync lifecycle.</p></div><span class="lq-pill <?= $done === count($checks) ? 'green' : 'amber' ?>"><?= number_format($done) ?>/<?= number_format(count($checks)) ?></span></div><div class="lq-readiness-checks"><?php foreach ($checks as $check): ?><article class="lq-readiness-check"><div><h3><?= lqr_h($check['label']) ?></h3><p><?= lqr_h($check['detail']) ?></p></div><span class="lq-pill <?= !empty($check['ok']) ? 'green' : 'amber' ?>"><?= !empty($check['ok']) ? 'Pass' : 'Open' ?></span></article><?php endforeach; ?></div></section>
<section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Claim sync evidence</h2><p>Latest claim reports, retry states, and webhook confirmations stored in Local Quest state.</p></div><a class="lq-btn soft" href="wallet.php">Open wallet</a></div><div class="lq-evidence-grid"><?php if (!$claimEvidence): ?><div class="lq-webhook-mini"><strong>No claim evidence yet.</strong><p>Claim a wallet reward to create claim-report evidence.</p></div><?php endif; ?><?php foreach ($claimEvidence as $evidence): ?><article class="lq-webhook-mini"><div class="lq-actions"><span class="lq-pill <?= $evidence['report_status']==='confirmed_by_microgifter_webhook'?'green':'amber' ?>"><?= lqr_h($evidence['report_status']) ?></span><strong><?= lqr_h($evidence['quest']) ?></strong></div><pre><?= lqr_h(json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></article><?php endforeach; ?></div></section>
<section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Recent webhook evidence</h2><p>Verified deliveries prove signing and reconciliation are working.</p></div><a class="lq-btn soft" href="webhook-events.log">Raw log</a></div><div class="lq-stack"><?php if (!$webhooks): ?><div class="lq-webhook-mini"><strong>No webhook evidence yet.</strong><p>Send a webhook.test from the merchant Developer API workspace.</p></div><?php endif; ?><?php foreach ($webhooks as $hook): ?><article class="lq-webhook-mini"><div class="lq-actions"><span class="lq-pill <?= !empty($hook['verified']) ? 'green' : 'amber' ?>"><?= !empty($hook['verified']) ? 'Verified' : 'Rejected' ?></span><strong><?= lqr_h((string)($hook['event'] ?? 'webhook')) ?></strong></div><pre><?= lqr_h(json_encode($hook, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></article><?php endforeach; ?></div></section>
</main>
</div>
</body>
</html>
