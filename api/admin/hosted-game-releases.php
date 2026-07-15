<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__,2) . '/includes/hosted-game-preview.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$input=$method==='POST'?mg_input():$_GET;
$user=mg_require_api_user();$actorId=(int)$user['id'];
$canView=mg_admin_permission_user_has($user,'admin.hosted_games.view')||mg_admin_permission_user_has($user,'admin.hosted_games.manage')||mg_admin_permission_user_has($user,'admin.hosted_games.releases.manage')||mg_admin_permission_user_has($user,'admin.settings.manage');
$canManage=mg_admin_permission_user_has($user,'admin.hosted_games.releases.manage')||mg_admin_permission_user_has($user,'admin.hosted_games.manage')||mg_admin_permission_user_has($user,'admin.settings.manage');
if(!$canView)mg_fail('Hosted Games administration permission is required.',403);
if($method==='POST'&&!$canManage)mg_fail('Hosted Games release management permission is required.',403);
$pdo=mg_db();
if(!mg_hosted_game_release_schema_ready($pdo))mg_fail('Hosted Games Release and QA setup is incomplete. Import database/hosted_games_release_qa_foundation_v1.sql.',503);
$gamePublicId=trim((string)($input['game_id']??''));if($gamePublicId==='')mg_fail('Hosted game is required.',422);
try{
    $game=mg_hosted_game_by_public_id($pdo,$gamePublicId,false);if(!$game)mg_fail('Hosted game not found.',404);
    if($method==='GET')mg_ok(['game'=>['id'=>(string)$game['public_id'],'name'=>(string)$game['name'],'slug'=>(string)$game['slug'],'status'=>(string)$game['status'],'merchant_user_id'=>(int)$game['merchant_user_id'],'current_release_id'=>$game['current_release_public_id']??null],'releases'=>mg_hosted_game_release_history($pdo,$game)]);
    mg_require_method('POST');mg_require_csrf_for_write($input);
    if(function_exists('mg_rate_limit'))mg_rate_limit('admin.hosted_game.releases','game:'.(int)$game['id'].':user:'.$actorId,180,300);
    $action=strtolower(trim((string)($input['action']??'')));$releaseId=trim((string)($input['release_id']??''));
    if($action==='compare')mg_ok(mg_hosted_game_release_compare($pdo,$game,trim((string)($input['left_release_id']??'')),trim((string)($input['right_release_id']??''))));
    if($releaseId==='')mg_fail('Hosted game release is required.',422);
    if($action==='update_notes'){$release=mg_hosted_game_release_update_notes($pdo,$game,$releaseId,(string)($input['release_notes']??''),$actorId);mg_ok(['release'=>mg_hosted_game_release_payload($release,$gamePublicId)],'Release notes saved.');}
    if($action==='health_check'){
        $health=mg_hosted_game_release_health_check($pdo,$game,$releaseId,$actorId);
        $pdo->prepare("UPDATE hosted_game_releases SET status=CASE WHEN ?='failed' AND status<>'active' THEN 'failed' WHEN ?<>'failed' AND status='failed' THEN 'testing' ELSE status END,updated_at=NOW() WHERE game_id=? AND public_id=?")
            ->execute([(string)$health['status'],(string)$health['status'],(int)$game['id'],$releaseId]);
        mg_ok(['health'=>$health],'Release health check completed.');
    }
    if($action==='activate'||$action==='rollback'){$release=mg_hosted_game_release_activate($pdo,$game,$releaseId,$actorId,$action==='rollback');mg_ok(['release'=>mg_hosted_game_release_payload($release,$gamePublicId)],$action==='rollback'?'Release rolled back and activated.':'Release activated.');}
    if($action==='archive'){$release=mg_hosted_game_release_archive($pdo,$game,$releaseId,$actorId);mg_ok(['release'=>mg_hosted_game_release_payload($release,$gamePublicId)],'Release archived.');}
    if($action==='create_preview'){$access=mg_hosted_game_preview_access($pdo,$user,$gamePublicId,$releaseId);$session=mg_hosted_game_preview_session($pdo,$access,true);mg_ok(['session_id'=>(string)$session['public_id'],'preview_url'=>'/hosted-game-preview.php?game='.rawurlencode($gamePublicId).'&release='.rawurlencode($releaseId)],'Protected preview session ready.');}
    mg_fail('Invalid release action.',422);
}catch(InvalidArgumentException|MgHostedGameException $error){mg_fail($error->getMessage(),409);}catch(Throwable $error){
    mg_security_log('error','admin.hosted_game.release_action_failed','Hosted game release action failed.',['game_id'=>$gamePublicId,'action'=>$input['action']??null,'message'=>$error->getMessage()],$actorId);
    mg_fail('Unable to complete the release action.',500);
}
