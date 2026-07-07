<?php
declare(strict_types=1);

require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$purchaseId = trim((string)($input['purchase_id'] ?? $input['id'] ?? ''));
if ($purchaseId === '') mg_fail('purchase_id is required.', 422);

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $purchase = mg_stamp_purchase_load($pdo, (int)$user['id'], $purchaseId, '', true);
    $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id'], true);
    if (!$intent) throw new RuntimeException('Stamp purchase payment intent is missing.');

    if ((string)$purchase['status'] === 'credited') {
        $payload = mg_stamp_purchase_payload($pdo, $purchase, null, $intent);
        $payload['checkout_session'] = [
            'available' => false,
            'reason' => 'already_credited',
            'checkout_url' => '',
        ];
        $pdo->commit();
        mg_ok($payload, 'Stamp purchase is already credited.');
        return;
    }

    $checkout = mg_stamp_purchase_create_provider_checkout_session($pdo, $purchase, $intent);
    $payload = mg_stamp_purchase_payload($pdo, $purchase, null, mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id'], false));
    $payload['checkout_session'] = $checkout + ['available' => true];

    $pdo->commit();
    mg_audit('stamps.purchase_provider_checkout_created', 'stamp_purchase', [
        'purchase_id' => (string)$purchase['public_id'],
        'payment_intent_id' => (string)($intent['public_id'] ?? ''),
        'provider' => (string)$checkout['provider'],
        'provider_session_reference' => (string)$checkout['provider_session_reference'],
        'source_type' => 'stamp_purchase',
    ], (int)$user['id']);
    mg_ok($payload, 'Secure provider checkout session created.', 201);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 599) $status = 409;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'stamps.provider_checkout_failed', 'Unable to create Stamp provider checkout session.', [
        'purchase_id' => $purchaseId,
        'exception_class' => $error::class,
    ], (int)$user['id']);
    mg_fail('Unable to create Stamp provider checkout session.', 500);
}
