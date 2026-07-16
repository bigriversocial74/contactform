<?php
declare(strict_types=1);

require_once __DIR__ . '/_checkout_handoff.php';
require_once __DIR__ . '/_billing_lifecycle_v2.php';

function mg_subscription_upgrade_wants_json(array $input): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    return (($input['response'] ?? '') === 'json') || str_contains($accept, 'application/json') || str_contains($contentType, 'application/json') || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
}

function mg_subscription_upgrade_checkout_message(array $request, bool $checkoutAttempted): string
{
    if (($request['request_type'] ?? '') === 'enterprise') return 'Enterprise package request submitted for review.';
    if (($request['status'] ?? '') === 'approved' && !empty($request['scheduled_effective_at'])) return 'Package change scheduled for the next billing period.';
    if (!empty($request['checkout_url'])) return $checkoutAttempted ? 'Secure billing session created.' : 'Existing secure billing session loaded.';
    return 'Package request saved. Subscription billing is not configured yet.';
}

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

$plan = trim((string)($input['plan'] ?? $input['package_id'] ?? $input['requested_package_id'] ?? ''));
$billingCycle = mg_subscription_billing_v2_cycle((string)($input['billing_cycle'] ?? $input['cycle'] ?? 'month'));
$note = trim((string)($input['note'] ?? ''));
$wantsJson = mg_subscription_upgrade_wants_json($input);

try {
    if ($plan === '') throw new InvalidArgumentException('Choose a package before continuing to billing.');

    $pdo = mg_db();
    $request = mg_subscription_billing_v2_request($pdo, $user, $plan, $billingCycle, $note);
    $checkoutAttempted = false;
    $checkoutError = null;

    if (($request['request_type'] ?? '') !== 'enterprise') {
        $canonical = mg_platform_account_subscription_snapshot($pdo, (int)$user['id'], false);
        $hasStripeSubscription = $canonical
            && (string)($canonical['provider_key'] ?? '') === 'stripe'
            && trim((string)($canonical['provider_subscription_id'] ?? '')) !== ''
            && in_array((string)($canonical['status'] ?? ''), MG_SUBSCRIPTION_PACKAGE_CHANGE_ACTIVE_STATUSES, true);

        try {
            if ($hasStripeSubscription && in_array((string)($request['request_type'] ?? ''), ['downgrade','lateral'], true)) {
                $checkoutAttempted = true;
                $request = mg_subscription_billing_v2_schedule_change($pdo, $user, $request);
            } elseif ($hasStripeSubscription) {
                $checkoutAttempted = true;
                $request = mg_subscription_billing_v2_attach_portal($pdo, $user, $request);
            } elseif (empty($request['checkout_url'])) {
                $checkoutAttempted = true;
                $request = mg_subscription_checkout_try_start($pdo, $user, $request);
            }
        } catch (Throwable $billingException) {
            $checkoutError = $billingException->getMessage();
            mg_security_log('warning', 'subscription.billing_v2_start_failed', 'Subscription billing did not start after package request.', [
                'request_id' => (string)($request['request_id'] ?? ''),
                'requested_package_id' => (string)($request['requested_package_id'] ?? ''),
                'billing_cycle' => $billingCycle,
                'exception' => $checkoutError,
            ], (int)($user['id'] ?? 0));
        }
    }

    $redirect = !empty($request['checkout_url'])
        ? (string)$request['checkout_url']
        : '/account-subscriptions.php?upgrade=requested&request=' . rawurlencode((string)$request['request_id']);

    if ($wantsJson) {
        mg_ok([
            'request' => $request,
            'redirect' => $redirect,
            'checkout_started' => !empty($request['checkout_url']),
            'checkout_attempted' => $checkoutAttempted,
            'checkout_error' => $checkoutError,
            'billing_cycle' => $billingCycle,
        ], mg_subscription_upgrade_checkout_message($request, $checkoutAttempted));
    }

    header('Cache-Control: no-store, private');
    header('Location: ' . $redirect, true, 303);
    exit;
} catch (InvalidArgumentException $e) {
    if ($wantsJson) mg_fail($e->getMessage(), 422);
    header('Cache-Control: no-store, private');
    header('Location: /account-subscriptions.php?upgrade=error&message=' . rawurlencode($e->getMessage()), true, 303);
    exit;
} catch (MgSubscriptionBillingV2Exception|MgSubscriptionCheckoutException $e) {
    if ($wantsJson) mg_fail($e->getMessage(), $e->httpStatus);
    header('Cache-Control: no-store, private');
    header('Location: /account-subscriptions.php?upgrade=error&message=' . rawurlencode($e->getMessage()), true, 303);
    exit;
} catch (Throwable $e) {
    mg_security_log('error', 'subscription.package_change_request_failed', 'Subscription package change request failed.', ['exception' => $e->getMessage()], (int)($user['id'] ?? 0));
    if ($wantsJson) mg_fail('Unable to start subscription billing.', 500);
    header('Cache-Control: no-store, private');
    header('Location: /account-subscriptions.php?upgrade=error&message=' . rawurlencode('Unable to start subscription billing.'), true, 303);
    exit;
}
