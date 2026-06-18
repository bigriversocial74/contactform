<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

mg_require_method('GET');
$user=mg_require_api_user();
$folder=mg_action_center_folder(trim((string)($_GET['folder']??'inbox')));
$limit=mg_action_center_limit($_GET['limit']??50);
$search=mg_action_center_search($_GET['q']??'');
try{
    $cursor=mg_action_center_decode_cursor(isset($_GET['cursor'])?(string)$_GET['cursor']:null);
}catch(InvalidArgumentException $e){
    mg_fail($e->getMessage(),422);
}
$pdo=mg_db();
$page=mg_action_center_page($pdo,(int)$user['id'],$folder,$limit,$search,$cursor);

mg_ok([
    'folder'=>$folder,
    'query'=>$search,
    'counts'=>mg_action_center_counts($pdo,(int)$user['id']),
    'items'=>$page['items'],
    'page'=>$page['page'],
]);
