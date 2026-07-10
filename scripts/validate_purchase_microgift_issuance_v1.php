<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{
    $value=file_get_contents($root.'/'.$path);
    if(!is_string($value))throw new RuntimeException('Missing '.$path);
    return $value;
};

$capture=$read('api/payments/_capture.php');
$reconcile=$read('api/payments/_issuance_reconciliation.php');
$fulfillment=$read('api/payments/_fulfillment.php');
$summary=$read('api/commerce/_order_issuance_summary.php');
$confirmation=$read('api/commerce/order-confirmation.php');
$reconcileRoute=$read('api/commerce/order-issuance-reconcile.php');
$local=$read('api/payments/_local_confirmation.php');
$cash=$read('api/payments/cash-confirm.php');
$sandbox=$read('api/payments/sandbox-confirm.php');
$webhook=$read('api/payments/webhook.php');
$successJs=$read('assets/js/order-success.js');

$checks=[
    'atomic_capture_authority'=>str_contains($capture,'mg_payment_reconcile_paid_order(')&&str_contains($capture,"\$failureHook('after_fulfillment'")&&str_contains($capture,"'issuance_complete'"),
    'canonical_reconciler'=>str_contains($reconcile,'mg_payment_issue_order_pppm(')&&str_contains($reconcile,'mg_payment_issue_order_microgifts(')&&str_contains($reconcile,"'fulfillment.reconciled'"),
    'pppm_unit_issuance'=>str_contains($fulfillment,'for ($sequence=1; $sequence <= (int)$line[\'quantity\']; $sequence++)')&&str_contains($fulfillment,'pppm_issuance_request_id')&&str_contains($fulfillment,"'customer_purchase'"),
    'microgift_unit_idempotency'=>str_contains($fulfillment,"\$idempotencyKey='commerce-order-item:'")&&str_contains($fulfillment,'mg_microgift_existing_issue(')&&str_contains($fulfillment,'UPDATE microgift_instances SET pppm_item_id='),
    'action_center_delivery'=>str_contains($fulfillment,'mg_action_center_receive(')&&str_contains($summary,'COUNT(DISTINCT inbox.instance_id)')&&!str_contains($summary,"inbox.folder='inbox'"),
    'read_only_confirmation'=>!str_contains($confirmation,'beginTransaction')&&!str_contains($confirmation,'mg_payment_issue_order_')&&str_contains($confirmation,"'can_reconcile'"),
    'explicit_reconciliation'=>str_contains($reconcileRoute,"mg_require_method('POST')")&&str_contains($reconcileRoute,'mg_require_csrf_for_write')&&str_contains($reconcileRoute,'mg_payment_reconcile_paid_order('),
    'local_payment_replay_repair'=>str_contains($local,"payment_status']==='paid'")&&str_contains($local,'mg_finance_record_paid_order(')&&str_contains($cash,'mg_payment_confirm_local_session')&&str_contains($sandbox,'mg_payment_confirm_local_session'),
    'webhook_replay_repair'=>str_contains($webhook,'successful_webhook_replay')&&str_contains($webhook,'mg_payment_reconcile_paid_order(')&&str_contains($webhook,"!empty(\$result['duplicate'])"),
    'customer_delivery_status'=>str_contains($successJs,'data-order-reconcile')&&str_contains($successJs,'/api/commerce/order-issuance-reconcile.php')&&str_contains($successJs,'visibilitychange')&&str_contains($reconcile,"'microgift_delivery_ready'"),
];

$score=0;
foreach($checks as $name=>$passed){echo($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;if($passed)$score++;}
echo 'Score: '.$score.'/10'.PHP_EOL;
exit($score===10?0:1);
