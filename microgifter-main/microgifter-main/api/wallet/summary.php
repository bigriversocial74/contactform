<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/finance/_money.php';
mg_require_method('GET');
$user=mg_require_api_user();
$currency=mg_money_currency((string)($_GET['currency']??'USD'));
$ownerType=trim((string)($_GET['owner_type']??'user'));
$ownerUserId=(int)($_GET['owner_user_id']??$user['id']);
$allowedOwnerTypes=['user','merchant','creator','organization','enterprise'];
if(!in_array($ownerType,$allowedOwnerTypes,true)||$ownerUserId<1)mg_fail('Invalid wallet owner.',422);
$canViewForeign=mg_api_user_has_permission($user,'cashouts.manage')||mg_api_user_has_permission($user,'financial.reconciliation.manage');
if($ownerUserId!==(int)$user['id']&&!$canViewForeign)mg_fail('Permission denied.',403);
$pdo=mg_db();
$stmt=$pdo->prepare('SELECT * FROM wallets WHERE owner_type=? AND owner_user_id=? AND currency=? LIMIT 1');
$stmt->execute([$ownerType,$ownerUserId,$currency]);
$wallet=$stmt->fetch();
if(!$wallet){
    mg_ok(['wallet'=>null,'balances'=>['available_cents'=>0,'pending_cents'=>0,'held_cents'=>0,'cashout_pending_cents'=>0,'paid_cents'=>0,'currency'=>$currency,'calculated_at'=>gmdate('c')]]);
}
$balances=mg_wallet_balances($pdo,(int)$wallet['id']);
mg_ok(['wallet'=>['wallet_id'=>$wallet['public_id'],'owner_type'=>$wallet['owner_type'],'owner_user_id'=>(int)$wallet['owner_user_id'],'currency'=>$wallet['currency'],'status'=>$wallet['status']],'balances'=>$balances]);
