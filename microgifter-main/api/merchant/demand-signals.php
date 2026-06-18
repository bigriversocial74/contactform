<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/demand/_demand.php';

$user=mg_require_permission('demand.signals.manage');
$pdo=mg_db();
if($_SERVER['REQUEST_METHOD']==='GET'){
    $status=trim((string)($_GET['status']??'open'));
    if(!in_array($status,['open','acknowledged','resolved','expired','all'],true))mg_fail('Invalid demand signal status.',422);
    $sql='SELECT public_id,location_id,product_id,signal_key,signal_level,status,observed_value,baseline_value,confidence_score,summary,recommendation_json,triggered_at,acknowledged_at,resolved_at,expires_at FROM demand_agent_signals WHERE merchant_user_id=?';
    $params=[(int)$user['id']];
    if($status!=='all'){$sql.=' AND status=?';$params[]=$status;}
    $sql.=' ORDER BY FIELD(signal_level,\'critical\',\'warning\',\'opportunity\',\'info\'),triggered_at DESC LIMIT 100';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);mg_ok(['status'=>$status,'signals'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);
$publicId=trim((string)($input['signal_id']??''));$action=trim((string)($input['action']??''));
if($publicId===''||!in_array($action,['acknowledge','resolve','reopen'],true))mg_fail('Demand signal and valid action are required.',422);
$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare('SELECT * FROM demand_agent_signals WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$publicId,(int)$user['id']]);$signal=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$signal)throw new RuntimeException('Demand signal not found.');
    $to=match($action){'acknowledge'=>'acknowledged','resolve'=>'resolved',default=>'open'};
    $pdo->prepare('UPDATE demand_agent_signals SET status=?,acknowledged_at=IF(?=\'acknowledged\',NOW(),acknowledged_at),resolved_at=IF(?=\'resolved\',NOW(),IF(?=\'open\',NULL,resolved_at)),updated_at=NOW() WHERE id=?')
        ->execute([$to,$to,$to,$to,(int)$signal['id']]);
    $pdo->commit();
    mg_audit('demand.signal_'.$action,'demand_signal',['signal_id'=>$publicId,'from_status'=>$signal['status'],'to_status'=>$to],(int)$user['id']);
    mg_ok(['signal_id'=>$publicId,'status'=>$to],'Demand signal updated.');
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),404);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update demand signal.',500);}
