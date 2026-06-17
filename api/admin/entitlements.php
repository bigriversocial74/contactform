<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/entitlements/_entitlements.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission('entitlements.manage');
$pdo=mg_db();
if($method==='GET'){
    $status=trim((string)($_GET['status']??''));
    $sql="SELECT e.public_id entitlement_id,e.status,e.entitlement_type,e.entitled_user_id,e.merchant_user_id,e.revocation_reason,e.updated_at,pi.public_id pppm_item_id,a.public_id asset_id,a.original_filename FROM entitlements e INNER JOIN pppm_items pi ON pi.id=e.pppm_item_id INNER JOIN catalog_assets a ON a.id=e.asset_id";
    $params=[];
    if($status!==''){$sql.=' WHERE e.status=?';$params[]=$status;}
    $sql.=' ORDER BY e.updated_at DESC LIMIT 200';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);mg_ok(['entitlements'=>$stmt->fetchAll()]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$entitlementId=trim((string)($input['entitlement_id']??''));$action=trim((string)($input['action']??''));$reason=trim((string)($input['reason']??'admin_policy'));
$stmt=$pdo->prepare('SELECT * FROM entitlements WHERE public_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$entitlementId]);$entitlement=$stmt->fetch();if(!$entitlement)mg_fail('Entitlement not found.',404);
$from=(string)$entitlement['status'];
if($action==='suspend'&&$from==='active'){$to='suspended';$sql="UPDATE entitlements SET status='suspended',suspended_at=NOW(),updated_at=NOW() WHERE id=?";$event='entitlement.suspended';}
elseif($action==='revoke'&&in_array($from,['active','suspended'],true)){$to='revoked';$sql="UPDATE entitlements SET status='revoked',revoked_at=NOW(),revocation_reason=?,updated_at=NOW() WHERE id=?";$event='entitlement.revoked';}
elseif($action==='restore'&&$from==='suspended'){$to='active';$sql="UPDATE entitlements SET status='active',suspended_at=NULL,updated_at=NOW() WHERE id=?";$event='entitlement.restored';}
else mg_fail('Invalid entitlement transition.',409);
if($action==='revoke')$pdo->prepare($sql)->execute([$reason,(int)$entitlement['id']]);else $pdo->prepare($sql)->execute([(int)$entitlement['id']]);
mg_entitlement_event($pdo,(int)$entitlement['id'],$event,$from,$to,(int)$user['id'],$reason,[]);
mg_audit($event,'entitlement',['entitlement_id'=>$entitlementId,'from'=>$from,'to'=>$to,'reason'=>$reason],(int)$user['id']);
mg_ok(['entitlement_id'=>$entitlementId,'status'=>$to],'Entitlement updated.');
