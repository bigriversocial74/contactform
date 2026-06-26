<?php
declare(strict_types=1);

require_once __DIR__ . '/_checkout_handoff.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

$requestId = trim((string)($input['request_id'] ?? $input['id'] ?? ''));

try {
    if ($requestId === '') throw new InvalidArgumentException('Package request ID is required.');
    $result = mg_subscription_checkout_start(mg_db(), $user, $requestId);
    mg_ok($result, $result['duplicate'] ? 'Existing subscription checkout session loaded.' : 'Subscription checkout session created.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (MgSubscriptionCheckoutException $e) {
    mg_fail($e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    mg_fail('Unable to create subscription checkout.', 500);
}
