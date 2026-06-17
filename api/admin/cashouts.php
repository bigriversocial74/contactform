<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/payments/_payments.php';
require_once dirname(__DIR__) . '/finance/_cashouts.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission('cashouts.manage');
$pdo=mg_db();
if($method==='GET'){
    $status=trim((string)($_GET['status']??''));
    $sql='SELECT c.*,w.public_id wallet_public_id,w.owner_type,w.owner_user_id,p.public_id payout_public_id,p.status payout_status FROM cashout_requests c INNER JOIN wallets w ON w.id=c.wallet_id LEFT JOIN cashout_payout_links l ON l.cashout_request_id=c.id LEFT JOIN merchant_payouts p ON p.id=l.payout_id';
    $params=[];
    if($status!==''){$sql.=' WHERE c.status=?';$params[]=$status;}
    $sql.=' ORDER BY c.id DESC LIMIT 200';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);mg_ok(['cashouts'=>$stmt->fetchAll()]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$cashoutId=trim((string)($input['cashout_id']??''));$action=trim((string)($input['action']??''));
try{
    $stmt=$pdo->prepare('SELECT * FROM cashout_requests WHERE public_id=? LIMIT 1');$stmt->execute([$cashoutId]);$cashout=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$cashout)throw new MgCashoutWorkflowException('Cashout not found.',404);
    if($action==='approve')mg_ok(mg_cashout_approve($pdo,$cashout,(int)$user['id']),'Cashout approved.');
    if($action==='cancel')mg_ok(mg_cashout_cancel($pdo,$cashout,(int)$user['id']),'Cashout cancelled.');
    throw new MgCashoutWorkflowException('Invalid cashout action.',422);
}catch(MgCashoutWorkflowException $e){
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    mg_fail('Unable to update cashout.',500);
}
