<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/admin-auth.php';
require __DIR__ . '/webhook-storage.php';

$config = lqr_config();
$state = lqr_load_state();
$admin = lqr_admin_current($state, $config);
if (!lqr_admin_is_authed() || !$admin) {
    header('Location: admin-credentials.php');
    exit;
}

function lqadr_config_ok(array $config, string $key): bool
{
    $value = lqr_config_value($config, $key);
    return $value !== '' && !str_contains($value, 'replace_me') && !str_contains($value, 'replace_with');
}

$users = is_array($state['users'] ?? null) ? $state['users'] : [];
$linked = 0;
$completions = 0;
$rewards = 0;
$reportedClaims = 0;
$retryClaims = 0;
$webhookSynced = 0;
$claimEvidence = [];
foreach ($users as $user) {
    if (!is_array($user)) continue;
    if (!empty($user['linked_account_id'])) $linked++;
    $completions += count(is_array($user['completed_quests'] ?? null) ? $user['completed_quests'] : []);
    foreach ((array)($user['rewards'] ?? []) as $questId => $reward) {
        if (!is_array($reward)) continue;
        $rewards++;
        if (in_array((string)($reward['claim_report_status'] ?? ''), ['reported_to_microgifter','confirmed_by_microgifter_webhook'], true)) $reportedClaims++;
        if (!empty($reward['claim_retry_available'])) $retryClaims++;
        if (!empty($reward['last_webhook_delivery'])) $webhookSynced++;
        if (!empty($reward['claim_report_status']) || !empty($reward['last_webhook_delivery'])) {
            $claimEvidence[] = [
                'user' => (string)($user['email'] ?? $user['id'] ?? ''),
                'quest' => (string)$questId,
                'reward_id' => (string)($reward['reward_id'] ?? ''),
                'report_status' => (string)($reward['claim_report_status'] ?? 'not_reported'),
                'webhook_delivery' => (string)($reward['last_webhook_delivery'] ?? ''),
            ];
        }
    }
}
$claimEvidence = array_slice(array_reverse($claimEvidence), 0, 10);

$pdo = lqr_sql_db($config);
$webhookCount = lqr_webhook_delivery_count($pdo);
$verifiedWebhooks = lqr_webhook_delivery_count($pdo, true);
$recentWebhooks = lqr_webhook_recent_deliveries($pdo, 8);
$schemaVersion = '';
try {
    $schemaVersion = (string)$pdo->query('SELECT version_key FROM lqr_schema_versions ORDER BY applied_at DESC,id DESC LIMIT 1')->fetchColumn();
} catch (Throwable) {}

$checks = [
    ['label'=>'Installer locked','ok'=>is_file(__DIR__ . '/.installed.lock') && !is_file(__DIR__ . '/.install-unlock'),'detail'=>'config.php and installer lock are present; no temporary unlock remains.'],
    ['label'=>'Schema version recorded','ok'=>$schemaVersion !== '','detail'=>$schemaVersion ?: 'No production schema version found.'],
    ['label'=>'Public URL configured','ok'=>lqadr_config_ok($config, 'app_public_url'),'detail'=>(string)($config['app_public_url'] ?? '')],
    ['label'=>'Bearer credential configured','ok'=>lqadr_config_ok($config, 'api_key'),'detail'=>'Server-side Developer API credential is present.'],
    ['label'=>'Default program configured','ok'=>lqadr_config_ok($config, 'default_program_id'),'detail'=>(string)($config['default_program_id'] ?? '')],
    ['label'=>'Default template configured','ok'=>lqadr_config_ok($config, 'default_template_id'),'detail'=>(string)($config['default_template_id'] ?? '')],
    ['label'=>'Webhook signing configured','ok'=>lqadr_config_ok($config, 'webhook_secret'),'detail'=>'Rotated signing value is present.'],
    ['label'=>'Participants exist','ok'=>count($users) > 0,'detail'=>number_format(count($users)) . ' participant accounts.'],
    ['label'=>'Linked accounts exist','ok'=>$linked > 0,'detail'=>number_format($linked) . ' linked participants.'],
    ['label'=>'Quest completions exist','ok'=>$completions > 0,'detail'=>number_format($completions) . ' completed quest actions.'],
    ['label'=>'Rewards issued','ok'=>$rewards > 0,'detail'=>number_format($rewards) . ' reward records.'],
    ['label'=>'Claims reported','ok'=>$reportedClaims > 0,'detail'=>number_format($reportedClaims) . ' claims reported or confirmed.'],
    ['label'=>'Claim retry queue clear','ok'=>$retryClaims === 0,'detail'=>number_format($retryClaims) . ' claims need retry.'],
    ['label'=>'Verified webhooks received','ok'=>$verifiedWebhooks > 0,'detail'=>number_format($verifiedWebhooks) . ' of ' . number_format($webhookCount) . ' deliveries verified.'],
];
$done = count(array_filter($checks, static fn(array $check): bool => !empty($check['ok'])));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Launch Readiness | Local Quest Rewards</title><link rel="stylesheet" href="assets/portal.css"><style>.lq-ready-hero{background:linear-gradient(135deg,#0f2f25,#18583f);color:#fff;border-radius:18px;padding:30px;margin-bottom:22px}.lq-ready-hero h1{font-size:clamp(34px,5vw,58px);line-height:.95;letter-spacing:-.07em;margin:10px 0}.lq-ready-hero p{color:#d5e8de;max-width:920px}.lq-ready-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.lq-ready-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid var(--lq-border)}.lq-ready-evidence{font-size:12px;background:#f7f8fb;border-radius:9px;padding:11px;overflow:auto}@media(max-width:900px){.lq-ready-grid{grid-template-columns:1fr}.lq-ready-row{grid-template-columns:1fr}}</style></head><body class="lq-portal"><div class="lq-shell"><header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="admin.php">Admin</a><a class="lq-upgrade" href="runtime-diagnostics.php">Diagnostics</a><a class="lq-upgrade" href="cover.php">Public site</a></div></header><aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link active" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a><a class="lq-side-link" href="admin-demo-tools.php"><span class="lq-side-icon">DB</span><span class="lq-side-label">Demo tools</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhooks</span></a></aside><main class="lq-main"><section class="lq-ready-hero"><span class="lq-eyebrow">Operational launch review</span><h1>Production readiness evidence.</h1><p>Review installation, configuration, participant activity, reward lifecycle, claim reporting, and signed webhook evidence before deployment or partner handoff.</p><div class="lq-actions"><span class="lq-pill green"><?= number_format($done) ?>/<?= number_format(count($checks)) ?> checks</span><a class="lq-btn soft" href="start.php">Launch console</a></div></section><div class="lq-kpis"><div class="lq-kpi"><span>Participants</span><strong><?= number_format(count($users)) ?></strong></div><div class="lq-kpi"><span>Linked</span><strong><?= number_format($linked) ?></strong></div><div class="lq-kpi"><span>Rewards</span><strong><?= number_format($rewards) ?></strong></div><div class="lq-kpi"><span>Reported claims</span><strong><?= number_format($reportedClaims) ?></strong></div></div><section class="lq-card"><div class="lq-card-head"><div><h2>Readiness checks</h2><p>Open items should be resolved before live reward distribution.</p></div><span class="lq-pill <?= $done === count($checks) ? 'green' : 'amber' ?>"><?= $done === count($checks) ? 'Ready' : 'Needs attention' ?></span></div><?php foreach($checks as $check): ?><div class="lq-ready-row"><div><strong><?= lqr_h($check['label']) ?></strong><div class="lq-meta"><?= lqr_h($check['detail']) ?></div></div><span class="lq-pill <?= !empty($check['ok']) ? 'green' : 'amber' ?>"><?= !empty($check['ok']) ? 'Pass' : 'Open' ?></span></div><?php endforeach; ?></section><div class="lq-ready-grid" style="margin-top:18px"><section class="lq-card"><h2>Claim evidence</h2><?php if(!$claimEvidence): ?><p>No claim evidence recorded yet.</p><?php endif; ?><?php foreach($claimEvidence as $row): ?><pre class="lq-ready-evidence"><?= lqr_h(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre><?php endforeach; ?></section><section class="lq-card"><h2>Recent webhook evidence</h2><?php if(!$recentWebhooks): ?><p>No SQL webhook deliveries recorded yet.</p><?php endif; ?><?php foreach($recentWebhooks as $row): ?><pre class="lq-ready-evidence"><?= lqr_h(json_encode(['delivery'=>$row['delivery_id'],'event'=>$row['event_type'],'verified'=>(bool)$row['verified'],'reconciled'=>(bool)$row['reconciled'],'received_at'=>$row['received_at']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre><?php endforeach; ?></section></div></main></div></body></html>
