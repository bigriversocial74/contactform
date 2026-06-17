<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/profiles/_public_profile.php';
require_once dirname(__DIR__) . '/social/_engagement.php';

mg_require_method('GET');
$pdo=mg_db();
$viewer=mg_public_profile_session_viewer($pdo);
$viewerId=isset($viewer['id'])?(int)$viewer['id']:null;
$postId=trim((string)($_GET['post_id']??''));
$cursor=isset($_GET['cursor'])?(string)$_GET['cursor']:null;
$limit=max(1,min((int)($_GET['limit']??20),MG_ENGAGEMENT_COMMENT_LIMIT));

$identifier=$viewerId!==null?'user:'.$viewerId:'ip:'.(mg_client_ip()??'unknown');
mg_rate_limit('social.engagement.read',$identifier,$viewerId!==null?240:120,60);

try{
    $post=mg_engagement_post($pdo,$postId,$viewerId,false);
    $data=[
        'post_id'=>(string)$post['public_id'],
        'engagement'=>mg_engagement_post_state($pdo,$post,$viewerId),
        'comments'=>mg_engagement_comments($pdo,$post,$viewerId,$cursor,$limit),
        'permissions'=>[
            'authenticated'=>$viewerId!==null,
            'can_comment'=>$viewerId!==null,
            'is_post_owner'=>$viewerId!==null&&$viewerId===(int)$post['created_by_user_id'],
        ],
    ];
}catch(InvalidArgumentException $error){
    mg_fail($error->getMessage()==='Invalid pagination cursor.'?'Invalid pagination cursor.':'Post not found.',422);
}catch(RuntimeException){
    mg_fail('Post not found.',404);
}catch(Throwable $error){
    mg_security_log('error','social.engagement_read_failed','Post engagement read failed.',['exception_class'=>$error::class],$viewerId);
    mg_fail('Unable to load post engagement.',500);
}

if($viewerId===null){
    header_remove('Set-Cookie');
    header('Cache-Control: public, max-age=15, stale-while-revalidate=15');
}else{
    header('Cache-Control: private, no-store, max-age=0');
}
header('Vary: Cookie, Authorization');
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'message'=>'OK','data'=>$data],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
