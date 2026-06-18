<?php
declare(strict_types=1);

require_once __DIR__ . '/_publishing.php';
require_once __DIR__ . '/_media_assets.php';

mg_require_method('GET');
$user=mg_require_permission('social.posts.create');
$userId=(int)$user['id'];
$postId=strtolower(trim((string)($_GET['post_id']??'')));
mg_rate_limit('social.post_media.owner_read','user:'.$userId,180,60);

try{
    $pdo=mg_db();
    $row=mg_publishing_post_owned($pdo,$userId,$postId,false);
    $post=mg_publishing_owner_project($pdo,$row);
    $enriched=mg_social_media_enrich_owner_posts($pdo,$userId,['items'=>[$post]]);
    header('Cache-Control: private, no-store, max-age=0');
    mg_ok(['post'=>$enriched['items'][0]??$post]);
}catch(InvalidArgumentException $error){
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    mg_fail($error->getMessage(),404);
}
