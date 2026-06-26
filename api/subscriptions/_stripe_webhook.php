<?php
declare(strict_types=1);

require_once __DIR__ . '/_stripe_activation.php';
require_once dirname(__DIR__) . '/payments/_payments.php';

final class MgSubscriptionStripeWebhookException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_subscription_stripe_webhook_object(array $event): array
{
    $data = $event['data'] ?? [];
    if (is_array($data) && is_array($data['object'] ?? null)) return $data['object'];
    return is_array($data) ? $data : [];
}

function mg_subscription_stripe_webhook_identifiers(array $event): array
{
    $object = mg_subscription_stripe_webhook_object($event);
    $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
    $providerIntent = '';
    $paymentIntent = $object['payment_intent'] ?? '';
    if (is_array($paymentIntent)) $providerIntent = trim((string)($paymentIntent['id'] ?? ''));
    else $providerIntent = trim((string)$paymentIntent);
    return [
        'object' => $object,
        'metadata' => $metadata,
        'order_id' => trim((string)($metadata['order_id'] ?? $object['client_reference_id'] ?? '')),
        'payment_intent_id' => trim((string)($metadata['payment_intent_id'] ?? '')),
        'checkout_session_id' => trim((string)($metadata['checkout_session_id'] ?? '')),
        'provider_intent_reference' => $providerIntent,
        'provider_session_reference' => trim((string)($object['id'] ?? '')),
        'amount_cents' => (int)($object['amount_total'] ?? 0),
        'currency' => strtoupper(trim((string)($object['currency'] ?? ''))),
        'payment_status' => trim((string)($object['payment_status'] ?? '')),
        'failure_code' => '',
        'failure_message' => '',
        'application_fee_cents' => 0,
    ];
}

function mg_subscription_stripe_process_webhook_event(PDO $pdo, array $event, string $payload): array
{
    $eventId = trim((string)($event['id'] ?? ''));
    $type = trim((string)($event['type'] ?? ''));
    if ($eventId === '' || $type === '') throw new MgSubscriptionStripeWebhookException('Invalid Stripe webhook event.', 422);
    $payloadHash = hash('sha256', $payload);

    $existingStmt = $pdo->prepare('SELECT signature_valid,status,payload_hash,event_type FROM payment_webhook_events WHERE provider_key=? AND provider_event_id=? LIMIT 1 FOR UPDATE');
    $existingStmt->execute(['stripe', $eventId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $same = (int)$existing['signature_valid'] === 1 && hash_equals((string)$existing['payload_hash'], $payloadHash) && hash_equals((string)$existing['event_type'], $type);
        if (!$same) throw new MgSubscriptionStripeWebhookException('Webhook event conflicts with an existing signed payload.', 409);
        if (in_array((string)$existing['status'], ['processed','ignored'], true)) return ['duplicate' => true, 'status' => (string)$existing['status'], 'processed' => (string)$existing['status'] === 'processed'];
        $pdo->prepare("UPDATE payment_webhook_events SET status='processing',failure_message=NULL,received_at=NOW() WHERE provider_key=? AND provider_event_id=?")->execute(['stripe', $eventId]);
    } else {
        $pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'processing',?,?,NOW())")
            ->execute([mg_public_uuid(), 'stripe', $eventId, $type, $payloadHash, $payload]);
    }

    $processed = false;
    $requestId = null;
    $accepted = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];
    if (in_array($type, $accepted, true)) {
        $ids = mg_subscription_stripe_webhook_identifiers($event);
        $activation = mg_subscription_webhook_activate_package_change($pdo, 'stripe', $event, $ids);
        if ($activation !== null) {
            $processed = (bool)($activation['processed'] ?? false);
            $requestId = $activation['subscription_request_id'] ?? null;
        }
    }

    $status = $processed ? 'processed' : 'ignored';
    $pdo->prepare('UPDATE payment_webhook_events SET status=?,processed_at=NOW(),failure_message=NULL WHERE provider_key=? AND provider_event_id=?')->execute([$status, 'stripe', $eventId]);
    return ['duplicate' => false, 'status' => $status, 'processed' => $processed, 'subscription_request_id' => $requestId, 'event_type' => $type];
}
