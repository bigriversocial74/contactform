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
