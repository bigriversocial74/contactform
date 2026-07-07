<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$purchaseId = trim((string)($input['purchase_id'] ?? $input['purchase'] ?? ''));
if ($purchaseId === '') mg_fail('purchase_id is required.', 422);

$pdo = mg_db();
try {
    $accountUserId = (int)$user['id'];
    $purchase = mg_stamp_purchase_load($pdo, $accountUserId, $purchaseId, '', false);
    $notification = mg_stamp_receipt_notify_merchant($pdo, $purchase, 'receipt_sent', $accountUserId, ['manual_resend'=>true]);
    mg_audit('stamps.receipt_notification_resent', 'stamp_purchase', [
        'purchase_id'=>(string)$purchase['public_id'],
        'account_user_id'=>$accountUserId,
        'notification'=>$notification,
        'surface'=>'merchant_notifications',
        'inbox_receipt'=>false,
    ], $accountUserId);
    mg_ok(['purchase_id'=>(string)$purchase['public_id'],'notification'=>$notification,'receipt_url'=>mg_stamp_receipt_notification_url($purchase),'surface'=>'merchant_notifications'], 'Stamp receipt notification sent.');
} catch (RuntimeException $error) {
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 599) $status = 404;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('warning','stamps.receipt_notification_resend_failed','Unable to resend Stamp receipt notification.', ['purchase_id'=>$purchaseId,'exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to send Stamp receipt notification.', 500);
}
