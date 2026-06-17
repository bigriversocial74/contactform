<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/tips/_tips.php';
require_once dirname(__DIR__) . '/tips/_notifications.php';

mg_require_method('POST');
$user=mg_require_permission('tips.reverse');
$input=mg_input();
mg_require_csrf_for_write($input);
$tipPublicId=trim((string)($input['tip_id']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
$reason=trim((string)($input['reason']??''));
if($tipPublicId===''||$idempotencyKey===''||$reason==='')mg_fail('Tip, idempotency key, and reason are required.',422);
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $result=mg_tip_reverse($pdo,(int)$user['id'],$tipPublicId,$idempotencyKey,$reason);
    $alertId=null;
    if(empty($result['duplicate'])){
        $alertId=mg_tip_notify_reversal($pdo,$result['tip'],$reason);
    }
    $pdo->commit();
    if(empty($result['duplicate'])){
        mg_audit('tip.reversed','tip',['tip_id'=>$tipPublicId,'reversal_id'=>$result['reversal']['public_id'],'reason'=>$reason],(int)$user['id']);
        mg_event('tip.reversed',['tip_id'=>$tipPublicId,'reversal_id'=>$result['reversal']['public_id'],'recipient_user_id'=>(int)$result['tip']['recipient_user_id']],(int)$user['id']);
    }
    mg_ok([
        'tip_id'=>$tipPublicId,
        'reversal_id'=>$result['reversal']['public_id'],
        'reversal_group_id'=>$result['reversal_group_id'],
        'alert_id'=>$alertId,
        'duplicate'=>(bool)$result['duplicate'],
    ],$result['duplicate']?'Existing reversal returned.':'Tip reversed.',$result['duplicate']?200:201);
}catch(RuntimeException|InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','tip.reversal_failed','Tip reversal failed.',['exception'=>$e->getMessage()],(int)$user['id']);mg_fail('Unable to reverse tip.',500);}
