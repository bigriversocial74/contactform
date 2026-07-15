<?php
declare(strict_types=1);

$root=dirname(__DIR__);$errors=[];
$required=[
'database/hosted_games_release_qa_foundation_v1.sql','includes/hosted-game-releases.php','includes/hosted-game-preview.php','includes/hosted-game-releases-view.php','merchant-game-releases.php','admin/hosted-game-releases.php','hosted-game-preview.php','api/merchant/hosted-game-releases.php','api/admin/hosted-game-releases.php','api/merchant/hosted-game-release-download.php','api/admin/hosted-game-release-download.php','api/hosted-games/preview-document.php','api/hosted-games/preview-asset.php','api/hosted-games/preview-runtime.php','api/hosted-games/preview-events.php','api/hosted-games/preview-reset.php','assets/js/hosted-game-releases.js','assets/js/hosted-game-preview.js','assets/css/hosted-game-releases.css','assets/css/hosted-game-preview.css','docs/hosted-games-release-qa-v1.md'];
foreach($required as $path){if(!is_file($root.'/'.$path))$errors[]="Missing required file: {$path}";}
$read=static function(string $path)use($root):string{$value=@file_get_contents($root.'/'.$path);return is_string($value)?$value:'';};
$must=static function(string $path,array $needles)use(&$errors,$read):void{$content=$read($path);foreach($needles as $needle){if(!str_contains($content,(string)$needle))$errors[]="{$path} missing contract: {$needle}";}};
$forbid=static function(string $path,array $needles)use(&$errors,$read):void{$content=$read($path);foreach($needles as $needle){if(str_contains($content,(string)$needle))$errors[]="{$path} contains forbidden contract: {$needle}";}};

$must('database/hosted_games_release_qa_foundation_v1.sql',[
"ENUM('draft','testing','active','failed','archived')",'release_notes','package_zip_storage_key','package_zip_bytes','manifest_version','sdk_version','validation_status','health_status','activated_by_user_id','hosted_game_test_sessions','hosted_game_test_runs','hosted_game_test_events','hosted_game_test_state','merchant.hosted_games.releases.manage','merchant.hosted_games.preview','admin.hosted_games.releases.manage','admin.hosted_games.preview']);
$must('includes/hosted-game-upload.php',["'draft'",'package_zip_storage_key','package_zip_bytes','Unable to preserve the original uploaded ZIP','packageStorageKey']);
$forbid('includes/hosted-game-upload.php',['UPDATE hosted_games SET current_release_public_id=?']);
$must('includes/hosted-game-standard-upload.php',['validation_status','validation_json','validated_at',"status IN ('draft','testing')"]);
$must('includes/hosted-game-releases.php',['mg_hosted_game_release_history','mg_hosted_game_release_health_check','mg_hosted_game_release_activate','mg_hosted_game_release_archive','The active release cannot be archived or deleted','mg_hosted_game_release_compare','mg_hosted_game_release_zip_path',"status='archived'",'current_release_public_id']);
$must('api/merchant/hosted-game-releases.php',["\$action==='compare'","\$action==='health_check'","\$action==='activate'||\$action==='rollback'","\$action==='archive'","\$action==='create_preview'","THEN 'failed'"]);
$must('api/admin/hosted-game-releases.php',["\$action==='compare'","\$action==='health_check'","\$action==='activate'||\$action==='rollback'","\$action==='archive'","\$action==='create_preview'","THEN 'failed'"]);
$must('includes/hosted-game-preview.php',['mg_hosted_game_preview_access','mg_hosted_game_preview_session','mg_hosted_game_preview_runtime','test_reward_','simulated_delivered','inventory_consumed','hosted_game_test_runs','hosted_game_test_events','hosted_game_test_state','mg_hosted_game_preview_reset']);
$forbid('includes/hosted-game-preview.php',['mg_hosted_game_api_request(','/api/public/v1/rewards/issue.php']);
$must('hosted-game-preview.php',['Protected QA','No live inventory','data-viewport="desktop"','data-viewport="tablet"','data-viewport="mobile"','SDK requests','Errors','data-preview-reset']);
$must('assets/js/hosted-game-preview.js',["'telemetry'",'telemetryType','sdk_request','data-console-tab','data-viewport','data-preview-reset','runtimeUrl']);
$must('api/hosted-games/preview-document.php',['mg_hosted_game_preview_session_by_public_id','mg_hosted_game_standard_valid_bridge_token','hosted-game-preview-assets','hosted-game-child-bridge.js']);
$must('api/hosted-games/preview-asset.php',['mg_require_api_user','mg_hosted_game_preview_session_by_public_id','Cache-Control: no-store']);
$must('api/hosted-games/preview-runtime.php',['mg_require_api_user','mg_require_csrf_for_write','mg_hosted_game_preview_runtime']);
$must('api/hosted-games/preview-reset.php',['mg_require_csrf_for_write','mg_hosted_game_preview_reset']);
$must('.htaccess',['hosted-game-preview-assets','preview-asset.php','hosted-game-preview']);
$must('includes/hosted-game-releases-view.php',['Upload a draft package','Compare manifests','Complete history']);
$must('assets/js/hosted-game-releases.js',['Roll back to this release','Download original ZIP','Preview & test','Run health check','create_preview']);
$must('config/migrations.php',["'hosted_games_management_v1.sql'","'hosted_games_analytics_diagnostics_v1.sql'","'hosted_games_release_qa_foundation_v1.sql'"]);

$migrations=require $root.'/config/migrations.php';$ordered=is_array($migrations['ordered_files']??null)?$migrations['ordered_files']:[];
$management=array_search('hosted_games_management_v1.sql',$ordered,true);$analytics=array_search('hosted_games_analytics_diagnostics_v1.sql',$ordered,true);$release=array_search('hosted_games_release_qa_foundation_v1.sql',$ordered,true);
if(!is_int($management)||!is_int($analytics)||!is_int($release)||!($management<$analytics&&$analytics<$release))$errors[]='Hosted Games release QA migration order is invalid.';

if($errors!==[]){fwrite(STDERR,"Hosted Games Release and QA v1 validation failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}echo "Hosted Games Release and QA v1 validation passed.\n";
