<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/hosted-game-preview.php';

mg_require_method('POST');$input=mg_input();mg_require_csrf_for_write($input);$user=mg_require_api_user();$pdo=mg_db();$sessionId=trim((string)($input['session_id']??''));
try{$session=mg_hosted_game_preview_session_by_public_id($pdo,$user,$sessionId);mg_hosted_game_preview_reset($pdo,$session,(int)$user['id']);mg_ok(['reset'=>true,'session_id'=>$sessionId],'Preview runs, events, scores, and state were reset.');}catch(InvalidArgumentException|MgHostedGameException $error){mg_fail($error->getMessage(),409);}catch(Throwable $error){mg_fail('Unable to reset preview data.',500);}
