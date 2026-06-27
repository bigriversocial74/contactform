<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';

final class MgSubscriptionWebhookActivationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_subscription_webhook_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_subscription_webhook_datetime_from_timestamp(mixed $value, string $fallback = '+1 month'): string
{
    $timestamp = is_numeric($value) ? (int)$value : 0;
    if ($timestamp > 0) return date('Y-m-d H:i:s', $timestamp);
    return (new DateTimeImmutable($fallback))->format('Y-m-d H:i:s');
}

function mg_subscription_webhook_is_package_change(array $ids): bool
{
    $metadata = is_array($ids['metadata'] ?? null) ? $ids['metadata'] : [];
    $source = (string)($metadata['source_type'] ?? $metadata['source'] ?? '');
    return $source === 'subscription_package_change' || trim((string)($metadata['package_change_request_id'] ?? '')) !== '';
}

function mg_subscription_webhook_request_id(array $ids): string
{
    $metadata = is_array($ids['metadata'] ?? null) ? $ids['metadata'] : [];
    $object = is_array($ids['object'] ?? null) ? $ids['object'] : [];
    return trim((string)($metadata['package_change_request_id'] ?? $metadata['request_id'] ?? $object['client_reference_id'] ?? ''));
}

function mg_subscription_webhook_provider_reference(array $ids, string $key): string
{
    $object = is_array($ids['object'] ?? null) ? $ids['object'] : [];
    $value = $object[$key] ?? '';
    if (is_array($value)) return trim((string)($value['id'] ?? ''));
    return trim((string)$value);
}

function mg_subscription_webhook_find_or_create_plan(PDO $pdo, array $request, array $plan): array
{
    $userId = (int)$request['user_id'];
    $packageId = (string)$request['requested_package_id'];
    $targetReference = 'platform_package:' . $packageId;
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE owner_user_id=? AND target_type='merchant' AND target_reference=? ORDER BY FIELD(status,'active','draft','paused','archived'), id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$userId, $targetReference]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare("UPDATE subscription_plans SET name=?,description=?,amount_cents=?,currency=?,interval_unit='month',interval_count=1,funding_type='stripe',status='active',metadata_json=JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.package_id', ?, '$.pricing_package_id', ?),updated_at=NOW() WHERE id=?")
            ->execute(['Microgifter ' . (string)($plan['name'] ?? ucfirst($packageId)), (string)($plan['description'] ?? ''), (int)$request['amount_cents'], (string)$request['currency'], $packageId, $packageId, (int)$row['id']]);
        $reload = $pdo->prepare('SELECT * FROM subscription_plans WHERE id=? LIMIT 1');
        $reload->execute([(int)$row['id']]);
        return $reload->fetch(PDO::FETCH_ASSOC) ?: $row;
    }

    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO subscription_plans (public_id,owner_user_id,target_type,target_reference,name,description,amount_cents,currency,interval_unit,interval_count,trial_days,funding_type,status,metadata_json,created_at,updated_at) VALUES (?,?, 'merchant', ?, ?, ?, ?, ?, 'month', 1, 0, 'stripe', 'active', ?, NOW(), NOW())")
        ->execute([$publicId, $userId, $targetReference, 'Microgifter ' . (string)($plan['name'] ?? ucfirst($packageId)), (string)($plan['description'] ?? ''), (int)$request['amount_cents'], (string)$request['currency'], mg_subscription_webhook_json(['source' => 'subscription_package_change', 'package_id' => $packageId, 'pricing_package_id' => $packageId, 'pricing_source' => 'includes/pricing-packages.php'])]);
    $reload = $pdo->prepare('SELECT * FROM subscription_plans WHERE public_id=? LIMIT 1');
    $reload->execute([$publicId]);
    return $reload->fetch(PDO::FETCH_ASSOC) ?: [];
}

function mg_subscription_webhook_upsert_subscription(PDO $pdo, array $request, array $planRow, array $ids, array $event): array
{
    $object = is_array($ids['object'] ?? null) ? $ids['object'] : [];
    $userId = (int)$request['user_id'];
    $packageId = (string)$request['requested_package_id'];
    $requestId = (string)$request['public_id'];
    $providerSubscription = mg_subscription_webhook_provider_reference($ids, 'subscription');
    $providerCustomer = mg_subscription_webhook_provider_reference($ids, 'customer');
    $periodStart = mg_subscription_webhook_datetime_from_timestamp($object['current_period_start'] ?? null, 'now');
    $periodEnd = mg_subscription_webhook_datetime_from_timestamp($object['current_period_end'] ?? null, '+1 month');
    $nextBilling = $periodEnd;
    $metadata = [
        'source' => 'subscription_package_change',
        'package_id' => $packageId,
        'pricing_package_id' => $packageId,
        'package_change_request_id' => $requestId,
        'stripe_checkout_session_id' => (string)($ids['provider_session_reference'] ?? ''),
        'stripe_subscription_id' => $providerSubscription,
        'stripe_customer_id' => $providerCustomer,
        'stripe_payment_intent_id' => (string)($ids['provider_intent_reference'] ?? ''),
        'stripe_event_id' => (string)($event['id'] ?? ''),
        'activated_source' => 'stripe_checkout_webhook',
    ];

    $existingStmt = $pdo->prepare("SELECT * FROM subscriptions WHERE subscriber_user_id=? AND (provider_subscription_id=? OR status IN ('pending_payment','trialing','active','past_due','paused','cancel_pending')) ORDER BY FIELD(status,'active','trialing','pending_payment','past_due','paused','cancel_pending'), updated_at DESC, id DESC LIMIT 1 FOR UPDATE");
    $existingStmt->execute([$userId, $providerSubscription]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $fromStatus = (string)$existing['status'];
        $pdo->prepare("UPDATE subscriptions SET plan_id=?,recipient_user_id=?,target_type='merchant',target_reference=?,amount_cents=?,currency=?,funding_type='stripe',status='active',provider_subscription_id=NULLIF(?,''),provider_customer_id=NULLIF(?,''),current_period_start=?,current_period_end=?,next_billing_at=?,initial_payment_required=0,funded_at=COALESCE(funded_at,NOW()),activated_at=COALESCE(activated_at,NOW()),metadata_json=JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.source', ?, '$.package_id', ?, '$.pricing_package_id', ?, '$.package_change_request_id', ?, '$.stripe_checkout_session_id', ?, '$.stripe_subscription_id', ?, '$.stripe_customer_id', ?, '$.stripe_payment_intent_id', ?, '$.stripe_event_id', ?, '$.activated_source', ?),updated_at=NOW() WHERE id=?")
            ->execute([(int)$planRow['id'], $userId, (string)$planRow['target_reference'], (int)$request['amount_cents'], (string)$request['currency'], $providerSubscription, $providerCustomer, $periodStart, $periodEnd, $nextBilling, 'subscription_package_change', $packageId, $packageId, $requestId, (string)($ids['provider_session_reference'] ?? ''), $providerSubscription, $providerCustomer, (string)($ids['provider_intent_reference'] ?? ''), (string)($event['id'] ?? ''), 'stripe_checkout_webhook', (int)$existing['id']]);
        $subscriptionId = (int)$existing['id'];
    } else {
        $fromStatus = null;
        $publicId = mg_public_uuid();
        $pdo->prepare("INSERT INTO subscriptions (public_id,plan_id,subscriber_user_id,recipient_user_id,target_type,target_reference,amount_cents,currency,funding_type,status,idempotency_key,provider_subscription_id,provider_customer_id,current_period_start,current_period_end,next_billing_at,initial_payment_required,funded_at,activated_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?, 'merchant', ?, ?, ?, 'stripe', 'active', ?, NULLIF(?,''), NULLIF(?,''), ?, ?, ?, 0, NOW(), NOW(), ?, NOW(), NOW())")
            ->execute([$publicId, (int)$planRow['id'], $userId, $userId, (string)$planRow['target_reference'], (int)$request['amount_cents'], (string)$request['currency'], 'subscription-package:' . $requestId, $providerSubscription, $providerCustomer, $periodStart, $periodEnd, $nextBilling, mg_subscription_webhook_json($metadata)]);
        $subscriptionId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare('INSERT INTO subscription_events (public_id,subscription_id,event_type,from_status,to_status,actor_user_id,reason_code,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(), $subscriptionId, 'package_change.activated', $fromStatus, 'active', $userId, 'stripe_checkout_completed', mg_subscription_webhook_json($metadata)]);

    return ['subscription_id' => $subscriptionId, 'from_status' => $fromStatus, 'to_status' => 'active'];
}

function mg_subscription_webhook_activate_package_change(PDO $pdo, string $provider, array $event, array $ids): ?array
{
    if ($provider !== 'stripe' || !mg_subscription_webhook_is_package_change($ids)) return null;
    $requestId = mg_subscription_webhook_request_id($ids);
    if ($requestId === '') throw new MgSubscriptionWebhookActivationException('Subscription package change request ID is missing from webhook metadata.', 422);
    if ((string)($ids['payment_status'] ?? '') !== '' && (string)$ids['payment_status'] !== 'paid') return ['processed' => false, 'subscription_request_id' => $requestId, 'reason' => 'checkout_session_not_paid'];

    mg_subscription_package_change_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM subscription_package_change_requests WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) throw new MgSubscriptionWebhookActivationException('Subscription package change request was not found.', 404);
    if ((string)$request['request_type'] === 'enterprise') throw new MgSubscriptionWebhookActivationException('Enterprise package requests cannot be activated by checkout.', 422);

    $amount = (int)($ids['amount_cents'] ?? 0);
    if ($amount > 0 && $amount !== (int)$request['amount_cents']) throw new MgSubscriptionWebhookActivationException('Stripe amount does not match the package request.', 409);
    $currency = strtoupper((string)($ids['currency'] ?? ''));
    if ($currency !== '' && $currency !== strtoupper((string)$request['currency'])) throw new MgSubscriptionWebhookActivationException('Stripe currency does not match the package request.', 409);

    $plans = mg_subscription_package_change_plans();
    $plan = mg_subscription_package_change_plan($plans, (string)$request['requested_package_id']);
    if (!$plan) throw new MgSubscriptionWebhookActivationException('Requested package is no longer available.', 422);
    if ((string)$request['status'] === 'completed') return ['processed' => true, 'subscription_request_id' => $requestId, 'duplicate_request' => true];

    $planRow = mg_subscription_webhook_find_or_create_plan($pdo, $request, $plan);
    if (!$planRow || empty($planRow['id'])) throw new MgSubscriptionWebhookActivationException('Unable to resolve package subscription plan.', 500);
    $subscription = mg_subscription_webhook_upsert_subscription($pdo, $request, $planRow, $ids, $event);

    $meta = mg_subscription_package_change_decode_json($request['metadata_json'] ?? null);
    $meta['stripe_activation'] = [
        'provider' => 'stripe',
        'event_id' => (string)($event['id'] ?? ''),
        'event_type' => (string)($event['type'] ?? ''),
        'checkout_session_id' => (string)($ids['provider_session_reference'] ?? ''),
        'payment_intent_id' => (string)($ids['provider_intent_reference'] ?? ''),
        'subscription_id' => mg_subscription_webhook_provider_reference($ids, 'subscription'),
        'customer_id' => mg_subscription_webhook_provider_reference($ids, 'customer'),
        'activated_at' => gmdate('Y-m-d H:i:s'),
        'internal_subscription_id' => $subscription['subscription_id'],
    ];
    $meta['applied_to_subscription'] = 1;

    $pdo->prepare("UPDATE subscription_package_change_requests SET status='completed',admin_note=COALESCE(admin_note,'Activated automatically by Stripe checkout.'),completed_at=COALESCE(completed_at,NOW()),metadata_json=?,updated_at=NOW() WHERE id=?")
        ->execute([mg_subscription_webhook_json($meta), (int)$request['id']]);

    mg_audit('subscription.package_change_checkout_completed', 'subscription_package_change_request', ['request_id' => $requestId, 'requested_package_id' => (string)$request['requested_package_id'], 'internal_subscription_id' => $subscription['subscription_id']], (int)$request['user_id']);
    mg_event('subscription.package_change_checkout_completed', ['request_id' => $requestId, 'requested_package_id' => (string)$request['requested_package_id'], 'internal_subscription_id' => $subscription['subscription_id']], (int)$request['user_id']);

    return ['processed' => true, 'subscription_request_id' => $requestId, 'subscription' => $subscription];
}
