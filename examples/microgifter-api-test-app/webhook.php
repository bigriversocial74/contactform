<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/config.example.php';
}
$config = require $configPath;
$secret = (string)($config['webhook_secret'] ?? '');
$body = file_get_contents('php://input') ?: '';

function mg_demo_server_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

$timestamp = mg_demo_server_header('X-Microgifter-Timestamp');
$signature = mg_demo_server_header('X-Microgifter-Signature');
$event = mg_demo_server_header('X-Microgifter-Event');
$delivery = mg_demo_server_header('X-Microgifter-Delivery');
$verified = false;

if ($secret !== '' && !str_starts_with($secret, 'replace_') && $timestamp !== '' && $signature !== '') {
    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $verified = abs(time() - (int)$timestamp) <= 300 && hash_equals($expected, $signature);
}

$entry = [
    'received_at' => gmdate('c'),
    'verified' => $verified,
    'event' => $event,
    'delivery' => $delivery,
    'timestamp' => $timestamp,
    'body' => json_decode($body, true) ?: $body,
];
file_put_contents(__DIR__ . '/webhook-events.log', json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

if (!$verified) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Invalid Microgifter webhook signature.']);
    exit;
}

http_response_code(204);
