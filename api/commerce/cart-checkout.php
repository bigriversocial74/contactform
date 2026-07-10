<?php
declare(strict_types=1);

require_once __DIR__ . '/_cart_checkout.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $result = mg_cart_checkout_run(
        mg_db(),
        (int) $user['id'],
        (string) ($input['workflow_key'] ?? ''),
        (string) ($input['provider_key'] ?? '')
    );
    mg_audit('commerce.cart_checkout_ready', 'commerce_order', [
        'order_id' => $result['order']['order_id'] ?? null,
        'checkout_session_id' => $result['session']['checkout_session_id'] ?? null,
        'provider' => $result['provider'],
        'reused' => $result['reused'],
    ], (int) $user['id']);
    mg_ok($result, $result['reused'] ? 'Checkout resumed.' : 'Checkout ready.', $result['reused'] ? 200 : 201);
} catch (MgCartCheckoutException $error) {
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (MgCheckoutWorkflowException $error) {
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (MgCheckoutSessionException $error) {
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    mg_security_log('error', 'commerce.cart_checkout_failed', 'Cart checkout workflow failed.', [
        'exception_type' => get_class($error),
    ], (int) $user['id']);
    mg_fail('Unable to prepare checkout.', 500);
}
