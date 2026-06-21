<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/payments/_provider_credentials.php';
$user=mg_require_permission('admin.settings.manage');
$pdo=mg_db();
function mg_admin_payment_methods_payload(PDO $pdo): array{
  $row=mg_payment_platform_credential_row($pdo,'cash','test',false);
  return ['payment_methods'=>['cash'=>['enabled'=>$row?(bool)($row['enabled']??false):false,'mode'=>'test','label'=>'Pay with cash']]];
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET')mg_ok(mg_admin_payment_methods_payload($pdo));
mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
$enabled=!empty($input['cash_enabled'])?1:0;
try{
  $pdo->beginTransaction();
  $row=mg_payment_platform_credential_row($pdo,'cash','test',true);
  if($row){
    $pdo->prepare('UPDATE payment_platform_credentials SET enabled=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')->execute([$enabled,(int)$user['id'],(int)$row['id']]);
  }else{
    $pdo->prepare('INSERT INTO payment_platform_credentials (public_id,provider_key,mode,platform_fee_bps,fixed_fee_cents,enabled,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())')->execute([mg_public_uuid(),'cash','test',0,0,$enabled,(int)$user['id']]);
  }
  $pdo->commit();
  mg_ok(mg_admin_payment_methods_payload($pdo),$enabled?'Cash option enabled.':'Cash option disabled.');
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  mg_fail('Unable to save payment method settings.',500);
}
