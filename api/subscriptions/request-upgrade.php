<?php
declare(strict_types=1);

require_once __DIR__ . '/_checkout_handoff.php';

function mg_subscription_upgrade_wants_json(array $input): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    return (($input['response'] ?? '') === 'json') || str_contains($accept, 'application/json') || str_contains($contentType, 'application/json') || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
}

function mg_subscription_upgrade_checkout_message(array $request, bool $checkoutAttempted): string
{
    if (($request['request_type'] ?? '') === 'enterprise') {
        return 'Enterprise package request submitted for review.';
    }
    if (!empty($request['checkout_url'])) {
        return $checkoutAttempted ? 'Checkout session created.' : 'Existing checkout session loaded.';
    }
    return 'Package request saved. Subscription checkout is not configured yet.';
}

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

$plan = trim((string)($input['plan'] ?? $input['package_id'] ?? $input['requested_package_id'] ?? ''));
$note = trim((string)($input['note'] ?? ''));
$wantsJson = mg_subscription_upgrade_wants_json($input);

try {
    if ($plan === '') {
        throw new InvalidArgumentException('Choose a package before continuing to checkout.');
    }

    $pdo = mg_db();
    $request = mg_subscription_package_change_request($pdo, $user, $plan, $note);
    $checkoutAttempted = false;
    $checkoutError = null;

    if (($request['request_type'] ?? '') !== 'enterprise' && empty($request['checkout_url'])) {
        try {
            $checkoutAttempted = true;
            $request = mg_subscription_checkout_try_start($pdo, $user, $request);
        } catch (Throwable $checkoutException) {
            $checkoutError = $checkoutException->getMessage();
            mg_security_log('warning', 'subscription.checkout_auto_start_failed', 'Subscription checkout did not start after package request.', [
                'request_id' => (string)($request['request_id'] ?? ''),
                'requested_package_id' => (string)($request['requested_package_id'] ?? ''),
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
} catch (Throwable $e) {
    mg_security_log('error', 'subscription.package_change_request_failed', 'Subscription package change request failed.', ['exception' => $e->getMessage()], (int)($user['id'] ?? 0));
    if ($wantsJson) mg_fail('Unable to start package checkout.', 500);
    header('Cache-Control: no-store, private');
    header('Location: /account-subscriptions.php?upgrade=error&message=' . rawurlencode('Unable to start package checkout.'), true, 303);
    exit;
}
