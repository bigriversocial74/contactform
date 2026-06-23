<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/webhook-reconcile.php';

$config = lqr_config();
$secret = lqr_config_value($config, 'webhook_secret');
$body = file_get_contents('php://input') ?: '';

function lqr_webhook_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

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
