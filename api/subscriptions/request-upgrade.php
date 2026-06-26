<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_changes.php';

function mg_subscription_upgrade_wants_json(array $input): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    return (($input['response'] ?? '') === 'json') || str_contains($accept, 'application/json') || str_contains($contentType, 'application/json') || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
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
        throw new InvalidArgumentException('Choose a package before requesting an upgrade.');
    }

    $pdo = mg_db();
    $request = mg_subscription_package_change_request($pdo, $user, $plan, $note);
    $redirect = $request['checkout_url'] ?: '/account-subscriptions.php?upgrade=requested&request=' . rawurlencode((string)$request['request_id']);

    if ($wantsJson) {
        mg_ok(['request' => $request, 'redirect' => $redirect], $request['duplicate'] ? 'Existing package request returned.' : 'Package request submitted.');
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
    if ($wantsJson) mg_fail('Unable to submit package request.', 500);
    header('Cache-Control: no-store, private');
    header('Location: /account-subscriptions.php?upgrade=error&message=' . rawurlencode('Unable to submit package request.'), true, 303);
    exit;
}
