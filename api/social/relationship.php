<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';
require_once __DIR__ . '/_follow_notification.php';

function mg_relationship_user_target(PDO $pdo,string $reference): array
{
    $reference=trim($reference);
    if($reference===''||strlen($reference)>190)throw new InvalidArgumentException('User is required.');
    $stmt=$pdo->prepare(
        "SELECT u.id user_id,u.public_id user_public_id,pp.public_id profile_public_id,pp.slug profile_slug
         FROM users u
         LEFT JOIN public_profiles pp ON pp.user_id=u.id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
         WHERE (u.public_id=? OR u.email=?) AND u.status='active'
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$reference,$reference]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('User is not available.');
    return $row;
}

function mg_relationship_apply_to_user(PDO $pdo,int $actorId,string $targetReference,string $action): array
{
    if(!in_array($action,['follow','unfollow','mute','unmute','block','unblock'],true))throw new InvalidArgumentException('Invalid relationship action.');
    $target=mg_relationship_user_target($pdo,$targetReference);
    $targetId=(int)$target['user_id'];
    if($targetId===$actorId)throw new InvalidArgumentException('You cannot change a relationship with yourself.');

    if($action==='follow'){
        if(mg_social_is_blocked($pdo,$actorId,$targetId))throw new RuntimeException('User is not available.');
        $existing=$pdo->prepare("SELECT status FROM social_follows WHERE follower_user_id=? AND followed_user_id=? LIMIT 1 FOR UPDATE");
        $existing->execute([$actorId,$targetId]);
        $wasFollowing=(string)($existing->fetchColumn()?:'')==='active';
        $pdo->prepare("INSERT INTO social_follows (follower_user_id,followed_user_id,status,created_at,updated_at) VALUES (?,?,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE status='active',updated_at=NOW()")
            ->execute([$actorId,$targetId]);
        if(!$wasFollowing)mg_social_notify($pdo,$targetId,$actorId,'new_follower','New follower','Someone followed your profile.','');
    }elseif($action==='unfollow'){
        $pdo->prepare('DELETE FROM social_follows WHERE follower_user_id=? AND followed_user_id=?')->execute([$actorId,$targetId]);
    }elseif($action==='mute'){
        if(mg_social_is_blocked($pdo,$actorId,$targetId))throw new RuntimeException('User is not available.');
        $pdo->prepare('INSERT IGNORE INTO social_mutes (muting_user_id,muted_user_id,created_at) VALUES (?,?,NOW())')->execute([$actorId,$targetId]);
    }elseif($action==='unmute'){
        $pdo->prepare('DELETE FROM social_mutes WHERE muting_user_id=? AND muted_user_id=?')->execute([$actorId,$targetId]);
    }elseif($action==='block'){
        $pdo->prepare('INSERT IGNORE INTO social_blocks (blocking_user_id,blocked_user_id,created_at) VALUES (?,?,NOW())')->execute([$actorId,$targetId]);
        $pdo->prepare('DELETE FROM social_follows WHERE (follower_user_id=? AND followed_user_id=?) OR (follower_user_id=? AND followed_user_id=?)')
            ->execute([$actorId,$targetId,$targetId,$actorId]);
        $pdo->prepare('DELETE FROM social_mutes WHERE muting_user_id=? AND muted_user_id=?')->execute([$actorId,$targetId]);
    }else{
        $pdo->prepare('DELETE FROM social_blocks WHERE blocking_user_id=? AND blocked_user_id=?')->execute([$actorId,$targetId]);
    }

    $profileId=(string)($target['profile_public_id']?:$target['user_public_id']?:$targetReference);
    return [
        'action'=>$action,
        'profile_id'=>$profileId,
        'profile_slug'=>(string)($target['profile_slug']??''),
        'relationship'=>mg_engagement_relationship_state($pdo,$actorId,$targetId),
    ];
}

mg_require_method('POST');
$user=mg_require_permission('social.engage');
$input=mg_input();
mg_require_csrf_for_write($input);

$actorId=(int)$user['id'];
$profileReference=trim((string)($input['profile_id']??''));
$userReference=trim((string)($input['user_id']??''));
$targetReference=$profileReference!==''?$profileReference:$userReference;
$targetKind=$profileReference!==''?'profile_id':'user_id';
$action=trim((string)($input['action']??''));
if($targetReference===''||!in_array($action,['follow','unfollow','mute','unmute','block','unblock'],true)){
    mg_fail('Profile or user and valid relationship action are required.',422);
}

mg_rate_limit('social.relationship.write','user:'.$actorId,90,60);

try{
    $key=mg_engagement_key($input);
    $fingerprint=mg_engagement_fingerprint('relationship.'.$action,[$targetKind=>$targetReference]);
    $pdo=mg_db();
    $pdo->beginTransaction();
    $replay=mg_engagement_claim($pdo,$actorId,'relationship.'.$action,$key,$fingerprint);
    if($replay!==null){
        $pdo->commit();
        mg_ok($replay,'Existing relationship result returned.');
    }
    $followNotification=$action==='follow'&&$profileReference!==''?mg_follow_notification_context($pdo,$actorId,$profileReference):null;
    if($followNotification!==null)$followNotification['event_key']='social.follow.'.hash('sha256',$key);
    $result=$profileReference!==''
        ? mg_engagement_relationship($pdo,$actorId,$profileReference,$action)
        : mg_relationship_apply_to_user($pdo,$actorId,$userReference,$action);
    if($followNotification!==null)mg_follow_notification_send($pdo,$actorId,$followNotification);
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
