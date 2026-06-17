<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';
require_once __DIR__ . '/_notifications.php';

mg_require_method('POST');
$user=mg_require_permission('tips.create');
$input=mg_input();
mg_require_csrf_for_write($input);
$actorId=(int)$user['id'];
$tipId=trim((string)($input['tip_id']??''));
mg_rate_limit('tips.confirm','user:'.$actorId,80,60);

try{
    $key=mg_tip_confirmation_key($input);
    $pdo=mg_db();
    $pdo->beginTransaction();
    $before=$pdo->prepare("SELECT status FROM tips WHERE public_id=? AND sender_user_id=? AND funding_type='stripe' LIMIT 1 FOR UPDATE");
    $before->execute([$tipId,$actorId]);
    $beforeStatus=$before->fetchColumn();
    if($beforeStatus===false)throw new RuntimeException('Tip payment is not available.');

    $result=mg_tip_confirm_card($pdo,$actorId,$tipId,$key);
    if($beforeStatus!=='posted'&&$result['status']==='posted'&&!$result['duplicate']){
        $tipStmt=$pdo->prepare('SELECT * FROM tips WHERE public_id=? LIMIT 1');
        $tipStmt->execute([$tipId]);
        $tip=$tipStmt->fetch(PDO::FETCH_ASSOC);
        if($tip)$result['alert_id']=mg_tip_notify_recipient($pdo,$tip);
    }
    $pdo->commit();

    mg_audit('tip.confirmed','tip',[
        'tip_id'=>$tipId,
        'status'=>$result['status'],
        'payment_intent_id'=>$result['payment_intent_id'],
        'duplicate'=>$result['duplicate'],
    ],$actorId);
    mg_event('tip.confirmation_checked',[
        'tip_id'=>$tipId,
        'status'=>$result['status'],
        'posted'=>$result['posted'],
    ],$actorId);
    mg_ok($result,$result['posted']?'Card-funded tip confirmed.':'Tip payment still requires confirmation.');
}catch(InvalidArgumentException $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','tip.confirm_failed','Tip confirmation failed.',['exception_class'=>$error::class],$actorId);
    mg_fail('Unable to confirm tip payment.',500);
}
