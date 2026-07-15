<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__,2) . '/includes/hosted-game-releases.php';

mg_require_method('GET');
$user=mg_merchant_require_permission('merchant.hosted_games.releases.manage');
$pdo=mg_db();$workspace=mg_merchant_ensure_workspace($pdo,$user);$merchantUserId=(int)$workspace['merchant_user_id'];
$gamePublicId=trim((string)($_GET['game_id']??''));$releasePublicId=trim((string)($_GET['release_id']??''));
try{
    $game=mg_hosted_game_for_merchant($pdo,$merchantUserId,$gamePublicId,false);
    $release=mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false);
    if(!$release)throw new MgHostedGameException('Hosted game release not found.');
    $path=mg_hosted_game_release_zip_path($release);$filename='hosted-game-'.preg_replace('/[^a-z0-9-]+/i','-',(string)$game['slug']).'-v'.(int)$release['version_number'].'.zip';
    header('Content-Type: application/zip');header('Content-Length: '.(string)filesize($path));header('Content-Disposition: attachment; filename="'.$filename.'"');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
    readfile($path);exit;
}catch(InvalidArgumentException|MgHostedGameException $error){mg_fail($error->getMessage(),404);}
