<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_webhook_v2.php';
require_once dirname(__DIR__) . '/payments/_payments.php';

final class MgSubscriptionStripeWebhookException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_subscription_stripe_history_reference(mixed $value): string
{
    if (is_array($value)) return trim((string)($value['id'] ?? ''));
    return trim((string)$value);
}

function mg_subscription_stripe_record_history(PDO $pdo, array $event, ?array $activation): void
{
    if (!$activation || empty($activation['processed'])) return;
    $publicId = trim((string)($activation['platform_account_subscription_id'] ?? ''));
    if ($publicId === '') return;

    $type = trim((string)($event['type'] ?? ''));
    if (!in_array($type, [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'invoice.paid',
        'invoice.payment_failed',
        'invoice.payment_action_required',
    ], true)) return;

    $object = mg_subscription_package_webhook_object($event);
    $stmt = $pdo->prepare('SELECT id,user_id,status,package_id,billing_cycle,amount_cents,currency FROM platform_account_subscriptions WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subscription) return;

    $invoice = str_starts_with($type, 'invoice.') ? $object : [];
    $eventType = match ($type) {
        'invoice.paid' => 'platform_subscription.payment_received',
        'invoice.payment_failed', 'invoice.payment_action_required' => 'platform_subscription.payment_attention_required',
        default => 'platform_subscription.checkout_completed',
    };
    $amountCents = (int)($object['amount_paid'] ?? $object['amount_due'] ?? $object['amount_total'] ?? $subscription['amount_cents'] ?? 0);
    $currency = strtoupper((string)($object['currency'] ?? $subscription['currency'] ?? 'USD'));
    $invoiceId = mg_subscription_stripe_history_reference($invoice['id'] ?? ($object['invoice'] ?? ''));
    $paymentIntentId = mg_subscription_stripe_history_reference($invoice['payment_intent'] ?? ($object['payment_intent'] ?? ''));

    mg_platform_account_subscription_event(
        $pdo,
        (int)$subscription['id'],
        $eventType,
        (string)($activation['from_status'] ?? $subscription['status']),
        (string)($activation['to_status'] ?? $subscription['status']),
        (int)$subscription['user_id'],
        [
            'provider_key' => 'stripe',
            'provider_event_id' => (string)($event['id'] ?? ''),
            'event_type' => $type,
            'package_id' => (string)($activation['package_id'] ?? $subscription['package_id']),
            'billing_cycle' => (string)($activation['billing_cycle'] ?? $subscription['billing_cycle']),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'provider_session_reference' => mg_subscription_stripe_history_reference($object['id'] ?? ''),
            'provider_subscription_id' => mg_subscription_stripe_history_reference($object['subscription'] ?? ''),
            'provider_customer_id' => mg_subscription_stripe_history_reference($object['customer'] ?? ''),
            'invoice_id' => $invoiceId,
            'invoice_status' => trim((string)($invoice['status'] ?? '')),
            'invoice_url' => trim((string)($invoice['hosted_invoice_url'] ?? '')),
            'invoice_pdf' => trim((string)($invoice['invoice_pdf'] ?? '')),
            'payment_intent_id' => $paymentIntentId,
        ]
    );
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
        $same = (int)$existing['signature_valid'] === 1
            && hash_equals((string)$existing['payload_hash'], $payloadHash)
            && hash_equals((string)$existing['event_type'], $type);
        if (!$same) throw new MgSubscriptionStripeWebhookException('Webhook event conflicts with an existing signed payload.', 409);
        if ((string)$existing['status'] === 'processed') {
            return ['duplicate'=>true,'status'=>'processed','processed'=>true,'event_type'=>$type];
        }
        $pdo->prepare("UPDATE payment_webhook_events SET status='processing',failure_message=NULL,received_at=NOW() WHERE provider_key=? AND provider_event_id=?")
            ->execute(['stripe', $eventId]);
    } else {
        $pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'processing',?,?,NOW())")
            ->execute([mg_public_uuid(), 'stripe', $eventId, $type, $payloadHash, $payload]);
    }

    $activation = mg_subscription_package_webhook_v2_try_process($pdo, 'stripe', $event);
    $processed = is_array($activation) && !empty($activation['processed']);
    $status = $processed ? 'processed' : 'ignored';
    mg_subscription_stripe_record_history($pdo, $event, $activation);

    $pdo->prepare('UPDATE payment_webhook_events SET status=?,processed_at=NOW(),failure_message=NULL WHERE provider_key=? AND provider_event_id=?')
        ->execute([$status, 'stripe', $eventId]);

    return [
        'duplicate'=>false,'status'=>$status,'processed'=>$processed,
        'subscription_request_id'=>$activation['request_id']??null,
        'platform_account_subscription_id'=>$activation['platform_account_subscription_id']??null,
        'package_id'=>$activation['package_id']??null,
        'billing_cycle'=>$activation['billing_cycle']??null,
        'from_status'=>$activation['from_status']??null,
        'to_status'=>$activation['to_status']??null,
        'scheduled_applied'=>$activation['scheduled_applied']??false,
        'event_type'=>$type,
    ];
}
