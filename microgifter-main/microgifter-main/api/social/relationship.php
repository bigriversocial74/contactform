<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';

mg_require_method('POST');
$user=mg_require_permission('social.engage');
$input=mg_input();
mg_require_csrf_for_write($input);

$actorId=(int)$user['id'];
$targetReference=trim((string)($input['profile_id']??$input['user_id']??''));
$action=trim((string)($input['action']??''));
if($targetReference===''||!in_array($action,['follow','unfollow','mute','unmute','block','unblock'],true)){
    mg_fail('Profile and valid relationship action are required.',422);
}

mg_rate_limit('social.relationship.write','user:'.$actorId,90,60);

try{
    $key=mg_engagement_key($input);
    $fingerprint=mg_engagement_fingerprint('relationship.'.$action,['profile_id'=>$targetReference]);
    $pdo=mg_db();
    $pdo->beginTransaction();
    $replay=mg_engagement_claim($pdo,$actorId,'relationship.'.$action,$key,$fingerprint);
    if($replay!==null){
        $pdo->commit();
        mg_ok($replay,'Existing relationship result returned.');
    }
    $result=mg_engagement_relationship($pdo,$actorId,$targetReference,$action);
    $result=mg_engagement_complete($pdo,$actorId,$key,$result);
    $pdo->commit();

    mg_audit('social.relationship_'.$action,'public_profile',[
        'profile_id'=>$result['profile_id'],
        'profile_slug'=>$result['profile_slug'],
        'following'=>$result['relationship']['following'],
    ],$actorId);
    mg_event('social.relationship_'.$action,[
        'profile_id'=>$result['profile_id'],
        'following'=>$result['relationship']['following'],
    ],$actorId);
    mg_ok($result,'Relationship updated.');
}catch(InvalidArgumentException $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','social.relationship_failed','Relationship mutation failed.',['action'=>$action,'exception_class'=>$error::class],$actorId);
    mg_fail('Unable to update relationship.',500);
}
