<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/social/_publishing.php';
require_once dirname(__DIR__) . '/social/_published_attachment_cards.php';

mg_require_method('GET');
$pdo=mg_db();
$viewer=mg_public_profile_session_viewer($pdo);
$viewerId=isset($viewer['id'])?(int)$viewer['id']:null;
$raw=trim((string)($_GET['post_ids']??''));
$ids=[];
foreach(explode(',',$raw) as $value){
    $value=strtolower(trim($value));
    if($value===''||isset($ids[$value]))continue;
    if(preg_match('/^[a-f0-9-]{36}$/',$value)!==1)mg_fail('Invalid post identifier.',422);
    $ids[$value]=$value;
    if(count($ids)>36)mg_fail('Too many posts requested.',422);
}
if($ids===[])mg_ok(['cards'=>[]]);

$identifier=$viewerId!==null?'user:'.$viewerId:'ip:'.(mg_client_ip()??'unknown');
mg_rate_limit('social.feed_attachments.read',$identifier,$viewerId!==null?240:120,60);

$values=array_values($ids);
$placeholders=implode(',',array_fill(0,count($values),'?'));
$stmt=$pdo->prepare(
    "SELECT fp.*,pp.slug profile_slug,pp.status profile_status,pp.visibility profile_visibility,u.status user_status
     FROM feed_posts fp
     INNER JOIN users u ON u.id=fp.created_by_user_id
     INNER JOIN public_profiles pp ON pp.user_id=fp.created_by_user_id
     WHERE fp.public_id IN ({$placeholders})"
);
$stmt->execute($values);
$visible=[];
$contexts=[];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $post){
    if((string)$post['user_status']!=='active'||(string)$post['profile_status']!=='active'||!in_array((string)$post['profile_visibility'],['public','unlisted'],true))continue;
    $authorId=(int)$post['created_by_user_id'];
    if(!isset($contexts[$authorId]))$contexts[$authorId]=mg_social_view_context($pdo,$viewerId,$authorId);
    if(!mg_social_can_view($pdo,$post,$viewerId,$contexts[$authorId]))continue;
    $visible[]=$post;
}

$cards=mg_feed_published_attachment_cards($pdo,$visible,$viewerId);
foreach($values as $postId){if(!isset($cards[$postId]))$cards[$postId]=[];}

if($viewerId===null){
    header_remove('Set-Cookie');
    header('Cache-Control: public, max-age=20, stale-while-revalidate=20');
}else{
    header('Cache-Control: private, no-store, max-age=0');
}
header('Vary: Cookie, Authorization');
header('X-Robots-Tag: noindex, follow');
mg_ok(['cards'=>$cards]);
