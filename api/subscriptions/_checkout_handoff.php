<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';
require_once dirname(__DIR__) . '/payments/_payments.php';
require_once dirname(__DIR__) . '/payments/_stripe.php';

final class MgSubscriptionCheckoutException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_subscription_checkout_enabled(PDO $pdo): bool
{
    try {
        if (mg_stripe_stub_enabled()) return (string)(getenv('MG_APP_URL') ?: '') !== '';
        $config = mg_payment_platform_config($pdo, 'stripe', mg_payment_mode());
        return (bool)$config['enabled'] && trim((string)$config['secret_key']) !== '' && trim((string)(getenv('MG_APP_URL') ?: '')) !== '';
    } catch (Throwable) {
        return false;
    }
}

function mg_subscription_checkout_request_row(PDO $pdo, string $requestId, int $userId, bool $lock = false): array
{
    mg_subscription_package_change_schema($pdo);
    $sql = 'SELECT * FROM subscription_package_change_requests WHERE public_id=? AND user_id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([trim($requestId), $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgSubscriptionCheckoutException('Package change request not found.', 404);
    return $row;
}

function mg_subscription_checkout_active_url(array $row): ?string
{
    $url = trim((string)($row['checkout_url'] ?? ''));
    if ($url === '') return null;
    $meta = mg_subscription_package_change_decode_json($row['metadata_json'] ?? null);
    $expires = trim((string)($meta['stripe_checkout']['expires_at'] ?? ''));
    if ($expires !== '' && strtotime($expires) !== false && strtotime($expires) <= time() + 60) return null;
    return $url;
}

function mg_subscription_checkout_success_url(string $requestId): string
{
    return '/account-subscriptions.php?checkout=success&request=' . rawurlencode($requestId) . '&stripe_session_id={CHECKOUT_SESSION_ID}';
}

function mg_subscription_checkout_cancel_url(string $requestId): string
{
    return '/account-subscriptions.php?checkout=cancelled&request=' . rawurlencode($requestId);
}

function mg_subscription_checkout_create_stripe_session(PDO $pdo, array $row, array $plan): array
{
    $requestId = (string)$row['public_id'];
    $packageId = (string)$row['requested_package_id'];
    $amountCents = (int)($row['amount_cents'] ?? 0);
    $currency = strtolower((string)($row['currency'] ?? 'USD'));
    if ($amountCents < 1) throw new MgSubscriptionCheckoutException('This package does not have a self-serve checkout price.', 422);

    $metadata = [
        'source_type' => 'subscription_package_change',
        'package_change_request_id' => $requestId,
        'user_id' => (string)$row['user_id'],
        'package_id' => $packageId,
        'order_total_cents' => (string)$amountCents,
        'currency' => $currency,
    ];
    $params = [
        'mode' => 'subscription',
        'success_url' => mg_payment_absolute_url(mg_subscription_checkout_success_url($requestId)),
        'cancel_url' => mg_payment_absolute_url(mg_subscription_checkout_cancel_url($requestId)),
        'client_reference_id' => $requestId,
        'metadata' => $metadata,
        'subscription_data' => ['metadata' => $metadata],
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $amountCents,
                'recurring' => ['interval' => 'month'],
                'product_data' => [
                    'name' => 'Microgifter ' . (string)($plan['name'] ?? ucfirst($packageId)) . ' Plan',
                    'metadata' => ['package_id' => $packageId],
                ],
            ],
        ]],
    ];
    $session = mg_stripe_api_request($pdo, 'POST', '/v1/checkout/sessions', $params, 'subscription-package:' . $requestId . ':' . $packageId);
    if (empty($session['id']) || empty($session['url'])) throw new MgSubscriptionCheckoutException('Stripe did not return a hosted checkout URL.', 502);
    return [
        'provider' => 'stripe',
        'provider_session_reference' => (string)$session['id'],
        'checkout_url' => (string)$session['url'],
        'expires_at' => date('Y-m-d H:i:s', (int)($session['expires_at'] ?? time() + 1800)),
    ];
}

function mg_subscription_checkout_start(PDO $pdo, array $user, string $requestId): array
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) throw new MgSubscriptionCheckoutException('Authentication required.', 401);

    $pdo->beginTransaction();
    try {
        $row = mg_subscription_checkout_request_row($pdo, $requestId, $userId, true);
        if ((string)$row['request_type'] === 'enterprise') throw new MgSubscriptionCheckoutException('Enterprise package requests require admin review.', 422);
        if (!in_array((string)$row['status'], ['pending_admin_review', 'pending_payment'], true)) throw new MgSubscriptionCheckoutException('This package request is not available for checkout.', 409);

        $plans = mg_subscription_package_change_plans();
        $plan = mg_subscription_package_change_plan($plans, (string)$row['requested_package_id']);
        if (!$plan) throw new MgSubscriptionCheckoutException('Requested package is no longer available.', 422);

        if ($activeUrl = mg_subscription_checkout_active_url($row)) {
            $pdo->commit();
            return ['request' => mg_subscription_package_change_public($row), 'checkout_url' => $activeUrl, 'duplicate' => true];
        }
        if (!mg_subscription_checkout_enabled($pdo)) throw new MgSubscriptionCheckoutException('Stripe subscription checkout is not configured yet.', 503);

        $checkout = mg_subscription_checkout_create_stripe_session($pdo, $row, $plan);
        $meta = mg_subscription_package_change_decode_json($row['metadata_json'] ?? null);
        $meta['stripe_checkout'] = [
            'provider' => 'stripe',
            'provider_session_reference' => $checkout['provider_session_reference'],
            'checkout_url' => $checkout['checkout_url'],
            'expires_at' => $checkout['expires_at'],
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        $update = $pdo->prepare("UPDATE subscription_package_change_requests SET status='pending_payment',checkout_url=?,metadata_json=?,updated_at=NOW() WHERE id=?");
        $update->execute([$checkout['checkout_url'], json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), (int)$row['id']]);

        $reload = mg_subscription_checkout_request_row($pdo, $requestId, $userId, true);
        $pdo->commit();

        mg_audit('subscription.checkout_session_created', 'subscription_package_change_request', [
            'request_id' => $requestId,
            'provider' => 'stripe',
            'provider_session_reference' => $checkout['provider_session_reference'],
        ], $userId);
        mg_event('subscription.checkout_session_created', ['request_id' => $requestId, 'provider' => 'stripe'], $userId);

        return ['request' => mg_subscription_package_change_public($reload), 'checkout_url' => $checkout['checkout_url'], 'duplicate' => false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_subscription_checkout_try_start(PDO $pdo, array $user, array $request): array
{
    if (($request['request_type'] ?? '') === 'enterprise') return $request;
    if (empty($request['request_id'])) return $request;
    if (!mg_subscription_checkout_enabled($pdo)) return $request;
    try {
        $checkout = mg_subscription_checkout_start($pdo, $user, (string)$request['request_id']);
        return is_array($checkout['request'] ?? null) ? $checkout['request'] : $request;
    } catch (Throwable) {
        return $request;
    }
}
