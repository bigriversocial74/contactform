<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/hosted-game-preview.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$input=$method==='POST'?mg_input():$_GET;
$user=mg_require_api_user();$pdo=mg_db();$sessionId=trim((string)($input['session_id']??''));
try{
    $session=mg_hosted_game_preview_session_by_public_id($pdo,$user,$sessionId);
    if($method==='GET')mg_ok(mg_hosted_game_preview_runtime($pdo,$session,$user,'session',$input));
    mg_require_method('POST');mg_require_csrf_for_write($input);
    if(function_exists('mg_rate_limit'))mg_rate_limit('hosted.game.preview.runtime','session:'.$sessionId.':user:'.(int)$user['id'],300,300);
    $action=strtolower(trim((string)($input['action']??'')));
    $data=mg_hosted_game_preview_runtime($pdo,$session,$user,$action,$input);
    mg_ok($data);
}catch(InvalidArgumentException|MgHostedGameException $error){mg_fail($error->getMessage(),409);}catch(Throwable $error){
    try{if(isset($session))mg_hosted_game_preview_event($pdo,$session,null,'runtime','runtime_error',['message'=>$error->getMessage()],'error',$input['action']??null);}catch(Throwable){}
    mg_security_log('error','hosted.game.preview_runtime_failed','Hosted game preview runtime failed.',['session_id'=>$sessionId,'action'=>$input['action']??null,'message'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to complete the preview request.',500);
}
