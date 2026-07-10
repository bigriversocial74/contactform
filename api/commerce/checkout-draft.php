<?php
declare(strict_types=1);

require_once __DIR__ . '/_cart_checkout.php';

mg_require_method('POST');
$user = mg_require_api_user();
$input = mg_input();
mg_require_csrf_for_write($input);

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $draft = mg_cart_checkout_create_draft(
        $pdo,
        (int) $user['id'],
        trim((string) ($input['idempotency_key'] ?? ''))
    );
    $pdo->commit();
    mg_ok($draft, !empty($draft['duplicate']) ? 'Checkout draft resumed.' : 'Checkout draft created.', !empty($draft['duplicate']) ? 200 : 201);
} catch (MgCartCheckoutException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'commerce.checkout_draft_failed', 'Checkout draft creation failed.', [
        'exception_type' => get_class($error),
    ], (int) $user['id']);
    mg_fail('Unable to create checkout draft.', 500);
}
