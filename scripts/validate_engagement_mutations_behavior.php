<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/social/_engagement.php';
require_once dirname(__DIR__).'/api/tips/_engagement.php';
require_once dirname(__DIR__).'/tests/integration/TipBehaviorFixture.php';

function em_assert(bool $condition,string $name): void
{
    if(!$condition)throw new RuntimeException('Engagement validation failed: '.$name);
}

function em_profile(PDO $pdo,int $userId,string $slug,string $type='creator'): array
{
    $now=gmdate('Y-m-d H:i:s');
    $publicId=mg_public_uuid();
    $id=mg_it_insert($pdo,'public_profiles',[
        'public_id'=>$publicId,'user_id'=>$userId,'slug'=>$slug,'display_name'=>'Engagement '.$slug,
        'headline'=>'Engagement profile','bio'=>'Behavior profile','avatar_url'=>null,'cover_url'=>null,
        'location_label'=>'Phoenix, AZ','website_url'=>null,'profile_type'=>$type,'visibility'=>'public',
        'status'=>'active','completion_score'=>100,'metadata_json'=>null,'published_at'=>$now,
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$publicId,'user_id'=>$userId,'slug'=>$slug];
}

function em_post(PDO $pdo,int $authorId,string $runId,string $moderation='clear'): array
{
    $now=gmdate('Y-m-d H:i:s');
    $publicId=mg_public_uuid();
    $id=mg_it_insert($pdo,'feed_posts',[
        'public_id'=>$publicId,'merchant_user_id'=>$authorId,'catalog_product_id'=>null,
        'linked_microgift_instance_id'=>null,'subscription_plan_id'=>null,'current_version_id'=>null,
        'post_type'=>'simple','headline'=>'Engagement post '.$runId,'body'=>'Behavior post body',
        'media_json'=>json_encode([],JSON_THROW_ON_ERROR),'visibility'=>'public','status'=>'published',
        'moderation_status'=>$moderation,'comment_count'=>0,'reaction_count'=>0,'share_count'=>0,'save_count'=>0,
        'promoted_at'=>null,'archived_at'=>null,'created_by_user_id'=>$authorId,'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$publicId,'author_id'=>$authorId];
}

function em_expect(callable $callback,string $contains): bool
{
    try{$callback();}catch(Throwable $error){return $contains===''||str_contains($error->getMessage(),$contains);}
    return false;
}

function em_keys(mixed $value,array &$keys): void
{
    if(!is_array($value))return;
    foreach($value as $key=>$child){$keys[]=strtolower((string)$key);em_keys($child,$keys);}
}

$pdo=mg_db();
$runId='em'.bin2hex(random_bytes(5));
$result=array_fill_keys([
    'follow_idempotency','follow_count','block_cleanup','block_enforcement','reaction_create_change_remove',
    'reaction_idempotency_conflict','comment_create_replay','comment_permissions','comment_count',
    'hidden_post_exclusion','safe_projection','public_profile_tip_target','card_tip_pending',
    'card_tip_confirmation','card_tip_confirmation_replay','single_tip_ledger','rollback_clean',
],false);

$pdo->beginTransaction();
try{
    $viewer=mg_it_user($pdo,$runId.'-viewer@example.test','Engagement Viewer');
    $author=mg_it_user($pdo,$runId.'-author@example.test','Engagement Author');
    $other=mg_it_user($pdo,$runId.'-other@example.test','Engagement Other');
    $viewerProfile=em_profile($pdo,$viewer,'viewer-'.$runId,'customer');
    $authorProfile=em_profile($pdo,$author,'author-'.$runId,'creator');
    $otherProfile=em_profile($pdo,$other,'other-'.$runId,'customer');
    $post=em_post($pdo,$author,$runId);
    $hiddenPost=em_post($pdo,$author,$runId.'-hidden','hidden');

    $followKey='follow:'.$runId;
    $followFingerprint=mg_engagement_fingerprint('relationship.follow',['profile_id'=>$authorProfile['public_id']]);
    em_assert(mg_engagement_claim($pdo,$viewer,'relationship.follow',$followKey,$followFingerprint)===null,'follow claim');
    $follow=mg_engagement_relationship($pdo,$viewer,$authorProfile['public_id'],'follow');
    mg_engagement_complete($pdo,$viewer,$followKey,$follow);
    $replay=mg_engagement_claim($pdo,$viewer,'relationship.follow',$followKey,$followFingerprint);
    $result['follow_idempotency']=is_array($replay)&&!empty($replay['duplicate'])&&!empty($replay['relationship']['following']);
    $result['follow_count']=(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM social_follows WHERE follower_user_id=? AND followed_user_id=? AND status='active'",[$viewer,$author])===1;

    mg_engagement_relationship($pdo,$author,$viewerProfile['public_id'],'follow');
    $blocked=mg_engagement_relationship($pdo,$viewer,$authorProfile['public_id'],'block');
    $result['block_cleanup']=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM social_follows WHERE (follower_user_id=? AND followed_user_id=?) OR (follower_user_id=? AND followed_user_id=?)',[$viewer,$author,$author,$viewer])===0
        &&!empty($blocked['relationship']['blocking']);
    $result['block_enforcement']=em_expect(fn()=>mg_engagement_relationship($pdo,$viewer,$authorProfile['public_id'],'follow'),'Profile is not available.');
    mg_engagement_relationship($pdo,$viewer,$authorProfile['public_id'],'unblock');

    $reactionKey='reaction:'.$runId;
    $reactionFingerprint=mg_engagement_fingerprint('post.react',['post_id'=>$post['public_id'],'comment_id'=>'','reaction_type'=>'like']);
    em_assert(mg_engagement_claim($pdo,$viewer,'post.react',$reactionKey,$reactionFingerprint)===null,'reaction claim');
    $reaction=mg_engagement_reaction($pdo,$viewer,$post['public_id'],'like');
    mg_engagement_complete($pdo,$viewer,$reactionKey,$reaction);
    $changed=mg_engagement_reaction($pdo,$viewer,$post['public_id'],'love');
    $removed=mg_engagement_reaction($pdo,$viewer,$post['public_id'],null);
    $result['reaction_create_change_remove']=($reaction['engagement']['reactions']??0)===1
        &&($changed['engagement']['viewer_reaction']??null)==='love'
        &&($changed['engagement']['reactions']??0)===1
        &&($removed['engagement']['reactions']??-1)===0;
    $result['reaction_idempotency_conflict']=em_expect(
        fn()=>mg_engagement_claim($pdo,$viewer,'post.react',$reactionKey,mg_engagement_fingerprint('post.react',['post_id'=>$post['public_id'],'comment_id'=>'','reaction_type'=>'support'])),
        'different engagement request'
    );

    $commentKey='comment:'.$runId;
    $commentBody='A safe behavior comment';
    $commentFingerprint=mg_engagement_fingerprint('post.comment',['post_id'=>$post['public_id'],'comment_id'=>'','body'=>$commentBody,'parent_comment_id'=>'']);
    em_assert(mg_engagement_claim($pdo,$viewer,'post.comment',$commentKey,$commentFingerprint)===null,'comment claim');
    $comment=mg_engagement_comment_create($pdo,$viewer,$post['public_id'],$commentBody);
    mg_engagement_complete($pdo,$viewer,$commentKey,$comment);
    $commentReplay=mg_engagement_claim($pdo,$viewer,'post.comment',$commentKey,$commentFingerprint);
    $result['comment_create_replay']=is_array($commentReplay)&&!empty($commentReplay['duplicate'])
        &&(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM feed_post_comments WHERE feed_post_id=?',[$post['id']])===1;
    $result['comment_permissions']=!empty($comment['comment']['permissions']['can_delete'])
        &&empty($comment['comment']['permissions']['can_hide']);
    $hidden=mg_engagement_comment_moderate($pdo,$author,(string)$comment['comment']['id'],'hide',false);
    $restored=mg_engagement_comment_moderate($pdo,$author,(string)$comment['comment']['id'],'restore',false);
    $deleted=mg_engagement_comment_moderate($pdo,$viewer,(string)$comment['comment']['id'],'delete',false);
    $result['comment_count']=$hidden['status']==='hidden'&&$restored['status']==='visible'&&$deleted['status']==='removed'
        &&(int)mg_it_scalar($pdo,'SELECT comment_count FROM feed_posts WHERE id=?',[$post['id']])===0;

    $result['hidden_post_exclusion']=em_expect(fn()=>mg_engagement_post($pdo,$hiddenPost['public_id'],$viewer,false),'Post is not available.');
    $safe=mg_engagement_comments($pdo,mg_social_post_load($pdo,$post['public_id']),$viewer,null,20);
    $keys=[];em_keys($safe,$keys);
    $result['safe_projection']=array_intersect(['user_id','feed_post_id','post_owner_id','email','metadata_json'],$keys)===[];

    $tipInput=mg_tip_engagement_input($pdo,[
        'target_type'=>'profile','target_reference'=>$authorProfile['public_id'],'amount_cents'=>1200,
        'currency'=>'USD','funding_type'=>'stripe','idempotency_key'=>'tip:'.$runId,
        'metadata'=>['source'=>'engagement_behavior'],
    ]);
    $result['public_profile_tip_target']=$tipInput['target_reference']===(string)$author
        &&($tipInput['metadata']['public_profile_id']??null)===$authorProfile['public_id'];
    $tip=mg_tip_create($pdo,$viewer,$tipInput);
    $result['card_tip_pending']=in_array((string)$tip['status'],['pending','requires_action','processing'],true)
        &&!empty($tip['client_secret'])&&!empty($tip['payment_intent_public_id']);
    $pdo->prepare("UPDATE payment_intents SET status='succeeded',captured_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$tip['payment_intent_id']]);
    $confirmed=mg_tip_confirm_card($pdo,$viewer,(string)$tip['public_id'],'confirm:'.$runId);
    $confirmedAgain=mg_tip_confirm_card($pdo,$viewer,(string)$tip['public_id'],'confirm:'.$runId);
    $result['card_tip_confirmation']=$confirmed['status']==='posted'&&!empty($confirmed['posted'])&&empty($confirmed['duplicate']);
    $result['card_tip_confirmation_replay']=$confirmedAgain['status']==='posted'&&!empty($confirmedAgain['duplicate']);
    $result['single_tip_ledger']=(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE transaction_type='tip' AND source_reference=?",[$tip['public_id']])===1;

    foreach($result as $name=>$passed)if($name!=='rollback_clean')em_assert($passed,$name);
    $pdo->rollBack();
    $result['rollback_clean']=true;
    echo json_encode($result+['suite'=>'engagement_mutations_foundation'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,$error->getMessage().PHP_EOL);
    exit(1);
}
