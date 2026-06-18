<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_social_post_load(PDO $pdo,string $publicId,bool $forUpdate=false): array
{
    $stmt=$pdo->prepare('SELECT fp.* FROM feed_posts fp WHERE fp.public_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([$publicId]);
    $post=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$post)throw new RuntimeException('Post not found.');
    return $post;
}

function mg_social_is_blocked(PDO $pdo,int $viewerId,int $authorId): bool
{
    $stmt=$pdo->prepare('SELECT 1 FROM social_blocks WHERE (blocking_user_id=? AND blocked_user_id=?) OR (blocking_user_id=? AND blocked_user_id=?) LIMIT 1');
    $stmt->execute([$viewerId,$authorId,$authorId,$viewerId]);
    return (bool)$stmt->fetchColumn();
}

function mg_social_is_following(PDO $pdo,int $viewerId,int $authorId): bool
{
    $stmt=$pdo->prepare("SELECT 1 FROM social_follows WHERE follower_user_id=? AND followed_user_id=? AND status='active' LIMIT 1");
    $stmt->execute([$viewerId,$authorId]);
    return (bool)$stmt->fetchColumn();
}

function mg_social_has_active_subscription(PDO $pdo,int $viewerId,int $authorId,?int $planId=null): bool
{
    $sql="SELECT 1 FROM subscriptions WHERE subscriber_user_id=? AND recipient_user_id=? AND recovery_status='clear' AND status IN ('trialing','active','cancel_pending')";
    $params=[$viewerId,$authorId];
    if($planId!==null){$sql.=' AND plan_id=?';$params[]=$planId;}
    $sql.=' AND current_period_end>NOW() LIMIT 1';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

/**
 * Preload the canonical Stage 14 relationship and Stage 13 eligibility state for one author.
 * The returned context is safe to reuse across a bounded post collection and prevents N+1 checks.
 */
function mg_social_view_context(PDO $pdo,?int $viewerId,int $authorId): array
{
    $context=[
        'viewer_id'=>$viewerId,
        'author_id'=>$authorId,
        'is_owner'=>$viewerId!==null&&$viewerId===$authorId,
        'blocked'=>false,
        'following'=>false,
        'active_subscription_plan_ids'=>[],
        'has_active_subscription'=>false,
    ];
    if($viewerId===null||$viewerId===$authorId)return $context;

    $relationship=$pdo->prepare(
        "SELECT
           EXISTS(SELECT 1 FROM social_blocks WHERE (blocking_user_id=? AND blocked_user_id=?) OR (blocking_user_id=? AND blocked_user_id=?)) blocked,
           EXISTS(SELECT 1 FROM social_follows WHERE follower_user_id=? AND followed_user_id=? AND status='active') following"
    );
    $relationship->execute([$viewerId,$authorId,$authorId,$viewerId,$viewerId,$authorId]);
    $row=$relationship->fetch(PDO::FETCH_ASSOC)?:[];
    $context['blocked']=!empty($row['blocked']);
    $context['following']=!empty($row['following']);
    if($context['blocked'])return $context;

    $plans=$pdo->prepare("SELECT DISTINCT plan_id FROM subscriptions WHERE subscriber_user_id=? AND recipient_user_id=? AND recovery_status='clear' AND status IN ('trialing','active','cancel_pending') AND current_period_end>NOW()");
    $plans->execute([$viewerId,$authorId]);
    foreach($plans->fetchAll(PDO::FETCH_COLUMN) as $planId){
        $planId=(int)$planId;
        if($planId>0)$context['active_subscription_plan_ids'][$planId]=true;
    }
    $context['has_active_subscription']=$context['active_subscription_plan_ids']!==[];
    return $context;
}

function mg_social_can_view(PDO $pdo,array $post,?int $viewerId,?array $context=null): bool
{
    if((string)$post['status']!=='published'||in_array((string)$post['moderation_status'],['hidden','removed'],true))return false;
    $authorId=(int)$post['created_by_user_id'];
    if($viewerId!==null&&$viewerId===$authorId)return true;

    $usableContext=is_array($context)
        &&($context['viewer_id']??null)===$viewerId
        &&(int)($context['author_id']??0)===$authorId;
    $blocked=$usableContext?(bool)($context['blocked']??false):($viewerId!==null&&mg_social_is_blocked($pdo,$viewerId,$authorId));
    if($blocked)return false;

    return match((string)$post['visibility']){
        'public','unlisted'=>true,
        'followers'=>$viewerId!==null&&($usableContext?(bool)($context['following']??false):mg_social_is_following($pdo,$viewerId,$authorId)),
        'subscribers','premium'=>$viewerId!==null&&(
            $usableContext
                ?($post['subscription_plan_id']!==null
                    ?isset($context['active_subscription_plan_ids'][(int)$post['subscription_plan_id']])
                    :(bool)($context['has_active_subscription']??false))
                :mg_social_has_active_subscription($pdo,$viewerId,$authorId,$post['subscription_plan_id']!==null?(int)$post['subscription_plan_id']:null)
        ),
        default=>false,
    };
}

function mg_social_create_post(PDO $pdo,int $userId,array $input): array
{
    $headline=mb_substr(trim((string)($input['headline']??'')),0,240);
    $body=trim((string)($input['body']??''));
    $visibility=trim((string)($input['visibility']??'public'));
    if($headline===''&&$body==='')throw new InvalidArgumentException('Post content is required.');
    if(!in_array($visibility,['private','unlisted','public','followers','subscribers','premium'],true))throw new InvalidArgumentException('Invalid post visibility.');
    $productId=null;$microgiftId=null;$planId=null;
    if(!empty($input['product_id'])){$stmt=$pdo->prepare('SELECT id,merchant_user_id FROM catalog_products WHERE public_id=? LIMIT 1');$stmt->execute([(string)$input['product_id']]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row||(int)$row['merchant_user_id']!==$userId)throw new RuntimeException('Product is not available to this author.');$productId=(int)$row['id'];}
    if(!empty($input['microgift_id'])){$stmt=$pdo->prepare('SELECT id,owner_user_id,issuer_user_id FROM microgift_instances WHERE public_id=? LIMIT 1');$stmt->execute([(string)$input['microgift_id']]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row||!in_array($userId,[(int)$row['owner_user_id'],(int)$row['issuer_user_id']],true))throw new RuntimeException('Microgift is not available to this author.');$microgiftId=(int)$row['id'];}
    if(in_array($visibility,['subscribers','premium'],true)){
        $planPublic=trim((string)($input['subscription_plan_id']??''));
        if($planPublic==='')throw new InvalidArgumentException('Subscriber content requires a subscription plan.');
        $stmt=$pdo->prepare("SELECT id FROM subscription_plans WHERE public_id=? AND owner_user_id=? AND status='active' LIMIT 1");$stmt->execute([$planPublic,$userId]);$planId=(int)($stmt->fetchColumn()?:0);if($planId<1)throw new RuntimeException('Subscription plan is not available.');
    }
    $publicId=mg_public_uuid();
    $status=(bool)($input['publish']??true)?'published':'draft';
    $pdo->prepare('INSERT INTO feed_posts (public_id,merchant_user_id,catalog_product_id,linked_microgift_instance_id,subscription_plan_id,current_version_id,post_type,headline,body,media_json,visibility,status,moderation_status,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,NULL,?,?,?,?,?,?,\'clear\',?,NOW(),NOW())')
        ->execute([$publicId,$userId,$productId,$microgiftId,$planId,trim((string)($input['post_type']??'simple')),$headline,$body,json_encode($input['media']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$visibility,$status,$userId]);
    $post=mg_social_post_load($pdo,$publicId);
    mg_event('social.post_created',['post_id'=>$publicId,'visibility'=>$visibility,'status'=>$status],$userId);
    return $post;
}

function mg_social_feed(PDO $pdo,?int $viewerId,int $afterId=0,int $limit=30): array
{
    $limit=max(1,min($limit,100));
    $sql='SELECT fp.*,u.public_id author_public_id,u.display_name author_name FROM feed_posts fp INNER JOIN users u ON u.id=fp.created_by_user_id WHERE fp.status=\'published\'';
    $params=[];
    if($afterId>0){$sql.=' AND fp.id<?';$params[]=$afterId;}
    $sql.=' ORDER BY fp.id DESC LIMIT '.($limit*4);
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);$items=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $post){
        if(count($items)>=$limit)break;
        if($viewerId!==null){$mute=$pdo->prepare('SELECT 1 FROM social_mutes WHERE muting_user_id=? AND muted_user_id=? LIMIT 1');$mute->execute([$viewerId,(int)$post['created_by_user_id']]);if($mute->fetchColumn())continue;}
        if(mg_social_can_view($pdo,$post,$viewerId))$items[]=$post;
    }
    return $items;
}

function mg_social_notify(PDO $pdo,int $recipientId,int $actorId,string $type,string $title,string $message,string $postId): void
{
    if($recipientId===$actorId)return;
    mg_create_operational_alert($pdo,$recipientId,$type,'info',$title,$message,'/feed.php?post='.$postId,['post_id'=>$postId,'actor_user_id'=>$actorId]);
}
