<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/finance/_money.php';
mg_require_method('GET');
$user=mg_require_api_user();
$walletId=trim((string)($_GET['wallet_id']??''));
$limit=max(1,min(100,(int)($_GET['limit']??50)));
$before=max(0,(int)($_GET['before_id']??0));
$pdo=mg_db();
$stmt=$pdo->prepare('SELECT * FROM wallets WHERE public_id=? LIMIT 1');
$stmt->execute([$walletId]);
$wallet=$stmt->fetch();
if(!$wallet)mg_fail('Wallet not found.',404);
$canViewForeign=mg_api_user_has_permission($user,'cashouts.manage')||mg_api_user_has_permission($user,'financial.reconciliation.manage');
if((int)$wallet['owner_user_id']!==(int)$user['id']&&!$canViewForeign)mg_fail('Permission denied.',403);
$sql="SELECT e.id,e.public_id entry_id,e.entry_type,e.amount_cents,e.currency,e.description,e.created_at,a.account_code,g.public_id group_id,g.transaction_type,g.source_type,g.source_reference,g.status group_status,g.posted_at FROM ledger_entries e INNER JOIN ledger_accounts a ON a.id=e.ledger_account_id INNER JOIN ledger_transaction_groups g ON g.id=e.transaction_group_id WHERE a.wallet_id=?";
$params=[(int)$wallet['id']];
if($before>0){$sql.=' AND e.id<?';$params[]=$before;}
$sql.=' ORDER BY e.id DESC LIMIT '.$limit;
$q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();
mg_ok(['wallet_id'=>$walletId,'entries'=>$rows,'next_before_id'=>$rows?(int)end($rows)['id']:null]);
