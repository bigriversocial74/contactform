<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/payments/_payments.php';
require_once dirname(__DIR__) . '/finance/_cashouts.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_api_user();
$pdo=mg_db();
if($method==='GET'){
    $stmt=$pdo->prepare('SELECT c.public_id cashout_id,w.public_id wallet_id,c.amount_cents,c.currency,c.status,c.failure_message,c.approved_at,c.cancelled_at,c.paid_at,c.created_at,c.updated_at,p.public_id payout_id,p.status payout_status FROM cashout_requests c INNER JOIN wallets w ON w.id=c.wallet_id LEFT JOIN cashout_payout_links l ON l.cashout_request_id=c.id LEFT JOIN merchant_payouts p ON p.id=l.payout_id WHERE c.requested_by_user_id=? ORDER BY c.id DESC LIMIT 100');
    $stmt->execute([(int)$user['id']]);
    mg_ok(['cashouts'=>$stmt->fetchAll()]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
try{
    $wallet=mg_wallet_require_owner($pdo,trim((string)($input['wallet_id']??'')),(int)$user['id']);
    $cashout=mg_cashout_request($pdo,$wallet,(int)$user['id'],(int)($input['amount_cents']??0),trim((string)($input['idempotency_key']??'')));
    mg_ok(['cashout_id'=>$cashout['public_id'],'status'=>$cashout['status'],'amount_cents'=>(int)$cashout['amount_cents'],'currency'=>$cashout['currency'],'duplicate'=>(bool)($cashout['duplicate']??false)],'Cashout requested.',!empty($cashout['duplicate'])?200:201);
}catch(MgCashoutWorkflowException $e){
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    mg_fail('Unable to request cashout.',500);
}
