<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__,2) . '/includes/hosted-game-releases.php';

mg_require_method('GET');$user=mg_require_api_user();$actorId=(int)$user['id'];
$canManage=mg_admin_permission_user_has($user,'admin.hosted_games.releases.manage')||mg_admin_permission_user_has($user,'admin.hosted_games.manage')||mg_admin_permission_user_has($user,'admin.settings.manage');
if(!$canManage)mg_fail('Hosted Games release management permission is required.',403);
$pdo=mg_db();$gamePublicId=trim((string)($_GET['game_id']??''));$releasePublicId=trim((string)($_GET['release_id']??''));
try{
    $game=mg_hosted_game_by_public_id($pdo,$gamePublicId,false);if(!$game)throw new MgHostedGameException('Hosted game not found.');
    $release=mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false);if(!$release)throw new MgHostedGameException('Hosted game release not found.');
    $path=mg_hosted_game_release_zip_path($release);$filename='hosted-game-'.preg_replace('/[^a-z0-9-]+/i','-',(string)$game['slug']).'-v'.(int)$release['version_number'].'.zip';
    mg_audit('hosted_game.release_zip_downloaded','hosted_game_release',['game_id'=>$gamePublicId,'release_id'=>$releasePublicId],$actorId);
    header('Content-Type: application/zip');header('Content-Length: '.(string)filesize($path));header('Content-Disposition: attachment; filename="'.$filename.'"');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');readfile($path);exit;
}catch(InvalidArgumentException|MgHostedGameException $error){mg_fail($error->getMessage(),404);}
