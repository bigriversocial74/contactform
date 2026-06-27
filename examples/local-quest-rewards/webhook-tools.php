<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

$config = lqr_config();
$secret = lqr_config_value($config, 'webhook_secret');
$appUrl = rtrim((string)($config['app_public_url'] ?? 'http://127.0.0.1:8090'), '/');
$webhookUrl = $appUrl . '/webhook.php';
$event = trim((string)($_POST['event'] ?? 'webhook.test')) ?: 'webhook.test';
$delivery = trim((string)($_POST['delivery'] ?? 'del_lqr_demo_1001')) ?: 'del_lqr_demo_1001';
$rewardId = trim((string)($_POST['reward_id'] ?? 'reward_demo_1001')) ?: 'reward_demo_1001';
$itemId = trim((string)($_POST['item_id'] ?? 'item_demo_1001')) ?: 'item_demo_1001';
$externalUserId = trim((string)($_POST['external_user_id'] ?? 'local-quest-player-1001')) ?: 'local-quest-player-1001';
$timestamp = (string)time();
$payload = [
    'event' => $event,
    'reward_id' => $rewardId,
    'item_id' => $itemId,
    'external_user_id' => $externalUserId,
    'status' => $event === 'reward.redeemed' ? 'redeemed' : ($event === 'reward.claimed_in_app' ? 'claimed_in_app' : 'delivered'),
    'metadata' => [
        'app' => 'local-quest-rewards',
        'source' => 'webhook-tools.php',
        'generated_at' => gmdate('c'),
    ],
];
$body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($body)) $body = '{}';
$signature = '';
if ($secret !== '' && !str_contains($secret, 'replace_with') && !str_contains($secret, 'replace_me')) {
    $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
}
$curl = "curl -i " . escapeshellarg($webhookUrl) . " \\\n  -X POST \\\n  -H " . escapeshellarg('Content-Type: application/json') . " \\\n  -H " . escapeshellarg('X-Microgifter-Event: ' . $event) . " \\\n  -H " . escapeshellarg('X-Microgifter-Delivery: ' . $delivery) . " \\\n  -H " . escapeshellarg('X-Microgifter-Timestamp: ' . $timestamp) . " \\\n  -H " . escapeshellarg('X-Microgifter-Signature-Version: v1') . " \\\n  -H " . escapeshellarg('X-Microgifter-Signature: ' . ($signature ?: 'sha256=replace_with_generated_signature')) . " \\\n  --data-binary @payload.json";
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Webhook Test Tools | Local Quest Rewards</title>
<link rel="stylesheet" href="assets/portal.css">
<style>
.lq-webhook-tool-hero{background:linear-gradient(135deg,#211816,#3b1022);color:#fff;border-radius:14px;padding:28px;margin-bottom:22px}.lq-webhook-tool-hero h1{font-size:clamp(34px,5vw,58px);line-height:.95;letter-spacing:-.07em;margin:10px 0}.lq-webhook-tool-hero p{color:#ffe6ef;max-width:940px}.lq-tool-grid{display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:18px}.lq-tool-code{background:#071225;color:#eef6ff;border-radius:10px;padding:16px;overflow:auto;font-size:12px;line-height:1.55;white-space:pre-wrap}.lq-tool-warning{border:1px solid #fed7aa;background:#fff7ed;color:#c2410c;border-radius:10px;padding:12px;margin-bottom:14px}@media(max-width:960px){.lq-tool-grid{grid-template-columns:1fr}}
</style>
</head>
<body class="lq-portal">
<div class="lq-shell">
<header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="runtime-diagnostics.php">Diagnostics</a><a class="lq-upgrade" href="webhook.php">Webhook Status</a></div></header>
<aside class="lq-sidebar"><a class="lq-side-link" href="start.php"><span class="lq-side-icon">▶</span><span class="lq-side-label">Launcher</span></a><a class="lq-side-link" href="runtime-diagnostics.php"><span class="lq-side-icon">✓</span><span class="lq-side-label">Diagnostics</span></a><a class="lq-side-link active" href="webhook-tools.php"><span class="lq-side-icon">◇</span><span class="lq-side-label">Webhook Tools</span></a><a class="lq-side-link" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a></aside>
<main class="lq-main">
<section class="lq-webhook-tool-hero"><span class="lq-eyebrow">Webhook lab</span><h1>Generate signed test payloads.</h1><p>Build sample Microgifter webhook payloads and matching signature headers for local validation. This does not send the webhook; it gives you a payload and cURL command to run from your terminal.</p><div class="lq-actions"><a class="lq-btn primary" href="webhook.php">Open webhook status</a><a class="lq-btn soft" href="admin-developer-readiness.php">Admin readiness</a></div></section>
<?php if ($signature === ''): ?><div class="lq-tool-warning">Webhook signing value is not configured. The command below uses a placeholder signature until config.php has a real rotated signing value.</div><?php endif; ?>
<section class="lq-tool-grid"><div class="lq-card"><div class="lq-card-head"><div><h2>Payload settings</h2><p>Use values from a real reward/status response when testing reconciliation.</p></div><span class="lq-pill <?= $signature !== '' ? 'green' : 'amber' ?>"><?= $signature !== '' ? 'Signed' : 'Unsigned' ?></span></div><form method="post"><label class="lq-label">Event</label><select class="lq-select" name="event"><option <?= $event==='webhook.test'?'selected':'' ?>>webhook.test</option><option <?= $event==='reward.delivered'?'selected':'' ?>>reward.delivered</option><option <?= $event==='reward.claimed_in_app'?'selected':'' ?>>reward.claimed_in_app</option><option <?= $event==='reward.redeemed'?'selected':'' ?>>reward.redeemed</option><option <?= $event==='reward.failed'?'selected':'' ?>>reward.failed</option></select><label class="lq-label">Delivery ID</label><input class="lq-input" name="delivery" value="<?= lqr_h($delivery) ?>"><label class="lq-label">Reward ID</label><input class="lq-input" name="reward_id" value="<?= lqr_h($rewardId) ?>"><label class="lq-label">Item ID</label><input class="lq-input" name="item_id" value="<?= lqr_h($itemId) ?>"><label class="lq-label">External user ID</label><input class="lq-input" name="external_user_id" value="<?= lqr_h($externalUserId) ?>"><button class="lq-btn primary" style="width:100%;margin-top:14px">Generate payload</button></form></div><div class="lq-card"><h2>Generated headers</h2><pre class="lq-tool-code">X-Microgifter-Event: <?= lqr_h($event) ?>
X-Microgifter-Delivery: <?= lqr_h($delivery) ?>
X-Microgifter-Timestamp: <?= lqr_h($timestamp) ?>
X-Microgifter-Signature-Version: v1
X-Microgifter-Signature: <?= lqr_h($signature ?: 'sha256=replace_with_generated_signature') ?></pre><h2 style="margin-top:18px">payload.json</h2><pre class="lq-tool-code"><?= lqr_h($body) ?></pre><h2 style="margin-top:18px">Terminal test</h2><pre class="lq-tool-code"><?= lqr_h("cat > payload.json <<'JSON'\n" . $body . "\nJSON\n\n" . $curl) ?></pre></div></section>
</main>
</div>
</body>
</html>
