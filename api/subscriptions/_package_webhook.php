<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';
require_once __DIR__ . '/_package_billing.php';

final class MgSubscriptionPackageWebhookException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_subscription_package_webhook_object(array $event): array
{
    $data = $event['data'] ?? [];
    if (is_array($data) && is_array($data['object'] ?? null)) return $data['object'];
    return is_array($data) ? $data : [];
}

function mg_subscription_package_webhook_metadata(array $object): array
{
    $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
    if (is_array($object['subscription_details']['metadata'] ?? null)) {
        $metadata = array_merge($metadata, $object['subscription_details']['metadata']);
    }
    return $metadata;
}

function mg_subscription_package_webhook_provider_reference(mixed $value): string
{
    if (is_array($value)) return trim((string) ($value['id'] ?? ''));
    return trim((string) $value);
}

function mg_subscription_package_webhook_datetime(mixed $timestamp): ?string
{
    if (!is_numeric($timestamp) || (int) $timestamp < 1) return null;
    return gmdate('Y-m-d H:i:s', (int) $timestamp);
}

function mg_subscription_package_webhook_try_process(PDO $pdo, string $provider, array $event): ?array
{
    $type = trim((string) ($event['type'] ?? ''));
    $object = mg_subscription_package_webhook_object($event);
    $metadata = mg_subscription_package_webhook_metadata($object);

    if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
        if (($metadata['source_type'] ?? '') !== 'subscription_package_change') return null;
        return mg_subscription_package_webhook_complete(
            $pdo,
            $provider,
            (string) ($event['id'] ?? ''),
            $object,
            $metadata
        );
    }

    if (in_array($type, [
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.paid',
        'invoice.payment_failed',
    ], true)) {
        return mg_subscription_package_webhook_sync_lifecycle(
            $pdo,
            $provider,
            (string) ($event['id'] ?? ''),
            $type,
            $object
        );
    }

    return null;
}

function mg_subscription_package_webhook_complete(PDO $pdo, string $provider, string $eventId, array $session, array $metadata): array
{
    $requestId = trim((string) ($metadata['package_change_request_id'] ?? $session['client_reference_id'] ?? ''));
    $expectedUserId = (int) ($metadata['user_id'] ?? 0);
    $expectedPackageId = mg_platform_package_slug($metadata['package_id'] ?? '');
    $expectedAmount = (int) ($metadata['order_total_cents'] ?? 0);
    $expectedCurrency = strtoupper((string) ($metadata['currency'] ?? $session['currency'] ?? 'USD'));

    if ($requestId === '' || $expectedUserId < 1 || $expectedPackageId === '' || $expectedAmount < 1 || !preg_match('/^[A-Z]{3}$/', $expectedCurrency)) {
        throw new MgSubscriptionPackageWebhookException('Package checkout metadata is incomplete.', 422);
    }

    $sessionId = trim((string) ($session['id'] ?? ''));
    $paymentStatus = strtolower(trim((string) ($session['payment_status'] ?? '')));
    if ($paymentStatus !== '' && !in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
        return [
            'processed' => false,
            'duplicate' => false,
            'request_id' => $requestId,
            'package_id' => $expectedPackageId,
            'reason' => 'checkout_session_not_paid',
        ];
    }
    $sessionAmount = (int) ($session['amount_total'] ?? 0);
    $sessionCurrency = strtoupper((string) ($session['currency'] ?? $expectedCurrency));
    if ($sessionAmount > 0 && $sessionAmount !== $expectedAmount) {
        throw new MgSubscriptionPackageWebhookException('Stripe Checkout amount does not match the package request.', 409);
    }
    if ($sessionCurrency !== '' && !hash_equals($expectedCurrency, $sessionCurrency)) {
        throw new MgSubscriptionPackageWebhookException('Stripe Checkout currency does not match the package request.', 409);
    }

    mg_platform_package_sync_defaults($pdo);
    $stmt = $pdo->prepare('SELECT * FROM subscription_package_change_requests WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgSubscriptionPackageWebhookException('Package change request not found.', 404);
    if ((int) $row['user_id'] !== $expectedUserId) throw new MgSubscriptionPackageWebhookException('Package checkout user does not match the request.', 409);
    if (mg_platform_package_slug($row['requested_package_id'] ?? '') !== $expectedPackageId) throw new MgSubscriptionPackageWebhookException('Package checkout package does not match the request.', 409);
    if ((int) ($row['amount_cents'] ?? 0) !== $expectedAmount) throw new MgSubscriptionPackageWebhookException('Package checkout amount does not match the request.', 409);
    if (strtoupper((string) ($row['currency'] ?? 'USD')) !== $expectedCurrency) throw new MgSubscriptionPackageWebhookException('Package checkout currency does not match the request.', 409);

    $package = mg_platform_package_get($pdo, $expectedPackageId);
    if (!$package) throw new MgSubscriptionPackageWebhookException('Package billing configuration is unavailable.', 422);
    if ((int) $package['requires_admin_review'] === 1 || (int) $package['is_self_serve'] !== 1) {
        throw new MgSubscriptionPackageWebhookException('This package is not eligible for self-serve Checkout completion.', 409);
    }

    $canonicalAmount = mg_platform_package_amount_cents($package, (string) ($row['billing_cycle'] ?? 'month'));
    if ($canonicalAmount > 0 && $canonicalAmount !== $expectedAmount) {
        throw new MgSubscriptionPackageWebhookException('Package request amount no longer matches canonical billing.', 409);
    }

    $requestAlreadyCompleted = (string) $row['status'] === 'completed';
    if ($requestAlreadyCompleted) {
        $existingCanonical = mg_platform_account_subscription_snapshot($pdo, $expectedUserId, true);
        if ($existingCanonical
            && mg_platform_package_slug($existingCanonical['package_id'] ?? '') === $expectedPackageId
            && in_array((string) ($existingCanonical['status'] ?? ''), ['active', 'trialing', 'cancel_pending', 'past_due'], true)) {
            return [
                'processed' => true,
                'duplicate' => true,
                'request_id' => $requestId,
                'package_id' => $expectedPackageId,
                'platform_account_subscription_id' => (string) ($existingCanonical['public_id'] ?? ''),
            ];
        }
    } elseif (!in_array((string) $row['status'], MG_SUBSCRIPTION_PACKAGE_CHANGE_PENDING_STATUSES, true)) {
        throw new MgSubscriptionPackageWebhookException('Package change request is already closed.', 409);
    }

    $providerRefs = [
        'provider_key' => $provider,
        'provider_session_reference' => $sessionId,
        'provider_subscription_id' => mg_subscription_package_webhook_provider_reference($session['subscription'] ?? ''),
        'provider_customer_id' => mg_subscription_package_webhook_provider_reference($session['customer'] ?? ''),
        'provider_price_id' => mg_platform_package_stripe_price_id($package),
    ];
    $accountSubscription = mg_platform_account_subscription_upsert($pdo, $row, $package, $providerRefs);

    $existingMeta = mg_subscription_package_change_decode_json($row['metadata_json'] ?? null);
    $existingMeta['stripe_checkout_completed'] = [
        'provider' => $provider,
        'provider_event_id' => $eventId,
        'provider_session_reference' => $providerRefs['provider_session_reference'],
        'provider_subscription_id' => $providerRefs['provider_subscription_id'],
        'provider_customer_id' => $providerRefs['provider_customer_id'],
        'amount_total' => $expectedAmount,
        'currency' => $expectedCurrency,
        'completed_at' => gmdate('c'),
    ];
    $existingMeta['platform_account_subscription_id'] = (string) ($accountSubscription['public_id'] ?? '');

    $pdo->prepare(
        "UPDATE subscription_package_change_requests
         SET status='completed',completed_at=COALESCE(completed_at,NOW()),checkout_url=NULL,metadata_json=?,updated_at=NOW()
         WHERE id=?"
    )->execute([
        json_encode($existingMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        (int) $row['id'],
    ]);

    try {
        $pdo->prepare(
            "UPDATE subscriptions
             SET metadata_json=JSON_SET(
                    COALESCE(metadata_json,JSON_OBJECT()),
                    '$.package_id',?,
                    '$.pricing_package_id',?,
                    '$.package_change_request_id',?,
                    '$.platform_account_subscription_id',?
                 ),
                 updated_at=NOW()
             WHERE subscriber_user_id=?
             ORDER BY updated_at DESC,id DESC
             LIMIT 1"
        )->execute([
            $expectedPackageId,
            $expectedPackageId,
            $requestId,
            (string) ($accountSubscription['public_id'] ?? ''),
            $expectedUserId,
        ]);
    } catch (Throwable) {
        /* Legacy recurring-support subscriptions are not the access authority. */
    }

    mg_audit('subscription.package_checkout_completed', 'subscription_package_change_request', [
        'request_id' => $requestId,
        'package_id' => $expectedPackageId,
        'provider' => $provider,
        'provider_event_id' => $eventId,
        'provider_session_reference' => $sessionId,
        'platform_account_subscription_id' => (string) ($accountSubscription['public_id'] ?? ''),
    ], $expectedUserId);
    mg_event('subscription.package_checkout_completed', [
        'request_id' => $requestId,
        'package_id' => $expectedPackageId,
        'provider' => $provider,
        'platform_account_subscription_id' => (string) ($accountSubscription['public_id'] ?? ''),
    ], $expectedUserId);

    return [
        'processed' => true,
        'duplicate' => false,
        'request_id' => $requestId,
        'package_id' => $expectedPackageId,
        'platform_account_subscription_id' => (string) ($accountSubscription['public_id'] ?? ''),
    ];
}

function mg_subscription_package_webhook_sync_lifecycle(
    PDO $pdo,
    string $provider,
    string $eventId,
    string $type,
    array $object
): ?array {
    $providerSubscriptionId = '';
    if (str_starts_with($type, 'customer.subscription.')) {
        $providerSubscriptionId = mg_subscription_package_webhook_provider_reference($object['id'] ?? '');
    } elseif (str_starts_with($type, 'invoice.')) {
        $providerSubscriptionId = mg_subscription_package_webhook_provider_reference($object['subscription'] ?? '');
    }
    if ($providerSubscriptionId === '') return null;

    $stmt = $pdo->prepare(
        'SELECT * FROM platform_account_subscriptions
         WHERE provider_key=? AND provider_subscription_id=?
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$provider, $providerSubscriptionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $fromStatus = (string) $row['status'];
    $toStatus = $fromStatus;
    $providerStatus = strtolower(trim((string) ($object['status'] ?? '')));

    if ($type === 'customer.subscription.deleted') {
        $toStatus = 'canceled';
    } elseif ($type === 'invoice.paid') {
        $toStatus = 'active';
    } elseif ($type === 'invoice.payment_failed') {
        $toStatus = 'past_due';
    } elseif ($type === 'customer.subscription.updated') {
        $toStatus = match ($providerStatus) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due', 'unpaid' => 'past_due',
            'paused' => 'paused',
            'canceled' => 'canceled',
            'incomplete', 'incomplete_expired' => 'incomplete',
            default => $fromStatus,
        };
    }

    $periodStart = mg_subscription_package_webhook_datetime($object['current_period_start'] ?? null);
    $periodEnd = mg_subscription_package_webhook_datetime($object['current_period_end'] ?? null);
    $cancelAtPeriodEnd = !empty($object['cancel_at_period_end']) ? 1 : 0;
    if ($cancelAtPeriodEnd && in_array($toStatus, ['active', 'trialing'], true)) $toStatus = 'cancel_pending';

    $metadata = mg_platform_package_json($row['metadata_json'] ?? null);
    $metadata['last_provider_lifecycle_event'] = [
        'provider' => $provider,
        'provider_event_id' => $eventId,
        'event_type' => $type,
        'provider_status' => $providerStatus,
        'processed_at' => gmdate('c'),
    ];

    $pdo->prepare(
        "UPDATE platform_account_subscriptions
         SET status=?,
             current_period_start=COALESCE(?,current_period_start),
             current_period_end=COALESCE(?,current_period_end),
             next_billing_at=CASE WHEN ? IN ('canceled','expired','paused') THEN NULL ELSE COALESCE(?,next_billing_at) END,
             cancel_at_period_end=?,
             canceled_at=CASE WHEN ?='canceled' THEN COALESCE(canceled_at,NOW()) ELSE canceled_at END,
             metadata_json=?,
             updated_at=NOW()
         WHERE id=?"
    )->execute([
        $toStatus,
        $periodStart,
        $periodEnd,
        $toStatus,
        $periodEnd,
        $cancelAtPeriodEnd,
        $toStatus,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        (int) $row['id'],
    ]);

    mg_platform_account_subscription_event(
        $pdo,
        (int) $row['id'],
        'platform_subscription.provider_lifecycle',
        $fromStatus,
        $toStatus,
        (int) $row['user_id'],
        [
            'provider_key' => $provider,
            'provider_event_id' => $eventId,
            'event_type' => $type,
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_status' => $providerStatus,
        ]
    );

    return [
        'processed' => true,
        'duplicate' => false,
        'request_id' => null,
        'package_id' => (string) $row['package_id'],
        'platform_account_subscription_id' => (string) $row['public_id'],
        'from_status' => $fromStatus,
        'to_status' => $toStatus,
        'lifecycle_event' => $type,
    ];
}
