<?php
declare(strict_types=1);
define('LQR_SKIP_CSRF', true);
require __DIR__ . '/app.php';
require __DIR__ . '/webhook-reconcile.php';

$config = lqr_config();

function lqr_webhook_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function lqr_webhook_recent_entries(int $limit = 10): array
{
    $path = __DIR__ . '/webhook-events.log';
    if (!is_file($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    $lines = array_reverse(array_slice($lines, -1 * max(1, $limit)));
    $entries = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        $entries[] = is_array($decoded) ? $decoded : ['body' => $line];
    }
    return $entries;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $entries = lqr_webhook_recent_entries(10);
    $endpoint = rtrim((string)($config['app_public_url'] ?? ''), '/') . '/webhook.php';
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Webhook Status | Local Quest Rewards</title><link rel="stylesheet" href="assets/portal.css"><style>.lq-webhook-code{background:#101828;color:#f8fafc;border-radius:8px;padding:14px;overflow:auto;font-size:12px;line-height:1.55;white-space:pre-wrap}.lq-webhook-entry{border:1px solid var(--lq-border);border-radius:8px;background:#fff;padding:14px;display:grid;gap:8px}.lq-webhook-entry pre{margin:0;background:#f7f8fb;border-radius:6px;padding:12px;overflow:auto;font-size:12px;white-space:pre-wrap}</style></head><body class="lq-portal"><div class="lq-shell"><header class="lq-topbar"><div class="lq-brand"><span class="lq-logo">LQ</span><span>Local Quest</span></div><div class="lq-top-actions"><a class="lq-upgrade" href="developer-starter.php">Developer Starter</a><a class="lq-upgrade" href="app-console-admin.php">App Console</a><a class="lq-upgrade" href="index.php">Quest Board</a></div></header><aside class="lq-sidebar"><a class="lq-side-link" href="index.php"><span class="lq-side-icon">⌂</span><span class="lq-side-label">Quest board</span></a><a class="lq-side-link" href="wallet.php"><span class="lq-side-icon">◉</span><span class="lq-side-label">Wallet</span></a><a class="lq-side-link" href="developer-starter.php"><span class="lq-side-icon">API</span><span class="lq-side-label">Developer Starter</span></a><a class="lq-side-link active" href="webhook.php"><span class="lq-side-icon">◷</span><span class="lq-side-label">Webhook status</span></a></aside><main class="lq-main"><section class="lq-page-head"><span class="lq-eyebrow">Webhook Receiver</span><h1>Signed webhook status</h1><p>Use this endpoint in the merchant Developer API workspace. POST deliveries skip Local Quest form CSRF and are verified with Microgifter signature headers.</p></section><div class="lq-kpis"><div class="lq-kpi"><span>Endpoint</span><strong><?= lqr_h($endpoint !== '/webhook.php' ? 'Set' : 'Local') ?></strong></div><div class="lq-kpi"><span>Recent deliveries</span><strong><?= number_format(count($entries)) ?></strong></div><div class="lq-kpi"><span>Signature version</span><strong>v1</strong></div><div class="lq-kpi"><span>Window</span><strong>5m</strong></div></div><section class="lq-card"><div class="lq-card-head"><div><h2>Configure callback</h2><p>Paste this callback into the merchant Developer API webhook configuration and rotate the signing value into config.php.</p></div><span class="lq-pill <?= lqr_config_value($config, 'webhook_secret') !== '' && !str_contains(lqr_config_value($config, 'webhook_secret'), 'replace_with') ? 'green' : 'amber' ?>"><?= lqr_config_value($config, 'webhook_secret') !== '' && !str_contains(lqr_config_value($config, 'webhook_secret'), 'replace_with') ? 'Signing value configured' : 'Signing value missing' ?></span></div><div class="lq-webhook-code">Webhook URL: <?= lqr_h($endpoint) ?>
Expected base string: &lt;timestamp&gt;.&lt;raw request body&gt;
Expected signature: sha256=&lt;HMAC SHA-256 hex digest&gt;
Required headers:
  X-Microgifter-Event
  X-Microgifter-Delivery
  X-Microgifter-Timestamp
  X-Microgifter-Signature
  X-Microgifter-Signature-Version</div></section><section class="lq-card" style="margin-top:22px"><div class="lq-card-head"><div><h2>Recent deliveries</h2><p>Latest webhook-events.log entries. Verified deliveries are reconciled into local wallet state when reward or item IDs match.</p></div><a class="lq-btn soft" href="webhook-events.log">Raw log</a></div><div class="lq-stack"><?php if (!$entries): ?><div class="lq-webhook-entry"><strong>No deliveries recorded.</strong><span class="lq-meta">Send webhook.test from the merchant Developer API workspace.</span></div><?php endif; ?><?php foreach ($entries as $entry): ?><div class="lq-webhook-entry"><div class="lq-actions"><span class="lq-pill <?= !empty($entry['verified']) ? 'green' : 'amber' ?>"><?= !empty($entry['verified']) ? 'Verified' : 'Rejected' ?></span><strong><?= lqr_h((string)($entry['event'] ?? 'webhook')) ?></strong><span class="lq-meta"><?= lqr_h((string)($entry['received_at'] ?? '')) ?></span></div><pre><?= lqr_h(json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></div><?php endforeach; ?></div></section></main></div></body></html><?php
    exit;
}

$secret = lqr_config_value($config, 'webhook_secret');
$body = file_get_contents('php://input') ?: '';
$timestamp = lqr_webhook_header('X-Microgifter-Timestamp');
$signature = lqr_webhook_header('X-Microgifter-Signature');
$event = lqr_webhook_header('X-Microgifter-Event');
$delivery = lqr_webhook_header('X-Microgifter-Delivery');
$version = lqr_webhook_header('X-Microgifter-Signature-Version');
$verified = false;

if ($secret !== '' && !str_contains($secret, 'replace_with') && $timestamp !== '' && $signature !== '') {
    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $verified = abs(time() - (int)$timestamp) <= 300 && hash_equals($expected, $signature);
}

$decodedBody = json_decode($body, true) ?: $body;
$entry = [
    'received_at' => gmdate('c'),
    'verified' => $verified,
    'event' => $event,
    'delivery' => $delivery,
    'timestamp' => $timestamp,
    'signature_version' => $version,
    'body' => $decodedBody,
];

file_put_contents(__DIR__ . '/webhook-events.log', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);

$state = lqr_load_state();
lqr_add_event($state, $verified ? 'webhook.verified' : 'webhook.rejected', $event !== '' ? 'Webhook received: ' . $event : 'Webhook received.', [
    'delivery' => $delivery,
    'verified' => $verified,
]);

if ($verified && is_array($decodedBody)) {
    lqr_app_console_note_webhook($state);
    lqr_reconcile_microgifter_webhook($state, $event, $decodedBody, $delivery);
}

lqr_save_state($state);

if (!$verified) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Invalid Microgifter webhook signature.']);
    exit;
}

http_response_code(204);
