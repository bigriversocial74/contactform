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

function mg_subscription_package_webhook_try_process(PDO $pdo, string $provider, array $event): ?array
{
    $type = trim((string)($event['type'] ?? ''));
    if ($type !== 'checkout.session.completed') return null;
    $object = mg_subscription_package_webhook_object($event);
    $metadata = mg_subscription_package_webhook_metadata($object);
    if (($metadata['source_type'] ?? '') !== 'subscription_package_change') return null;
    return mg_subscription_package_webhook_complete($pdo, $provider, (string)($event['id'] ?? ''), $object, $metadata);
}

function mg_subscription_package_webhook_complete(PDO $pdo, string $provider, string $eventId, array $session, array $metadata): array
{
    $requestId = trim((string)($metadata['package_change_request_id'] ?? $session['client_reference_id'] ?? ''));
    $expectedUserId = (int)($metadata['user_id'] ?? 0);
    $expectedPackageId = mg_platform_package_slug($metadata['package_id'] ?? '');
    $expectedAmount = (int)($metadata['order_total_cents'] ?? 0);
    $expectedCurrency = strtoupper((string)($metadata['currency'] ?? $session['currency'] ?? 'USD'));
    if ($requestId === '' || $expectedUserId < 1 || $expectedPackageId === '' || $expectedAmount < 1 || !preg_match('/^[A-Z]{3}$/', $expectedCurrency)) {
        throw new MgSubscriptionPackageWebhookException('Package checkout metadata is incomplete.', 422);
    }

    $sessionId = trim((string)($session['id'] ?? ''));
    $sessionAmount = (int)($session['amount_total'] ?? 0);
    $sessionCurrency = strtoupper((string)($session['currency'] ?? $expectedCurrency));
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
    if ((int)$row['user_id'] !== $expectedUserId) throw new MgSubscriptionPackageWebhookException('Package checkout user does not match the request.', 409);
    if (mg_platform_package_slug($row['requested_package_id'] ?? '') !== $expectedPackageId) throw new MgSubscriptionPackageWebhookException('Package checkout package does not match the request.', 409);
    if ((int)($row['amount_cents'] ?? 0) !== $expectedAmount) throw new MgSubscriptionPackageWebhookException('Package checkout amount does not match the request.', 409);
    if (strtoupper((string)($row['currency'] ?? 'USD')) !== $expectedCurrency) throw new MgSubscriptionPackageWebhookException('Package checkout currency does not match the request.', 409);

    $package = mg_platform_package_get($pdo, $expectedPackageId);
    if (!$package) throw new MgSubscriptionPackageWebhookException('Package billing configuration is unavailable.', 422);
    if ((int)$package['requires_admin_review'] === 1 || (int)$package['is_self_serve'] !== 1) {
        throw new MgSubscriptionPackageWebhookException('This package is not eligible for self-serve Checkout completion.', 409);
    }
    $canonicalAmount = mg_platform_package_amount_cents($package, (string)($row['billing_cycle'] ?? 'month'));
    if ($canonicalAmount > 0 && $canonicalAmount !== $expectedAmount) {
        throw new MgSubscriptionPackageWebhookException('Package request amount no longer matches canonical billing.', 409);
    }

    if ((string)$row['status'] === 'completed') {
        return ['processed' => true, 'duplicate' => true, 'request_id' => $requestId, 'package_id' => $expectedPackageId];
    }
    if (!in_array((string)$row['status'], MG_SUBSCRIPTION_PACKAGE_CHANGE_PENDING_STATUSES, true)) {
        throw new MgSubscriptionPackageWebhookException('Package change request is already closed.', 409);
    }

    $providerRefs = [
        'provider_key' => $provider,
        'provider_session_reference' => $sessionId,
        'provider_subscription_id' => is_array($session['subscription'] ?? null) ? (string)($session['subscription']['id'] ?? '') : (string)($session['subscription'] ?? ''),
        'provider_customer_id' => is_array($session['customer'] ?? null) ? (string)($session['customer']['id'] ?? '') : (string)($session['customer'] ?? ''),
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
    $existingMeta['platform_account_subscription_id'] = (string)($accountSubscription['public_id'] ?? '');

    $pdo->prepare("UPDATE subscription_package_change_requests SET status='completed',completed_at=COALESCE(completed_at,NOW()),checkout_url=NULL,metadata_json=?,updated_at=NOW() WHERE id=?")
        ->execute([json_encode($existingMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), (int)$row['id']]);

    try {
        $pdo->prepare("UPDATE subscriptions SET metadata_json=JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.package_id', ?, '$.pricing_package_id', ?, '$.package_change_request_id', ?, '$.platform_account_subscription_id', ?), updated_at=NOW() WHERE subscriber_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1")
            ->execute([$expectedPackageId, $expectedPackageId, $requestId, (string)($accountSubscription['public_id'] ?? ''), $expectedUserId]);
    } catch (Throwable) {
        // Recurring support subscriptions are not the source of truth for platform package access.
    }

    mg_audit('subscription.package_checkout_completed', 'subscription_package_change_request', [
        'request_id' => $requestId,
        'package_id' => $expectedPackageId,
        'provider' => $provider,
        'provider_event_id' => $eventId,
        'provider_session_reference' => $sessionId,
        'platform_account_subscription_id' => (string)($accountSubscription['public_id'] ?? ''),
    ], $expectedUserId);
    mg_event('subscription.package_checkout_completed', [
        'request_id' => $requestId,
        'package_id' => $expectedPackageId,
        'provider' => $provider,
        'platform_account_subscription_id' => (string)($accountSubscription['public_id'] ?? ''),
    ], $expectedUserId);

    return [
        'processed' => true,
        'duplicate' => false,
        'request_id' => $requestId,
        'package_id' => $expectedPackageId,
        'platform_account_subscription_id' => (string)($accountSubscription['public_id'] ?? ''),
    ];
}
