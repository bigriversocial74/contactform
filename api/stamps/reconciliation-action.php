<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$purchaseId = trim((string)($input['purchase_id'] ?? $input['id'] ?? ''));
$action = strtolower(trim((string)($input['action'] ?? '')));
$note = trim((string)($input['note'] ?? ''));
if ($purchaseId === '') mg_fail('purchase_id is required.', 422);
if (!in_array($action, ['retry_checkout','mark_failed','mark_cancelled','mark_reviewed'], true)) mg_fail('Valid reconciliation action is required.', 422);

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $purchase = mg_stamp_purchase_load_any($pdo, $purchaseId, true);
    $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id'], true);
    $intentStatus = (string)($intent['status'] ?? '');
    $payload = ['purchase_id'=>(string)$purchase['public_id'], 'action'=>$action, 'note'=>$note];

    if ($action === 'retry_checkout') {
        if (!$intent) throw new RuntimeException('Stamp purchase payment intent is missing.', 409);
        if ((string)$purchase['status'] === 'credited') throw new RuntimeException('Credited Stamp purchases cannot be retried.', 409);
        if (in_array((string)$purchase['status'], ['failed','cancelled'], true) || in_array($intentStatus, ['failed','cancelled'], true)) {
            throw new RuntimeException('Failed or cancelled Stamp purchases must be reviewed before creating a new merchant checkout.', 409);
        }
        $checkout = mg_stamp_purchase_create_provider_checkout_session($pdo, $purchase, $intent);
        $payload['checkout_session'] = $checkout + ['available'=>true];
        $message = 'Provider checkout retry session created.';
    } elseif ($action === 'mark_failed') {
        if ((string)$purchase['status'] === 'credited') throw new RuntimeException('Credited Stamp purchases cannot be marked failed.', 409);
        if ($intentStatus === 'succeeded') throw new RuntimeException('Succeeded provider payments cannot be marked failed. Review the webhook or ledger credit path instead.', 409);
        if ($intent) {
            $pdo->prepare("UPDATE payment_intents SET status='failed',failure_message=COALESCE(NULLIF(?,''),failure_message,'Admin reconciliation marked failed.'),updated_at=NOW() WHERE id=? AND status<>'succeeded'")
                ->execute([$note, (int)$intent['id']]);
        }
        $pdo->prepare("UPDATE stamp_purchases SET status='failed',updated_at=NOW() WHERE id=? AND status<>'credited'")
            ->execute([(int)$purchase['id']]);
        $payload['receipt_notification'] = mg_stamp_receipt_notify_merchant($pdo, $purchase, 'failed', (int)$user['id'], ['payment_intent_id'=>(string)($intent['public_id'] ?? ''),'note'=>$note]);
        $message = 'Stamp purchase marked failed for reconciliation cleanup.';
    } elseif ($action === 'mark_cancelled') {
        if ((string)$purchase['status'] === 'credited') throw new RuntimeException('Credited Stamp purchases cannot be cancelled.', 409);
        if ($intentStatus === 'succeeded') throw new RuntimeException('Succeeded provider payments cannot be cancelled. Review the webhook or ledger credit path instead.', 409);
        if ($intent && (string)($intent['status'] ?? '') !== 'failed') {
            $pdo->prepare("UPDATE payment_intents SET status='cancelled',failure_message=COALESCE(NULLIF(?,''),failure_message,'Admin reconciliation cancelled checkout.'),updated_at=NOW() WHERE id=? AND status NOT IN ('succeeded','failed')")
                ->execute([$note, (int)$intent['id']]);
        }
        $pdo->prepare("UPDATE stamp_purchases SET status='cancelled',updated_at=NOW() WHERE id=? AND status<>'credited'")
            ->execute([(int)$purchase['id']]);
        $payload['receipt_notification'] = mg_stamp_receipt_notify_merchant($pdo, $purchase, 'cancelled', (int)$user['id'], ['payment_intent_id'=>(string)($intent['public_id'] ?? ''),'note'=>$note]);
        $message = 'Stamp purchase marked cancelled for reconciliation cleanup.';
    } else {
        $message = 'Stamp purchase marked reviewed in the audit trail.';
    }

    mg_audit('stamps.purchase_reconciliation_' . $action, 'stamp_purchase', [
        'purchase_id'=>(string)$purchase['public_id'],
        'account_user_id'=>(int)$purchase['account_user_id'],
        'payment_intent_id'=>(string)($intent['public_id'] ?? ''),
        'provider_intent_reference'=>(string)($intent['provider_intent_reference'] ?? ''),
        'receipt_notification'=>$payload['receipt_notification'] ?? null,
        'note'=>$note,
    ], (int)$user['id']);

    $updatedPurchase = mg_stamp_purchase_load_any($pdo, (string)$purchase['public_id'], false);
    $updatedIntent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id'], false);
    $payload['record'] = mg_stamp_purchase_payload($pdo, $updatedPurchase, null, $updatedIntent);
    $pdo->commit();
    mg_ok($payload, $message);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 599) $status = 409;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.reconciliation_action_failed','Stamp reconciliation action failed.', ['purchase_id'=>$purchaseId,'action'=>$action,'exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to apply Stamp reconciliation action.', 500);
}
