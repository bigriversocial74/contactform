<?php
declare(strict_types=1);
define('LQR_SKIP_CSRF', true);
require __DIR__ . '/app.php';
require __DIR__ . '/webhook-reconcile.php';
require __DIR__ . '/webhook-storage.php';

$config = lqr_config();

function lqr_webhook_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    require __DIR__ . '/admin-auth.php';
    $state = lqr_load_state();
    if (!lqr_admin_is_authed() || !lqr_admin_current($state, $config)) {
        header('Location: admin-credentials.php');
        exit;
    }

    $pdo = lqr_sql_db($config);
    $entries = lqr_webhook_recent_deliveries($pdo, 20);
    $verifiedCount = lqr_webhook_delivery_count($pdo, true);
    $endpoint = rtrim((string)($config['app_public_url'] ?? ''), '/') . '/webhook.php';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Webhook Operations | Local Quest Rewards</title>
    <link rel="stylesheet" href="assets/portal.css">
    <style>.lq-webhook-code{background:#101828;color:#f8fafc;border-radius:10px;padding:16px;overflow:auto;font-size:12px;line-height:1.6;white-space:pre-wrap}.lq-webhook-entry{border:1px solid var(--lq-border);border-radius:12px;background:#fff;padding:16px;display:grid;gap:10px}.lq-webhook-entry pre{margin:0;background:#f7f8fb;border-radius:8px;padding:12px;overflow:auto;font-size:12px;white-space:pre-wrap}.lq-webhook-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}</style>
    </head>
    <body class="lq-portal">
    <div class="lq-shell">
    <header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="admin.php">Admin</a><a class="lq-upgrade" href="runtime-diagnostics.php">Diagnostics</a><a class="lq-upgrade" href="cover.php">Public site</a></div></header>
    <aside class="lq-sidebar"><a class="lq-side-link" href="admin.php"><span class="lq-side-icon">⚙</span><span class="lq-side-label">Admin</span></a><a class="lq-side-link active" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhooks</span></a><a class="lq-side-link" href="webhook-tools.php"><span class="lq-side-icon">◇</span><span class="lq-side-label">Test tools</span></a><a class="lq-side-link" href="admin-developer-readiness.php"><span class="lq-side-icon">QA</span><span class="lq-side-label">Readiness</span></a></aside>
    <main class="lq-main">
      <section class="lq-page-head"><span class="lq-eyebrow">Protected operations</span><h1>Signed webhook deliveries</h1><p>Webhook payloads are stored in the application database and are visible only to authenticated administrators.</p></section>
      <div class="lq-kpis"><div class="lq-kpi"><span>Endpoint</span><strong><?= lqr_h($endpoint !== '/webhook.php' ? 'Configured' : 'Local') ?></strong></div><div class="lq-kpi"><span>Stored deliveries</span><strong><?= number_format(lqr_webhook_delivery_count($pdo)) ?></strong></div><div class="lq-kpi"><span>Verified</span><strong><?= number_format($verifiedCount) ?></strong></div><div class="lq-kpi"><span>Signature window</span><strong>5 min</strong></div></div>
      <section class="lq-card"><div class="lq-card-head"><div><h2>Receiver configuration</h2><p>Use this HTTPS callback in the Microgifter Developer API workspace.</p></div><span class="lq-pill <?= lqr_config_value($config, 'webhook_secret') !== '' && !str_contains(lqr_config_value($config, 'webhook_secret'), 'replace_with') ? 'green' : 'amber' ?>"><?= lqr_config_value($config, 'webhook_secret') !== '' && !str_contains(lqr_config_value($config, 'webhook_secret'), 'replace_with') ? 'Signing value configured' : 'Signing value missing' ?></span></div><div class="lq-webhook-code">Webhook URL: <?= lqr_h($endpoint) ?>
Expected base string: &lt;timestamp&gt;.&lt;raw request body&gt;
Expected signature: sha256=&lt;HMAC SHA-256 hex digest&gt;
Required headers:
  X-Microgifter-Event
  X-Microgifter-Delivery
  X-Microgifter-Timestamp
  X-Microgifter-Signature
  X-Microgifter-Signature-Version</div></section>
      <section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Recent deliveries</h2><p>Duplicate delivery IDs remain idempotent and do not run wallet reconciliation twice.</p></div><a class="lq-btn soft" href="webhook-tools.php">Generate test payload</a></div><div class="lq-stack"><?php if (!$entries): ?><div class="lq-webhook-entry"><strong>No deliveries recorded.</strong><span class="lq-meta">Send a signed webhook test from Microgifter or the local webhook tools page.</span></div><?php endif; ?><?php foreach ($entries as $entry): ?><article class="lq-webhook-entry"><div class="lq-webhook-meta"><span class="lq-pill <?= !empty($entry['verified']) ? 'green' : 'amber' ?>"><?= !empty($entry['verified']) ? 'Verified' : 'Rejected' ?></span><span class="lq-pill <?= !empty($entry['reconciled']) ? 'green' : 'amber' ?>"><?= !empty($entry['reconciled']) ? 'Reconciled' : 'No match' ?></span><strong><?= lqr_h((string)$entry['event_type']) ?></strong><span class="lq-meta"><?= lqr_h((string)$entry['received_at']) ?></span></div><div class="lq-meta">Delivery <?= lqr_h((string)$entry['delivery_id']) ?><?php if(!empty($entry['reward_id'])): ?> · Reward <?= lqr_h((string)$entry['reward_id']) ?><?php endif; ?><?php if(!empty($entry['item_id'])): ?> · Item <?= lqr_h((string)$entry['item_id']) ?><?php endif; ?></div><pre><?= lqr_h(json_encode($entry['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></article><?php endforeach; ?></div></section>
    </main>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$secret = lqr_config_value($config, 'webhook_secret');
$body = file_get_contents('php://input') ?: '';
$timestamp = lqr_webhook_header('X-Microgifter-Timestamp');
$signature = lqr_webhook_header('X-Microgifter-Signature');
$event = lqr_webhook_header('X-Microgifter-Event');
$delivery = lqr_webhook_header('X-Microgifter-Delivery');
$version = lqr_webhook_header('X-Microgifter-Signature-Version');

if ($delivery === '') $delivery = 'missing_' . substr(hash('sha256', $timestamp . '|' . $body), 0, 32);
$verified = false;
if ($secret !== '' && !str_contains($secret, 'replace_with') && $timestamp !== '' && $signature !== '' && $version === 'v1') {
    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $verified = ctype_digit($timestamp) && abs(time() - (int)$timestamp) <= 300 && hash_equals($expected, $signature);
}

$decodedBody = json_decode($body, true);
$payload = is_array($decodedBody) ? $decodedBody : ['raw' => $body];
$pdo = lqr_sql_db($config);
$isDuplicate = lqr_webhook_delivery_exists($pdo, $delivery);
$reconcile = ['matched' => []];

if ($verified && !$isDuplicate) {
    $state = lqr_load_state();
    lqr_add_event($state, 'webhook.verified', $event !== '' ? 'Webhook received: ' . $event : 'Webhook received.', ['delivery' => $delivery, 'verified' => true]);
    lqr_app_console_note_webhook($state);
    $reconcile = lqr_reconcile_microgifter_webhook($state, $event, $payload, $delivery);
    lqr_save_state($state);
}

lqr_webhook_store_delivery($pdo, [
    'delivery_id' => $delivery,
    'event_type' => $event !== '' ? $event : 'webhook.unknown',
    'verified' => $verified,
    'reconciled' => !empty($reconcile['matched']),
    'reward_id' => (string)($reconcile['reward_id'] ?? lqr_webhook_value($payload, ['reward_id', 'reward.id', 'id'])),
    'item_id' => (string)($reconcile['item_id'] ?? lqr_webhook_value($payload, ['item_id', 'pppm_item_id', 'item.id'])),
    'payload' => ['signature_version' => $version, 'timestamp' => $timestamp, 'body' => $payload],
]);

if (!$verified) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Invalid Microgifter webhook signature.']);
    exit;
}

http_response_code(204);
