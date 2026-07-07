<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$action = strtolower(trim((string)($input['action'] ?? '')));
$purchaseId = trim((string)($input['purchase_id'] ?? $input['id'] ?? ''));
$note = trim((string)($input['note'] ?? ''));
if (!in_array($action, ['review_detail','credit_after_verified_review'], true)) mg_fail('Valid admin completion review action is required.', 422);
if ($purchaseId === '') mg_fail('purchase_id is required.', 422);

function mg_stamp_admin_review_latest_webhook(PDO $pdo, string $purchaseId, string $providerReference): array
{
    $stmt = $pdo->prepare('SELECT provider_key,provider_event_id,event_type,status,received_at,processed_at,failure_message FROM payment_webhook_events WHERE payload_json LIKE ? OR payload_json LIKE ? ORDER BY received_at DESC,id DESC LIMIT 1');
    $likePurchase = '%' . $purchaseId . '%';
    $likeProvider = $providerReference !== '' ? '%' . $providerReference . '%' : $likePurchase;
    $stmt->execute([$likePurchase, $likeProvider]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['available'=>false,'provider_key'=>'','provider_event_id'=>'','event_type'=>'','status'=>'none','received_at'=>null,'processed_at'=>null,'failure_message'=>''];
    return [
        'available'=>true,
        'provider_key'=>(string)$row['provider_key'],
        'provider_event_id'=>(string)$row['provider_event_id'],
        'event_type'=>(string)$row['event_type'],
        'status'=>(string)$row['status'],
        'received_at'=>$row['received_at'] ?? null,
        'processed_at'=>$row['processed_at'] ?? null,
        'failure_message'=>(string)($row['failure_message'] ?? ''),
    ];
}

function mg_stamp_admin_review_build(PDO $pdo, array $purchase, ?array $intent, ?array $providerIntent = null): array
{
    $providerReference = trim((string)($intent['provider_intent_reference'] ?? ''));
    $providerStatus = $providerIntent ? mg_payment_normalize_intent_status((string)($providerIntent['status'] ?? 'created')) : '';
    $amountMatch = $intent && (int)$intent['amount_cents'] === (int)$purchase['price_cents_snapshot'] && hash_equals((string)$intent['currency'], (string)$purchase['currency_snapshot']);
    $isCredited = (string)$purchase['status'] === 'credited' || trim((string)($purchase['credited_ledger_entry_public_id'] ?? '')) !== '';
    $eligible = $intent && !$isCredited && $amountMatch && $providerReference !== '' && $providerStatus === 'succeeded';
    return [
        'purchase'=>[
            'id'=>(string)$purchase['public_id'],
            'account_user_id'=>(int)$purchase['account_user_id'],
            'bundle_key'=>(string)$purchase['bundle_key'],
            'label'=>(string)$purchase['label_snapshot'],
            'stamps'=>(int)$purchase['stamps_snapshot'],
            'price_cents'=>(int)$purchase['price_cents_snapshot'],
            'currency'=>(string)$purchase['currency_snapshot'],
            'status'=>(string)$purchase['status'],
            'credited_ledger_entry_id'=>(string)($purchase['credited_ledger_entry_public_id'] ?? ''),
            'paid_at'=>$purchase['paid_at'] ?? null,
            'credited_at'=>$purchase['credited_at'] ?? null,
        ],
        'payment_intent'=>$intent,
        'provider_intent'=>$providerIntent,
        'webhook_event'=>mg_stamp_admin_review_latest_webhook($pdo, (string)$purchase['public_id'], $providerReference),
        'checks'=>[
            'not_already_credited'=>!$isCredited,
            'payment_intent_present'=>(bool)$intent,
            'provider_reference_present'=>$providerReference !== '',
            'provider_status_succeeded'=>$providerStatus === 'succeeded',
            'amount_currency_match'=>$amountMatch,
        ],
        'eligible_to_credit'=>$eligible,
    ];
}

$pdo = mg_db();
try {
    $lock = $action === 'credit_after_verified_review';
    if ($lock) $pdo->beginTransaction();
    $purchase = mg_stamp_purchase_load_any($pdo, $purchaseId, $lock);
    $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id'], $lock);
    if (!$intent) throw new RuntimeException('Stamp purchase payment intent is missing.', 409);
    $providerKey = strtolower((string)($intent['provider_key'] ?? ''));
    $providerReference = trim((string)($intent['provider_intent_reference'] ?? ''));
    if ($providerKey === '' || $providerReference === '') throw new RuntimeException('Provider payment reference is required.', 409);
    $providerIntent = mg_payment_provider_retrieve_intent($providerKey, $providerReference, $pdo);
    $review = mg_stamp_admin_review_build($pdo, $purchase, $intent, $providerIntent);

    if ($action === 'review_detail') {
        mg_audit('stamps.admin_completion_review_detail', 'stamp_purchase', ['purchase_id'=>(string)$purchase['public_id'], 'payment_intent_id'=>(string)($intent['public_id'] ?? ''), 'provider_key'=>$providerKey, 'provider_intent_reference'=>$providerReference, 'eligible_to_credit'=>(bool)$review['eligible_to_credit']], (int)$user['id']);
        mg_ok(['review'=>$review], 'Admin completion review loaded.');
        return;
    }

    if (!$review['eligible_to_credit']) {
        throw new RuntimeException('Stamp purchase is not eligible for verified admin recovery credit.', 409);
    }
    if ((int)$intent['amount_cents'] !== (int)$purchase['price_cents_snapshot'] || !hash_equals((string)$intent['currency'], (string)$purchase['currency_snapshot'])) {
        throw new RuntimeException('Payment amount or currency does not match the Stamp purchase snapshot.', 409);
    }
    $result = mg_stamp_purchase_complete_verified($pdo, $purchase, (int)$user['id'], 'succeeded', $providerReference, 'admin-verified-review:' . (int)$user['id']);
    mg_audit('stamps.admin_verified_recovery_credit', 'stamp_purchase', [
        'purchase_id'=>$result['purchase']['id'] ?? (string)$purchase['public_id'],
        'account_user_id'=>(int)$purchase['account_user_id'],
        'payment_intent_id'=>(string)($intent['public_id'] ?? ''),
        'provider_key'=>$providerKey,
        'provider_intent_reference'=>$providerReference,
        'provider_status'=>(string)($providerIntent['status'] ?? ''),
        'amount_cents'=>(int)$intent['amount_cents'],
        'currency'=>(string)$intent['currency'],
        'ledger_entry_id'=>$result['purchase']['credited_ledger_entry_id'] ?? null,
        'note'=>$note,
    ], (int)$user['id']);
    $pdo->commit();
    mg_ok($result, !empty($result['idempotent']) ? 'Stamp purchase already credited.' : 'Stamp purchase credited after verified admin review.', !empty($result['idempotent']) ? 200 : 201);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 599) $status = 409;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.admin_completion_review_failed','Stamp admin completion review failed.', ['purchase_id'=>$purchaseId,'action'=>$action,'exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to complete Stamp admin completion review.', 500);
}
