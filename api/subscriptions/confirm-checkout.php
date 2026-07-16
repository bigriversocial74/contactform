<?php
declare(strict_types=1);

require_once __DIR__ . '/_checkout_handoff.php';
require_once __DIR__ . '/_package_webhook_v2.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';

function mg_subscription_checkout_completion_reference(mixed $value): string
{
    if (is_array($value)) return trim((string)($value['id'] ?? ''));
    return trim((string)$value);
}

function mg_subscription_checkout_completion_datetime(mixed $value): ?string
{
    if (!is_numeric($value) || (int)$value < 1) return null;
    return gmdate('Y-m-d H:i:s', (int)$value);
}

function mg_subscription_checkout_completion_stub_session(array $requestRow, array $checkoutMeta, string $sessionId): array
{
    $cycle = mg_platform_package_interval_unit((string)($requestRow['billing_cycle'] ?? 'month'));
    $periodEnd = $cycle === 'year' ? strtotime('+1 year') : strtotime('+1 month');
    $priceId = trim((string)($checkoutMeta['provider_price_id'] ?? '')) ?: 'price_test_checkout_completion';
    $subscriptionId = 'sub_test_completion_' . substr(hash('sha256', $sessionId), 0, 20);
    $customerId = 'cus_test_completion_' . substr(hash('sha256', (string)$requestRow['user_id']), 0, 18);
    $invoiceId = 'in_test_completion_' . substr(hash('sha256', $sessionId . '|invoice'), 0, 18);
    $paymentIntentId = 'pi_test_completion_' . substr(hash('sha256', $sessionId . '|payment'), 0, 18);
    $metadata = [
        'source_type' => 'subscription_package_change',
        'package_change_request_id' => (string)$requestRow['public_id'],
        'user_id' => (string)$requestRow['user_id'],
        'package_id' => (string)$requestRow['requested_package_id'],
        'order_total_cents' => (string)$requestRow['amount_cents'],
        'currency' => strtolower((string)$requestRow['currency']),
        'billing_cycle' => $cycle,
        'provider_price_id' => $priceId,
    ];

    return [
        'id' => $sessionId,
        'object' => 'checkout.session',
        'status' => 'complete',
        'payment_status' => 'paid',
        'amount_total' => (int)$requestRow['amount_cents'],
        'currency' => strtolower((string)$requestRow['currency']),
        'client_reference_id' => (string)$requestRow['public_id'],
        'metadata' => $metadata,
        'customer' => ['id' => $customerId],
        'subscription' => [
            'id' => $subscriptionId,
            'status' => 'active',
            'customer' => $customerId,
            'current_period_start' => time(),
            'current_period_end' => $periodEnd,
            'items' => ['data' => [[
                'price' => ['id' => $priceId, 'recurring' => ['interval' => $cycle]],
                'quantity' => 1,
            ]]],
            'latest_invoice' => [
                'id' => $invoiceId,
                'status' => 'paid',
                'hosted_invoice_url' => 'https://invoice.stripe.test/' . rawurlencode($invoiceId),
                'invoice_pdf' => 'https://invoice.stripe.test/' . rawurlencode($invoiceId) . '.pdf',
                'payment_intent' => ['id' => $paymentIntentId],
            ],
        ],
    ];
}

function mg_subscription_checkout_completion_retrieve_session(PDO $pdo, array $requestRow, string $sessionId): array
{
    $metadata = mg_subscription_package_change_decode_json($requestRow['metadata_json'] ?? null);
    $checkoutMeta = is_array($metadata['stripe_checkout'] ?? null) ? $metadata['stripe_checkout'] : [];
    if (mg_stripe_stub_enabled()) {
        return mg_subscription_checkout_completion_stub_session($requestRow, $checkoutMeta, $sessionId);
    }

    return mg_stripe_api_request($pdo, 'GET', '/v1/checkout/sessions/' . rawurlencode($sessionId), [
        'expand' => ['subscription', 'subscription.latest_invoice.payment_intent', 'customer'],
    ]);
}

function mg_subscription_checkout_completion_price_id(array $subscription, array $metadata): string
{
    $items = $subscription['items']['data'] ?? [];
    if (is_array($items)) {
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $priceId = mg_subscription_checkout_completion_reference($item['price'] ?? '');
            if ($priceId !== '') return $priceId;
        }
    }
    return trim((string)($metadata['provider_price_id'] ?? ''));
}

function mg_subscription_checkout_completion_invoice(array $session, array $subscription): array
{
    $invoice = $subscription['latest_invoice'] ?? ($session['invoice'] ?? []);
    if (!is_array($invoice)) return ['id' => mg_subscription_checkout_completion_reference($invoice)];
    return $invoice;
}

function mg_subscription_checkout_completion_hydrate(PDO $pdo, int $userId, array $session, array $metadata): array
{
    $before = mg_platform_account_subscription_snapshot($pdo, $userId, true);
    if (!$before) throw new MgSubscriptionCheckoutException('The activated subscription could not be loaded.', 500);

    $subscription = is_array($session['subscription'] ?? null) ? $session['subscription'] : [];
    $subscriptionId = mg_subscription_checkout_completion_reference($session['subscription'] ?? '');
    $customerId = mg_subscription_checkout_completion_reference($session['customer'] ?? ($subscription['customer'] ?? ''));
    $priceId = mg_subscription_checkout_completion_price_id($subscription, $metadata);
    $invoice = mg_subscription_checkout_completion_invoice($session, $subscription);
    $invoiceId = mg_subscription_checkout_completion_reference($invoice['id'] ?? '');
    $paymentIntentId = mg_subscription_checkout_completion_reference($invoice['payment_intent'] ?? ($session['payment_intent'] ?? ''));
    $providerStatus = strtolower(trim((string)($subscription['status'] ?? 'active')));
    $status = match ($providerStatus) {
        'trialing' => 'trialing',
        'past_due', 'unpaid' => 'past_due',
        'paused' => 'paused',
        'canceled' => 'canceled',
        'incomplete', 'incomplete_expired' => 'incomplete',
        default => 'active',
    };
    $periodStart = mg_subscription_checkout_completion_datetime($subscription['current_period_start'] ?? null);
    $periodEnd = mg_subscription_checkout_completion_datetime($subscription['current_period_end'] ?? null);

    $stmt = $pdo->prepare("UPDATE platform_account_subscriptions SET
        provider_customer_id=COALESCE(NULLIF(?,''),provider_customer_id),
        provider_subscription_id=COALESCE(NULLIF(?,''),provider_subscription_id),
        provider_session_reference=COALESCE(NULLIF(?,''),provider_session_reference),
        provider_price_id=COALESCE(NULLIF(?,''),provider_price_id),
        status=?,
        current_period_start=COALESCE(?,current_period_start),
        current_period_end=COALESCE(?,current_period_end),
        next_billing_at=COALESCE(?,next_billing_at),
        provider_latest_invoice_id=COALESCE(NULLIF(?,''),provider_latest_invoice_id),
        provider_latest_invoice_status=COALESCE(NULLIF(?,''),provider_latest_invoice_status),
        provider_latest_invoice_url=COALESCE(NULLIF(?,''),provider_latest_invoice_url),
        provider_latest_invoice_pdf=COALESCE(NULLIF(?,''),provider_latest_invoice_pdf),
        provider_latest_payment_intent_id=COALESCE(NULLIF(?,''),provider_latest_payment_intent_id),
        last_payment_at=CASE WHEN ? IN ('paid','no_payment_required') THEN COALESCE(last_payment_at,NOW()) ELSE last_payment_at END,
        updated_at=NOW()
        WHERE user_id=?");
    $stmt->execute([
        $customerId,
        $subscriptionId,
        (string)($session['id'] ?? ''),
        $priceId,
        $status,
        $periodStart,
        $periodEnd,
        $periodEnd,
        $invoiceId,
        (string)($invoice['status'] ?? ''),
        (string)($invoice['hosted_invoice_url'] ?? ''),
        (string)($invoice['invoice_pdf'] ?? ''),
        $paymentIntentId,
        strtolower((string)($session['payment_status'] ?? '')),
        $userId,
    ]);

    $snapshot = mg_platform_account_subscription_snapshot($pdo, $userId, true);
    if (!$snapshot) throw new MgSubscriptionCheckoutException('The activated subscription could not be loaded.', 500);

    $providerEventId = 'checkout-return:' . (string)($session['id'] ?? '');
    $historyStmt = $pdo->prepare('SELECT id FROM platform_subscription_events WHERE account_subscription_id=? AND event_type=? AND provider_key=? AND provider_event_id=? LIMIT 1');
    $historyStmt->execute([(int)$snapshot['id'], 'platform_subscription.checkout_return_confirmed', 'stripe', $providerEventId]);
    if (!$historyStmt->fetchColumn()) {
        mg_platform_account_subscription_event(
            $pdo,
            (int)$snapshot['id'],
            'platform_subscription.checkout_return_confirmed',
            (string)$before['status'],
            (string)$snapshot['status'],
            $userId,
            [
                'provider_key' => 'stripe',
                'provider_event_id' => $providerEventId,
                'provider_session_reference' => (string)($session['id'] ?? ''),
                'provider_subscription_id' => $subscriptionId,
                'provider_customer_id' => $customerId,
                'provider_price_id' => $priceId,
                'invoice_id' => $invoiceId,
                'invoice_status' => (string)($invoice['status'] ?? ''),
                'invoice_url' => (string)($invoice['hosted_invoice_url'] ?? ''),
                'invoice_pdf' => (string)($invoice['invoice_pdf'] ?? ''),
                'payment_intent_id' => $paymentIntentId,
                'amount_cents' => (int)($session['amount_total'] ?? 0),
                'currency' => strtoupper((string)($session['currency'] ?? 'USD')),
                'confirmation_source' => 'authenticated_checkout_return',
            ]
        );
    }

    return $snapshot;
}

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);
$requestId = trim((string)($input['request_id'] ?? $input['request'] ?? ''));
$sessionId = trim((string)($input['stripe_session_id'] ?? $input['session_id'] ?? ''));
$pdo = null;

try {
    if ($requestId === '' || $sessionId === '') throw new InvalidArgumentException('Checkout request and Stripe session are required.');
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) throw new InvalidArgumentException('Stripe Checkout session format is invalid.');

    $pdo = mg_db();
    $userId = (int)($user['id'] ?? 0);
    $requestRow = mg_subscription_checkout_request_row($pdo, $requestId, $userId, false);
    $requestMeta = mg_subscription_package_change_decode_json($requestRow['metadata_json'] ?? null);
    $checkoutMeta = is_array($requestMeta['stripe_checkout'] ?? null) ? $requestMeta['stripe_checkout'] : [];
    $storedSessionId = trim((string)($checkoutMeta['provider_session_reference'] ?? ''));
    if ($storedSessionId !== '' && !hash_equals($storedSessionId, $sessionId)) {
        throw new MgSubscriptionCheckoutException('Stripe Checkout session does not match this package request.', 409);
    }

    $session = mg_subscription_checkout_completion_retrieve_session($pdo, $requestRow, $sessionId);
    $sessionMetadata = mg_subscription_package_webhook_metadata($session);
    if ((string)($session['id'] ?? '') !== $sessionId) throw new MgSubscriptionCheckoutException('Stripe returned a different Checkout session.', 409);
    if (trim((string)($session['client_reference_id'] ?? '')) !== $requestId) throw new MgSubscriptionCheckoutException('Checkout session does not belong to this package request.', 409);
    if (($sessionMetadata['source_type'] ?? '') !== 'subscription_package_change') throw new MgSubscriptionCheckoutException('Checkout session source is invalid.', 409);
    if (!hash_equals($requestId, trim((string)($sessionMetadata['package_change_request_id'] ?? '')))) throw new MgSubscriptionCheckoutException('Checkout request metadata does not match.', 409);
    if ((int)($sessionMetadata['user_id'] ?? 0) !== $userId) throw new MgSubscriptionCheckoutException('Checkout session does not belong to this account.', 403);

    $sessionStatus = strtolower(trim((string)($session['status'] ?? 'complete')));
    $paymentStatus = strtolower(trim((string)($session['payment_status'] ?? '')));
    if ($sessionStatus !== '' && $sessionStatus !== 'complete') {
        mg_ok(['confirmed' => false, 'pending' => true, 'session_status' => $sessionStatus], 'Stripe Checkout has not completed yet.');
    }
    if (!in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
        mg_ok(['confirmed' => false, 'pending' => true, 'payment_status' => $paymentStatus], 'Stripe payment confirmation is still pending.');
    }

    $pdo->beginTransaction();
    mg_subscription_checkout_request_row($pdo, $requestId, $userId, true);
    $result = mg_subscription_package_webhook_complete(
        $pdo,
        'stripe',
        'checkout-return:' . $sessionId,
        $session,
        $sessionMetadata
    );
    $snapshot = mg_subscription_checkout_completion_hydrate($pdo, $userId, $session, $sessionMetadata);
    $pdo->commit();

    $context = mg_user_package_context($pdo, $user);
    mg_audit('subscription.checkout_return_confirmed', 'subscription_package_change_request', [
        'request_id' => $requestId,
        'provider_session_reference' => $sessionId,
        'platform_account_subscription_id' => (string)($snapshot['public_id'] ?? ''),
        'duplicate' => !empty($result['duplicate']),
    ], $userId);

    mg_ok([
        'confirmed' => true,
        'pending' => false,
        'duplicate' => !empty($result['duplicate']),
        'request_id' => $requestId,
        'subscription' => [
            'subscription_id' => (string)($snapshot['public_id'] ?? ''),
            'package_id' => (string)($snapshot['package_id'] ?? ''),
            'billing_cycle' => (string)($snapshot['billing_cycle'] ?? ''),
            'status' => (string)($snapshot['status'] ?? ''),
            'current_period_end' => $snapshot['current_period_end'] ?? null,
            'latest_invoice_url' => $snapshot['provider_latest_invoice_url'] ?? null,
        ],
        'activation' => [
            'merchant_access' => !empty($context['merchant_access']),
            'package_id' => (string)($context['package_id'] ?? 'free'),
            'package_name' => (string)($context['package_name'] ?? 'Free Wallet'),
        ],
    ], 'Subscription checkout confirmed and account access refreshed.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (MgSubscriptionCheckoutException|MgSubscriptionPackageWebhookException $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('warning', 'subscription.checkout_return_rejected', 'Checkout return confirmation was rejected.', [
        'request_id' => $requestId,
        'stripe_session_id' => $sessionId,
        'reason' => $error->getMessage(),
    ], (int)($user['id'] ?? 0));
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'subscription.checkout_return_failed', 'Checkout return confirmation failed.', [
        'request_id' => $requestId,
        'stripe_session_id' => $sessionId,
        'exception_class' => $error::class,
    ], (int)($user['id'] ?? 0));
    mg_fail('Unable to confirm subscription checkout.', 500);
}
