<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function mg_review_evidence(PDO $pdo, array $report): array
{
    $type=(string)$report['subject_type'];
    if(in_array($type,['profile','user'],true)&&!empty($report['subject_user_id'])){
        $stmt=$pdo->prepare(
            'SELECT u.id,u.public_id,u.display_name,u.full_name,u.email,u.status,u.created_at,u.updated_at,
                    pp.public_id profile_id,pp.slug,pp.display_name profile_name,pp.headline,pp.bio biography,
                    pp.avatar_url,pp.cover_url,pp.visibility,pp.status profile_status,pp.updated_at profile_updated_at
             FROM users u LEFT JOIN public_profiles pp ON pp.user_id=u.id WHERE u.id=? LIMIT 1'
        );
        $stmt->execute([(int)$report['subject_user_id']]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if($row)return ['type'=>$type,'available'=>true,'profile'=>$row];
    }
    if($type==='post'&&!empty($report['feed_post_id'])){
        $stmt=$pdo->prepare(
            'SELECT fp.id,fp.public_id,fp.created_by_user_id,fp.post_type,fp.title,fp.body,fp.media_json,
                    fp.visibility,fp.status,fp.moderation_status,fp.created_at,fp.updated_at,
                    COALESCE(NULLIF(u.display_name,\'\'),u.full_name,u.email) author_name
             FROM feed_posts fp INNER JOIN users u ON u.id=fp.created_by_user_id WHERE fp.id=? LIMIT 1'
        );
        $stmt->execute([(int)$report['feed_post_id']]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if($row){$row['media']=mg_content_review_json($row['media_json']??null);unset($row['media_json']);return ['type'=>'post','available'=>true,'post'=>$row];}
    }
    if($type==='comment'&&!empty($report['comment_id'])){
        $stmt=$pdo->prepare(
            'SELECT c.id,c.public_id,c.user_id,c.body,c.status,c.created_at,c.updated_at,
                    fp.public_id post_id,COALESCE(NULLIF(u.display_name,\'\'),u.full_name,u.email) author_name
             FROM feed_post_comments c INNER JOIN feed_posts fp ON fp.id=c.feed_post_id
             INNER JOIN users u ON u.id=c.user_id WHERE c.id=? LIMIT 1'
        );
        $stmt->execute([(int)$report['comment_id']]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if($row)return ['type'=>'comment','available'=>true,'comment'=>$row];
    }
    if($type==='media'&&!empty($report['asset_id'])){
        $stmt=$pdo->prepare(
            'SELECT a.id,a.public_id,a.owner_user_id,a.asset_type,a.original_filename,a.mime_type,a.byte_size,
                    a.width_px,a.height_px,a.duration_ms,a.status,a.moderation_status,a.created_at,a.updated_at,
                    fp.public_id post_id,COALESCE(NULLIF(u.display_name,\'\'),u.full_name,u.email) owner_name
             FROM catalog_assets a LEFT JOIN feed_post_assets fpa ON fpa.asset_id=a.id
             LEFT JOIN feed_posts fp ON fp.id=fpa.feed_post_id INNER JOIN users u ON u.id=a.owner_user_id
             WHERE a.id=? ORDER BY fp.updated_at DESC LIMIT 1'
        );
        $stmt->execute([(int)$report['asset_id']]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if($row){$row['preview_url']=mg_storage_asset_public_url((string)$row['public_id']);return ['type'=>'media','available'=>true,'media'=>$row];}
    }
    if($type==='message'&&!empty($report['message_id'])){
        $stmt=$pdo->prepare(
            'SELECT m.id,m.public_id,m.sender_user_id,m.body,m.moderation_status,m.created_at,m.updated_at,
                    mt.public_id thread_id,mt.subject thread_subject,COALESCE(NULLIF(u.display_name,\'\'),u.full_name,u.email) sender_name
             FROM messages m INNER JOIN message_threads mt ON mt.id=m.thread_id
             INNER JOIN users u ON u.id=m.sender_user_id WHERE m.id=? LIMIT 1'
        );
        $stmt->execute([(int)$report['message_id']]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if($row)return ['type'=>'message','available'=>true,'message'=>$row];
    }
    return ['type'=>$type,'available'=>false,'snapshot'=>mg_content_review_json($report['subject_snapshot_json']??null)];
}

function mg_review_account_context(PDO $pdo,int $userId):?array
{
    if($userId<1)return null;
    $stmt=$pdo->prepare(
        'SELECT u.id,u.public_id,u.display_name,u.full_name,u.email,u.status,u.created_at,u.updated_at,
                pp.public_id profile_id,pp.slug,pp.status profile_status,pp.visibility
         FROM users u LEFT JOIN public_profiles pp ON pp.user_id=u.id WHERE u.id=? LIMIT 1'
    );
    $stmt->execute([$userId]);$user=$stmt->fetch(PDO::FETCH_ASSOC);if(!$user)return null;
    $counts=[];
    foreach([
        'posts'=>'SELECT COUNT(*) FROM feed_posts WHERE created_by_user_id=?',
        'comments'=>'SELECT COUNT(*) FROM feed_post_comments WHERE user_id=?',
        'messages'=>'SELECT COUNT(*) FROM messages WHERE sender_user_id=?',
        'reports'=>'SELECT COUNT(*) FROM social_reports WHERE subject_user_id=?',
        'active_reports'=>"SELECT COUNT(*) FROM social_reports WHERE subject_user_id=? AND status IN ('open','reviewing')",
    ] as $key=>$sql){$counter=$pdo->prepare($sql);$counter->execute([$userId]);$counts[$key]=(int)$counter->fetchColumn();}
    $restriction=$pdo->prepare(
        "SELECT public_id,restriction_type,status,reason,starts_at,ends_at,created_at
         FROM user_moderation_restrictions WHERE user_id=? AND status='active' AND (ends_at IS NULL OR ends_at>NOW()) ORDER BY created_at DESC"
    );
    $restriction->execute([$userId]);
    return ['user'=>$user,'counts'=>$counts,'restrictions'=>$restriction->fetchAll(PDO::FETCH_ASSOC)];
}

function mg_review_history(PDO $pdo,int $reportId):array
{
    $stmt=$pdo->prepare(
        'SELECT a.public_id,a.action_type,a.reason,a.previous_state,a.resulting_state,a.metadata_json,a.created_at,
                COALESCE(NULLIF(u.display_name,\'\'),u.full_name,u.email,\'System\') actor_name
         FROM content_moderation_actions a LEFT JOIN users u ON u.id=a.actor_user_id
         WHERE a.report_id=? ORDER BY a.created_at DESC,a.id DESC LIMIT 100'
    );
    $stmt->execute([$reportId]);$items=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$items[]=[
        'id'=>(string)$row['public_id'],'action'=>(string)$row['action_type'],'reason'=>(string)($row['reason']??''),
        'previous_state'=>$row['previous_state']??null,'resulting_state'=>$row['resulting_state']??null,
        'metadata'=>mg_content_review_json($row['metadata_json']??null),'actor_name'=>(string)$row['actor_name'],'created_at'=>$row['created_at']??null,
    ];
    return $items;
}
