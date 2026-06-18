<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/finance/_cashouts.php';
mg_require_method('POST');
$user=mg_require_permission('payout_holds.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();
$action=trim((string)($input['action']??'create'));
$pdo->beginTransaction();
try{
    if($action==='create'){
        $walletPublicId=trim((string)($input['wallet_id']??''));
        $stmt=$pdo->prepare("SELECT * FROM wallets WHERE public_id=? AND status='active' LIMIT 1 FOR UPDATE");
        $stmt->execute([$walletPublicId]);
        $wallet=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$wallet)throw new MgCashoutWorkflowException('Wallet not found.',404);
        $result=mg_payout_hold_create($pdo,$wallet,(int)($input['amount_cents']??0),trim((string)($input['reason']??'')),(int)$user['id'],$input['expires_at']??null);
        $pdo->commit();
        mg_ok($result,'Payout hold created.',201);
    }
    if($action==='release'){
        $result=mg_payout_hold_release($pdo,trim((string)($input['hold_id']??'')),(int)$user['id']);
        $pdo->commit();
        mg_ok($result,'Payout hold released.');
    }
    throw new MgCashoutWorkflowException('Invalid payout hold action.',422);
}catch(MgCashoutWorkflowException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to update payout hold.',500);
}
