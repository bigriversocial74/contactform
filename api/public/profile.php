<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/profiles/_public_profile.php';
require_once dirname(__DIR__) . '/social/_engagement.php';

mg_require_method('GET');
$pdo=mg_db();
$slug=(string)($_GET['slug']??'');
$preview=(string)($_GET['preview']??'')==='1';
$viewer=mg_public_profile_session_viewer($pdo);

try{
    $data=mg_public_profile_read($pdo,$slug,[
        'viewer_id'=>$viewer['id']??null,
        'preview'=>$preview,
        'product_cursor'=>isset($_GET['product_cursor'])?(string)$_GET['product_cursor']:null,
        'post_cursor'=>isset($_GET['post_cursor'])?(string)$_GET['post_cursor']:null,
        'plan_cursor'=>isset($_GET['plan_cursor'])?(string)$_GET['plan_cursor']:null,
        'product_limit'=>$_GET['product_limit']??MG_PUBLIC_PROFILE_DEFAULT_LIMIT,
        'post_limit'=>$_GET['post_limit']??MG_PUBLIC_PROFILE_DEFAULT_LIMIT,
        'plan_limit'=>$_GET['plan_limit']??MG_PUBLIC_PROFILE_DEFAULT_LIMIT,
    ]);

    $viewerId=isset($viewer['id'])?(int)$viewer['id']:null;
    $isOwner=!empty($data['profile']['availability']['is_owner']);
    $relationship=[
        'authenticated'=>$viewerId!==null,
        'can_follow'=>$viewerId!==null&&!$isOwner,
        'following'=>false,
        'muted'=>false,
        'blocking'=>false,
        'followers'=>(int)($data['social_counts']['followers']??0),
    ];
    if($viewerId!==null&&!$isOwner){
        $target=mg_engagement_profile_target($pdo,(string)$data['profile']['id']);
        $relationship=array_merge($relationship,mg_engagement_relationship_state($pdo,$viewerId,(int)$target['user_id']));
    }
    $data['relationship']=$relationship;
}catch(InvalidArgumentException $error){
    if($error->getMessage()==='Invalid pagination cursor.')mg_fail('Invalid pagination cursor.',422);
    mg_fail('Profile not found.',404);
}catch(RuntimeException){
    mg_fail('Profile not found.',404);
}catch(Throwable $error){
    error_log('Public profile read failed: '.$error::class);
    mg_fail('Unable to load profile.',500);
}

$isAnonymous=$viewer===null;
$isPublic=(string)($data['profile']['visibility']??'')==='public';
$isPreview=!empty($data['profile']['availability']['is_preview']);
if($isAnonymous&&$isPublic&&!$isPreview){
    header_remove('Set-Cookie');
    header('Cache-Control: public, max-age=60, stale-while-revalidate=30');
}else{
    header('Cache-Control: private, no-store, max-age=0');
}
header('Vary: Cookie, Authorization');
if((string)($data['profile']['visibility']??'')==='unlisted')header('X-Robots-Tag: noindex, nofollow');
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'message'=>'OK','data'=>$data],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
