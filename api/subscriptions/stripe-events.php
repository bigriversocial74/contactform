<?php
declare(strict_types=1);

require_once __DIR__ . '/_stripe_webhook.php';

mg_require_method('POST');

$payload = (string)(file_get_contents('php://input') ?: '');
$signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

try {
    if ($payload === '') throw new InvalidArgumentException('Event payload is required.');
    $pdo = mg_db();
    if (!mg_payment_verify_signature('stripe', $payload, $signature, $pdo)) throw new InvalidArgumentException('Invalid Stripe signature.');
    $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($event)) throw new InvalidArgumentException('Invalid event payload.');
    $result = mg_subscription_stripe_process_webhook_event($pdo, $event, $payload);
    mg_ok($result, 'Subscription Stripe event processed.');
} catch (InvalidArgumentException|JsonException $e) {
    mg_fail($e->getMessage(), 400);
} catch (Throwable $e) {
    mg_fail('Unable to process subscription Stripe event.', 500);
}
