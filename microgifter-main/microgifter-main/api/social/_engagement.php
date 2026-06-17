<?php
declare(strict_types=1);

require_once __DIR__ . '/_social.php';

const MG_ENGAGEMENT_COMMENT_MAX = 2000;
const MG_ENGAGEMENT_COMMENT_LIMIT = 40;

function mg_engagement_key(array $input): string
{
    $key=trim((string)($input['idempotency_key']??''));
    if($key===''||strlen($key)>190||preg_match('/^[A-Za-z0-9._:-]{8,190}$/',$key)!==1){
        throw new InvalidArgumentException('A valid idempotency key is required.');
    }
    return $key;
}

function mg_engagement_fingerprint(string $action,array $payload): string
{
    ksort($payload);
    return hash('sha256',json_encode(['action'=>$action,'payload'=>$payload],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}

function mg_engagement_claim(PDO $pdo,int $actorId,string $action,string $key,string $fingerprint): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM social_mutation_requests WHERE actor_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$actorId,$key]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if($row){
        if(!hash_equals((string)$row['request_fingerprint'],$fingerprint)||(string)$row['action']!==$action){
            throw new RuntimeException('Idempotency key is already bound to a different engagement request.');
        }
        if($row['response_json']===null)throw new RuntimeException('Engagement request is still processing.');
        $response=json_decode((string)$row['response_json'],true);
        if(!is_array($response))throw new RuntimeException('Stored engagement response is unavailable.');
        $response['duplicate']=true;
        return $response;
    }
    $pdo->prepare('INSERT INTO social_mutation_requests (public_id,actor_user_id,action,idempotency_key,request_fingerprint,response_json,created_at,completed_at) VALUES (?,?,?,?,?,NULL,NOW(),NULL)')
        ->execute([mg_public_uuid(),$actorId,$action,$key,$fingerprint]);
    return null;
}

function mg_engagement_complete(PDO $pdo,int $actorId,string $key,array $response): array
{
    $response['duplicate']=false;
    $pdo->prepare('UPDATE social_mutation_requests SET response_json=?,completed_at=NOW() WHERE actor_user_id=? AND idempotency_key=?')
        ->execute([json_encode($response,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$actorId,$key]);
    return $response;
}

function mg_engagement_profile_target(PDO $pdo,string $reference,bool $forUpdate=false): array
{
    $reference=trim($reference);
    if($reference===''||strlen($reference)>190)throw new InvalidArgumentException('Profile is required.');
    $stmt=$pdo->prepare(
        "SELECT pp.id profile_id,pp.public_id profile_public_id,pp.slug,pp.user_id
         FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id
         WHERE (pp.public_id=? OR pp.slug=?)
           AND pp.status='active' AND pp.visibility IN ('public','unlisted') AND u.status='active'
         LIMIT 1".($forUpdate?' FOR UPDATE':'')
    );
    $stmt->execute([$reference,strtolower($reference)]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Profile is not available.');
    return $row;
}

function mg_engagement_relationship_state(PDO $pdo,int $actorId,int $targetId): array
{
    $stmt=$pdo->prepare(
        "SELECT
          EXISTS(SELECT 1 FROM social_follows WHERE follower_user_id=? AND followed_user_id=? AND status='active') following,
          EXISTS(SELECT 1 FROM social_mutes WHERE muting_user_id=? AND muted_user_id=?) muted,
          EXISTS(SELECT 1 FROM social_blocks WHERE blocking_user_id=? AND blocked_user_id=?) blocking,
          (SELECT COUNT(*) FROM social_follows WHERE followed_user_id=? AND status='active') followers"
    );
    $stmt->execute([$actorId,$targetId,$actorId,$targetId,$actorId,$targetId,$targetId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    return [
        'following'=>(bool)($row['following']??false),
        'muted'=>(bool)($row['muted']??false),
        'blocking'=>(bool)($row['blocking']??false),
        'followers'=>(int)($row['followers']??0),
    ];
}

function mg_engagement_relationship(PDO $pdo,int $actorId,string $targetReference,string $action): array
{
    if(!in_array($action,['follow','unfollow','mute','unmute','block','unblock'],true))throw new InvalidArgumentException('Invalid relationship action.');
    $target=mg_engagement_profile_target($pdo,$targetReference,true);
    $targetId=(int)$target['user_id'];
    if($targetId===$actorId)throw new InvalidArgumentException('You cannot change a relationship with your own profile.');

    if($action==='follow'){
        if(mg_social_is_blocked($pdo,$actorId,$targetId))throw new RuntimeException('Profile is not available.');
        $existing=$pdo->prepare("SELECT status FROM social_follows WHERE follower_user_id=? AND followed_user_id=? LIMIT 1 FOR UPDATE");
        $existing->execute([$actorId,$targetId]);
        $wasFollowing=(string)($existing->fetchColumn()?:'')==='active';
        $pdo->prepare("INSERT INTO social_follows (follower_user_id,followed_user_id,status,created_at,updated_at) VALUES (?,?,'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE status='active',updated_at=NOW()")
            ->execute([$actorId,$targetId]);
        if(!$wasFollowing)mg_social_notify($pdo,$targetId,$actorId,'new_follower','New follower','Someone followed your profile.','');
    }elseif($action==='unfollow'){
        $pdo->prepare('DELETE FROM social_follows WHERE follower_user_id=? AND followed_user_id=?')->execute([$actorId,$targetId]);
    }elseif($action==='mute'){
        if(mg_social_is_blocked($pdo,$actorId,$targetId))throw new RuntimeException('Profile is not available.');
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

    return [
        'action'=>$action,
        'profile_id'=>(string)$target['profile_public_id'],
        'profile_slug'=>(string)$target['slug'],
        'relationship'=>mg_engagement_relationship_state($pdo,$actorId,$targetId),
    ];
}

function mg_engagement_post(PDO $pdo,string $postPublicId,?int $viewerId,bool $forUpdate=false): array
{
    $postPublicId=trim($postPublicId);
    if($postPublicId===''||preg_match('/^[a-f0-9-]{36}$/i',$postPublicId)!==1)throw new InvalidArgumentException('Post is required.');
    $post=mg_social_post_load($pdo,$postPublicId,$forUpdate);
    if(!mg_social_can_view($pdo,$post,$viewerId))throw new RuntimeException('Post is not available.');
    return $post;
}

function mg_engagement_post_state(PDO $pdo,array $post,?int $viewerId): array
{
    $summary=$pdo->prepare('SELECT reaction_type,COUNT(*) total FROM feed_post_reactions WHERE feed_post_id=? GROUP BY reaction_type ORDER BY reaction_type');
    $summary->execute([(int)$post['id']]);
    $reactions=['like'=>0,'love'=>0,'celebrate'=>0,'support'=>0];
    foreach($summary->fetchAll(PDO::FETCH_ASSOC) as $row)$reactions[(string)$row['reaction_type']]=(int)$row['total'];
    $viewerReaction=null;
    if($viewerId!==null){
        $stmt=$pdo->prepare('SELECT reaction_type FROM feed_post_reactions WHERE feed_post_id=? AND user_id=? LIMIT 1');
        $stmt->execute([(int)$post['id'],$viewerId]);
        $viewerReaction=$stmt->fetchColumn()?:null;
    }
    return [
        'comments'=>(int)$post['comment_count'],
        'reactions'=>(int)$post['reaction_count'],
        'shares'=>(int)$post['share_count'],
        'reaction_types'=>$reactions,
        'viewer_reaction'=>$viewerReaction,
    ];
}

function mg_engagement_reaction(PDO $pdo,int $actorId,string $postPublicId,?string $reactionType): array
{
    $post=mg_engagement_post($pdo,$postPublicId,$actorId,true);
    if($reactionType===null||$reactionType===''){
        $stmt=$pdo->prepare('DELETE FROM feed_post_reactions WHERE feed_post_id=? AND user_id=?');
        $stmt->execute([(int)$post['id'],$actorId]);
        if($stmt->rowCount()>0)$pdo->prepare('UPDATE feed_posts SET reaction_count=GREATEST(reaction_count-1,0),updated_at=updated_at WHERE id=?')->execute([(int)$post['id']]);
    }else{
        if(!in_array($reactionType,['like','love','celebrate','support'],true))throw new InvalidArgumentException('Invalid reaction.');
        $existing=$pdo->prepare('SELECT reaction_type FROM feed_post_reactions WHERE feed_post_id=? AND user_id=? LIMIT 1 FOR UPDATE');
        $existing->execute([(int)$post['id'],$actorId]);
        $had=(bool)$existing->fetchColumn();
        $pdo->prepare('INSERT INTO feed_post_reactions (feed_post_id,user_id,reaction_type,created_at,updated_at) VALUES (?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE reaction_type=VALUES(reaction_type),updated_at=NOW()')
            ->execute([(int)$post['id'],$actorId,$reactionType]);
        if(!$had)$pdo->prepare('UPDATE feed_posts SET reaction_count=reaction_count+1,updated_at=updated_at WHERE id=?')->execute([(int)$post['id']]);
        if(!$had)mg_social_notify($pdo,(int)$post['created_by_user_id'],$actorId,'post_reaction','New reaction','Someone reacted to your post.',$postPublicId);
    }
    $post=mg_social_post_load($pdo,$postPublicId,true);
    return ['post_id'=>$postPublicId,'engagement'=>mg_engagement_post_state($pdo,$post,$actorId)];
}

function mg_engagement_comment_row(PDO $pdo,int $commentId,int $viewerId): array
{
    $stmt=$pdo->prepare(
        "SELECT c.public_id,c.body,c.status,c.created_at,c.updated_at,c.user_id,c.feed_post_id,
                u.display_name,u.full_name,pp.slug profile_slug,pp.public_id profile_public_id,
                fp.created_by_user_id post_owner_id
         FROM feed_post_comments c
         INNER JOIN users u ON u.id=c.user_id
         INNER JOIN feed_posts fp ON fp.id=c.feed_post_id
         LEFT JOIN public_profiles pp ON pp.user_id=u.id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
         WHERE c.id=? LIMIT 1"
    );
    $stmt->execute([$commentId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Comment not found.');
    return [
        'id'=>(string)$row['public_id'],
        'body'=>(string)$row['body'],
        'status'=>(string)$row['status'],
        'created_at'=>(string)$row['created_at'],
        'updated_at'=>(string)$row['updated_at'],
        'author'=>[
            'display_name'=>(string)($row['display_name']?:$row['full_name']),
            'profile_id'=>$row['profile_public_id']!==null?(string)$row['profile_public_id']:null,
            'profile_slug'=>$row['profile_slug']!==null?(string)$row['profile_slug']:null,
        ],
        'permissions'=>[
            'can_delete'=>$viewerId===(int)$row['user_id']||$viewerId===(int)$row['post_owner_id'],
            'can_hide'=>$viewerId===(int)$row['post_owner_id'],
        ],
    ];
}

function mg_engagement_comment_create(PDO $pdo,int $actorId,string $postPublicId,string $body,?string $parentPublicId=null): array
{
    $post=mg_engagement_post($pdo,$postPublicId,$actorId,true);
    $body=preg_replace('/\s+/u',' ',trim($body))??'';
    if($body===''||mb_strlen($body)>MG_ENGAGEMENT_COMMENT_MAX)throw new InvalidArgumentException('Comment must contain 1 to '.MG_ENGAGEMENT_COMMENT_MAX.' characters.');
    $parentId=null;
    if(trim((string)$parentPublicId)!==''){
        $stmt=$pdo->prepare("SELECT id,parent_comment_id FROM feed_post_comments WHERE public_id=? AND feed_post_id=? AND status IN ('visible','flagged') LIMIT 1 FOR UPDATE");
        $stmt->execute([trim((string)$parentPublicId),(int)$post['id']]);
        $parent=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$parent)throw new RuntimeException('Parent comment is not available.');
        if($parent['parent_comment_id']!==null)throw new InvalidArgumentException('Replies may only be one level deep.');
        $parentId=(int)$parent['id'];
    }
    $publicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO feed_post_comments (public_id,feed_post_id,user_id,parent_comment_id,body,status,created_at,updated_at) VALUES (?,?,?,?,?,'visible',NOW(),NOW())")
        ->execute([$publicId,(int)$post['id'],$actorId,$parentId,$body]);
    $commentId=(int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE feed_posts SET comment_count=comment_count+1,updated_at=updated_at WHERE id=?')->execute([(int)$post['id']]);
    mg_social_notify($pdo,(int)$post['created_by_user_id'],$actorId,'post_comment','New comment','Someone commented on your post.',$postPublicId);
    return ['post_id'=>$postPublicId,'comment'=>mg_engagement_comment_row($pdo,$commentId,$actorId)];
}

function mg_engagement_comment_moderate(PDO $pdo,int $actorId,string $commentPublicId,string $action,bool $canModerateAll=false): array
{
    if(!in_array($action,['delete','hide','restore'],true))throw new InvalidArgumentException('Invalid comment action.');
    $stmt=$pdo->prepare(
        "SELECT c.*,fp.public_id post_public_id,fp.created_by_user_id post_owner_id
         FROM feed_post_comments c INNER JOIN feed_posts fp ON fp.id=c.feed_post_id
         WHERE c.public_id=? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([trim($commentPublicId)]);
    $comment=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$comment)throw new RuntimeException('Comment is not available.');
    $isAuthor=$actorId===(int)$comment['user_id'];
    $isPostOwner=$actorId===(int)$comment['post_owner_id'];
    if($action==='delete'&&!$isAuthor&&!$isPostOwner&&!$canModerateAll)throw new RuntimeException('Comment is not available.');
    if(in_array($action,['hide','restore'],true)&&!$isPostOwner&&!$canModerateAll)throw new RuntimeException('Comment is not available.');
    $wasCounted=in_array((string)$comment['status'],['visible','flagged'],true);
    $status=$action==='restore'?'visible':($action==='hide'?'hidden':'removed');
    $pdo->prepare('UPDATE feed_post_comments SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,(int)$comment['id']]);
    $isCounted=in_array($status,['visible','flagged'],true);
    if($wasCounted&&!$isCounted)$pdo->prepare('UPDATE feed_posts SET comment_count=GREATEST(comment_count-1,0),updated_at=updated_at WHERE id=?')->execute([(int)$comment['feed_post_id']]);
    elseif(!$wasCounted&&$isCounted)$pdo->prepare('UPDATE feed_posts SET comment_count=comment_count+1,updated_at=updated_at WHERE id=?')->execute([(int)$comment['feed_post_id']]);
    return ['post_id'=>(string)$comment['post_public_id'],'comment_id'=>(string)$comment['public_id'],'status'=>$status];
}

function mg_engagement_comments(PDO $pdo,array $post,?int $viewerId,?string $cursor,int $limit=20): array
{
    $limit=max(1,min($limit,MG_ENGAGEMENT_COMMENT_LIMIT));
    $params=[(int)$post['id']];
    $cursorSql='';
    if(trim((string)$cursor)!==''){
        $encoded=(string)$cursor;
        $padding=(4-(strlen($encoded)%4))%4;
        $decoded=json_decode(base64_decode(strtr($encoded.str_repeat('=',$padding),'-_','+/'),true)?:'',true);
        if(!is_array($decoded)||!isset($decoded['time'],$decoded['id'])||strtotime((string)$decoded['time'])===false||preg_match('/^[a-f0-9-]{36}$/',(string)$decoded['id'])!==1)throw new InvalidArgumentException('Invalid pagination cursor.');
        $cursorSql=' AND (c.created_at>? OR (c.created_at=? AND c.public_id>?))';
        array_push($params,(string)$decoded['time'],(string)$decoded['time'],(string)$decoded['id']);
    }
    $stmt=$pdo->prepare(
        "SELECT c.id FROM feed_post_comments c
         WHERE c.feed_post_id=? AND c.status IN ('visible','flagged'){$cursorSql}
         ORDER BY c.created_at ASC,c.public_id ASC LIMIT ".($limit+1)
    );
    $stmt->execute($params);
    $ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    $hasMore=count($ids)>$limit;
    if($hasMore)array_pop($ids);
    $items=[];
    foreach($ids as $id)$items[]=mg_engagement_comment_row($pdo,$id,(int)($viewerId??0));
    $next=null;
    if($hasMore&&$items!==[]){
        $last=$items[array_key_last($items)];
        $next=rtrim(strtr(base64_encode(json_encode(['time'=>$last['created_at'],'id'=>$last['id']],JSON_THROW_ON_ERROR)),'+/','-_'),'=');
    }
    return ['items'=>$items,'next_cursor'=>$next,'has_more'=>$hasMore,'limit'=>$limit];
}
