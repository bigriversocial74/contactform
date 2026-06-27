<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once __DIR__ . '/_action_center_wallet.php';

function mg_action_center_counts_plus_wallet(PDO $pdo,int $userId,string $email): array
{
    return mg_ac_wallet_counts_merge(
        mg_action_center_counts($pdo,$userId),
        mg_ac_wallet_counts($pdo,$userId,$email)
    );
}

function mg_action_center_page_plus_wallet(PDO $pdo,int $userId,string $email,string $folder,int $limit=50,string $search='',?array $cursor=null): array
{
    return mg_ac_wallet_page_merge(
        $pdo,
        $userId,
        $email,
        $folder,
        mg_action_center_page($pdo,$userId,$folder,$limit,$search,$cursor),
        $limit,
        $search,
        $cursor
    );
}

mg_require_method('GET');
$user=mg_require_api_user();
$userId=(int)$user['id'];
$userEmail=mg_ac_wallet_user_email($user);
$folder=mg_action_center_folder(trim((string)($_GET['folder']??'inbox')));
$limit=mg_action_center_limit($_GET['limit']??50);
$search=mg_action_center_search($_GET['q']??'');
try{
    $cursor=mg_action_center_decode_cursor(isset($_GET['cursor'])?(string)$_GET['cursor']:null);
}catch(InvalidArgumentException $e){
    mg_fail($e->getMessage(),422);
}
$pdo=mg_db();
$page=mg_action_center_page_plus_wallet($pdo,$userId,$userEmail,$folder,$limit,$search,$cursor);

mg_ok([
    'folder'=>$folder,
    'query'=>$search,
    'counts'=>mg_action_center_counts_plus_wallet($pdo,$userId,$userEmail),
    'items'=>$page['items'],
    'page'=>$page['page'],
]);
