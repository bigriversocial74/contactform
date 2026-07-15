<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/hosted-game-preview.php';

mg_require_method('GET');$user=mg_require_api_user();$pdo=mg_db();$sessionId=trim((string)($_GET['session_id']??''));$after=max(0,(int)($_GET['after']??0));
try{$session=mg_hosted_game_preview_session_by_public_id($pdo,$user,$sessionId);mg_ok(['events'=>mg_hosted_game_preview_events($pdo,$session,$after),'session'=>['id'=>$sessionId,'expires_at'=>$session['expires_at'],'reset_at'=>$session['reset_at']??null]]);}catch(InvalidArgumentException|MgHostedGameException $error){mg_fail($error->getMessage(),404);}
