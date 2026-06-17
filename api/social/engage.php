<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';

mg_require_method('POST');
$user=mg_require_permission('social.engage');
$input=mg_input();
mg_require_csrf_for_write($input);

$actorId=(int)$user['id'];
$action=trim((string)($input['action']??''));
$allowed=['react','unreact','comment','comment_delete','comment_hide','comment_restore','save','unsave','share'];
if(!in_array($action,$allowed,true))mg_fail('A valid engagement action is required.',422);

mg_rate_limit('social.engagement.write','user:'.$actorId,$action==='comment'?40:120,60);

try{
    $key=mg_engagement_key($input);
    $postId=trim((string)($input['post_id']??''));
    $commentId=trim((string)($input['comment_id']??''));
    $fingerprintPayload=['post_id'=>$postId,'comment_id'=>$commentId];
    if($action==='react')$fingerprintPayload['reaction_type']=trim((string)($input['reaction_type']??'like'));
    if($action==='comment'){
        $fingerprintPayload['body']=preg_replace('/\s+/u',' ',trim((string)($input['body']??'')))??'';
        $fingerprintPayload['parent_comment_id']=trim((string)($input['parent_comment_id']??''));
    }
    if($action==='share'){
        $fingerprintPayload['channel']=trim((string)($input['channel']??'internal'));
        $fingerprintPayload['metadata']=$input['metadata']??[];
    }
    $fingerprint=mg_engagement_fingerprint('post.'.$action,$fingerprintPayload);

    $pdo=mg_db();
    $pdo->beginTransaction();
    $replay=mg_engagement_claim($pdo,$actorId,'post.'.$action,$key,$fingerprint);
    if($replay!==null){
        $pdo->commit();
        mg_ok($replay,'Existing engagement result returned.');
    }

    if($action==='react'){
        if($postId==='')throw new InvalidArgumentException('Post is required.');
        $result=mg_engagement_reaction($pdo,$actorId,$postId,trim((string)($input['reaction_type']??'like')));
    }elseif($action==='unreact'){
        if($postId==='')throw new InvalidArgumentException('Post is required.');
        $result=mg_engagement_reaction($pdo,$actorId,$postId,null);
    }elseif($action==='comment'){
        if($postId==='')throw new InvalidArgumentException('Post is required.');
        $result=mg_engagement_comment_create(
            $pdo,$actorId,$postId,(string)($input['body']??''),
            isset($input['parent_comment_id'])?(string)$input['parent_comment_id']:null
        );
        $post=mg_social_post_load($pdo,$postId,true);
        $result['engagement']=mg_engagement_post_state($pdo,$post,$actorId);
    }elseif(in_array($action,['comment_delete','comment_hide','comment_restore'],true)){
        if($commentId==='')throw new InvalidArgumentException('Comment is required.');
        $moderationAction=match($action){'comment_delete'=>'delete','comment_hide'=>'hide',default=>'restore'};
        $result=mg_engagement_comment_moderate(
            $pdo,$actorId,$commentId,$moderationAction,mg_api_user_has_permission($user,'social.moderate')
        );
        $post=mg_social_post_load($pdo,$result['post_id'],true);
        $result['engagement']=mg_engagement_post_state($pdo,$post,$actorId);
    }elseif($action==='save'||$action==='unsave'){
        if($postId==='')throw new InvalidArgumentException('Post is required.');
        $post=mg_engagement_post($pdo,$postId,$actorId,true);
        if($action==='save'){
            $stmt=$pdo->prepare('INSERT IGNORE INTO feed_post_saves (feed_post_id,user_id,created_at) VALUES (?,?,NOW())');
            $stmt->execute([(int)$post['id'],$actorId]);
            if($stmt->rowCount()>0)$pdo->prepare('UPDATE feed_posts SET save_count=save_count+1,updated_at=updated_at WHERE id=?')->execute([(int)$post['id']]);
        }else{
            $stmt=$pdo->prepare('DELETE FROM feed_post_saves WHERE feed_post_id=? AND user_id=?');
            $stmt->execute([(int)$post['id'],$actorId]);
            if($stmt->rowCount()>0)$pdo->prepare('UPDATE feed_posts SET save_count=GREATEST(save_count-1,0),updated_at=updated_at WHERE id=?')->execute([(int)$post['id']]);
        }
        $saved=$pdo->prepare('SELECT 1 FROM feed_post_saves WHERE feed_post_id=? AND user_id=? LIMIT 1');
        $saved->execute([(int)$post['id'],$actorId]);
        $result=['post_id'=>$postId,'saved'=>(bool)$saved->fetchColumn()];
    }else{
        if($postId==='')throw new InvalidArgumentException('Post is required.');
        $post=mg_engagement_post($pdo,$postId,$actorId,true);
        $channel=trim((string)($input['channel']??'internal'));
        if(!in_array($channel,['internal','copy_link','email','sms','external'],true))throw new InvalidArgumentException('Invalid share channel.');
        $metadata=is_array($input['metadata']??null)?$input['metadata']:[];
        $pdo->prepare('INSERT INTO feed_post_shares (public_id,feed_post_id,user_id,channel,metadata_json,created_at) VALUES (?,?,?,?,?,NOW())')
            ->execute([mg_public_uuid(),(int)$post['id'],$actorId,$channel,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
        $pdo->prepare('UPDATE feed_posts SET share_count=share_count+1,updated_at=updated_at WHERE id=?')->execute([(int)$post['id']]);
        $post=mg_social_post_load($pdo,$postId,true);
        $result=['post_id'=>$postId,'engagement'=>mg_engagement_post_state($pdo,$post,$actorId)];
    }

    $result=mg_engagement_complete($pdo,$actorId,$key,$result);
    $pdo->commit();
    mg_audit('social.post_'.$action,'feed_post',[
        'post_id'=>$result['post_id']??$postId,
        'comment_id'=>$result['comment_id']??($result['comment']['id']??null),
    ],$actorId);
    mg_event('social.post_'.$action,[
        'post_id'=>$result['post_id']??$postId,
        'comment_id'=>$result['comment_id']??($result['comment']['id']??null),
    ],$actorId);
    mg_ok($result,'Engagement updated.',$action==='comment'?201:200);
}catch(InvalidArgumentException $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','social.engagement_failed','Post engagement mutation failed.',['action'=>$action,'exception_class'=>$error::class],$actorId);
    mg_fail('Unable to complete engagement action.',500);
}
