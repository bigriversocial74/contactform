<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-game-releases.php';
require_once __DIR__ . '/admin-permission-matrix.php';

function mg_hosted_game_preview_access(PDO $pdo,array $user,string $gamePublicId,string $releasePublicId): array
{
    if(!mg_hosted_game_release_schema_ready($pdo))throw new MgHostedGameException('Hosted Games Release and QA setup is incomplete.');
    $game=mg_hosted_game_by_public_id($pdo,$gamePublicId,false);
    if(!$game)throw new MgHostedGameException('Hosted game not found.');
    $userId=(int)$user['id'];
    $isAdmin=mg_admin_permission_user_has($user,'admin.hosted_games.preview')
        ||mg_admin_permission_user_has($user,'admin.hosted_games.releases.manage')
        ||mg_admin_permission_user_has($user,'admin.hosted_games.manage')
        ||mg_admin_permission_user_has($user,'admin.settings.manage');
    $isMerchant=(int)$game['merchant_user_id']===$userId
        &&(mg_api_user_has_permission($user,'merchant.hosted_games.preview')
            ||mg_api_user_has_permission($user,'merchant.hosted_games.releases.manage')
            ||mg_api_user_has_permission($user,'merchant.hosted_games.manage')
            ||mg_user_has_merchant_access($user,$pdo));
    if(!$isAdmin&&!$isMerchant)throw new MgHostedGameException('Hosted game preview access is required.');
    $release=mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false);
    if(!$release)throw new MgHostedGameException('Hosted game release not found.');
    return ['game'=>$game,'release'=>$release,'scope'=>$isAdmin?'admin':'merchant','user_id'=>$userId];
}

function mg_hosted_game_preview_session(PDO $pdo,array $access,bool $create=true): ?array
{
    $stmt=$pdo->prepare("SELECT * FROM hosted_game_test_sessions WHERE game_id=? AND release_id=? AND actor_user_id=? AND status='active' AND expires_at>NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([(int)$access['game']['id'],(int)$access['release']['id'],(int)$access['user_id']]);
    $session=$stmt->fetch(PDO::FETCH_ASSOC);
    if($session||!$create)return $session?:null;
    $game=$access['game'];
    $config=$pdo->prepare("SELECT dp.public_id program_public_id,c.public_id campaign_public_id,cpt.public_id template_public_id
        FROM hosted_games hg
        LEFT JOIN distribution_programs dp ON dp.id=hg.distribution_program_id
        LEFT JOIN campaigns c ON c.id=hg.campaign_id
        LEFT JOIN catalog_pppm_templates cpt ON cpt.id=hg.pppm_template_id
        WHERE hg.id=? LIMIT 1");
    $config->execute([(int)$game['id']]);
    $snapshot=$config->fetch(PDO::FETCH_ASSOC)?:[];
    $publicId=mg_hosted_game_uuid();
    $expiresAt=gmdate('Y-m-d H:i:s',time()+14400);
    $playerKey='preview-user-'.(int)$access['user_id'];
    $pdo->prepare("INSERT INTO hosted_game_test_sessions
        (public_id,game_id,release_id,actor_user_id,actor_scope,status,test_program_public_id,test_campaign_public_id,test_template_public_id,test_player_key,expires_at,last_activity_at,created_at,updated_at)
        VALUES (?,?,?,?,?,'active',?,?,?,?,?,NOW(),NOW(),NOW())")
        ->execute([$publicId,(int)$game['id'],(int)$access['release']['id'],(int)$access['user_id'],(string)$access['scope'],$snapshot['program_public_id']??null,$snapshot['campaign_public_id']??null,$snapshot['template_public_id']??null,$playerKey,$expiresAt]);
    if((string)$access['release']['status']!=='active'&&(string)$access['release']['status']!=='failed'){
        $pdo->prepare("UPDATE hosted_game_releases SET status='testing',updated_at=NOW() WHERE id=?")->execute([(int)$access['release']['id']]);
    }
    mg_audit('hosted_game.preview_session_created','hosted_game_release',['game_id'=>(string)$game['public_id'],'release_id'=>(string)$access['release']['public_id'],'session_id'=>$publicId,'scope'=>(string)$access['scope']],(int)$access['user_id']);
    return mg_hosted_game_preview_session($pdo,$access,false);
}

function mg_hosted_game_preview_session_by_public_id(PDO $pdo,array $user,string $sessionPublicId): array
{
    $stmt=$pdo->prepare("SELECT ts.*,hg.public_id game_public_id,hg.name game_name,hg.slug game_slug,hg.merchant_user_id,
        hgr.public_id release_public_id,hgr.version_number,hgr.status release_status,hgr.storage_key,hgr.entry_file,hgr.manifest_json,hgr.validation_status,hgr.health_status
        FROM hosted_game_test_sessions ts
        INNER JOIN hosted_games hg ON hg.id=ts.game_id
        INNER JOIN hosted_game_releases hgr ON hgr.id=ts.release_id AND hgr.game_id=hg.id
        WHERE ts.public_id=? LIMIT 1");
    $stmt->execute([$sessionPublicId]);
    $session=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$session)throw new MgHostedGameException('Preview session not found.');
    if((int)$session['actor_user_id']!==(int)$user['id'])throw new MgHostedGameException('Preview session access denied.');
    if((string)$session['status']!=='active'||strtotime((string)$session['expires_at'])<time())throw new MgHostedGameException('Preview session has expired.');
    return $session;
}

function mg_hosted_game_preview_manifest(array $session): array
{
    $game=['name'=>$session['game_name'],'slug'=>$session['game_slug'],'entry_file'=>$session['entry_file']];
    return mg_hosted_game_standard_normalize_manifest(mg_hosted_game_json_decode($session['manifest_json']??null),$game,[],(string)$session['entry_file']);
}

function mg_hosted_game_preview_event(PDO $pdo,array $session,?int $runId,string $source,string $eventType,array $event=[],string $severity='info',?string $requestAction=null,?int $durationMs=null): string
{
    $publicId=mg_hosted_game_uuid();
    $allowedSources=['shell','sdk','runtime','game','system'];
    $allowedSeverity=['debug','info','warning','error'];
    if(!in_array($source,$allowedSources,true))$source='runtime';
    if(!in_array($severity,$allowedSeverity,true))$severity='info';
    $pdo->prepare("INSERT INTO hosted_game_test_events (public_id,session_id,run_id,source,severity,event_type,request_action,duration_ms,event_json,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,NOW())")
        ->execute([$publicId,(int)$session['id'],$runId,$source,mb_substr($eventType,0,120),$requestAction!==null?mb_substr($requestAction,0,100):null,$durationMs!==null?max(0,min(120000,$durationMs)):null,$event===[]?null:mg_hosted_game_json_encode($event,65536)]);
    $pdo->prepare('UPDATE hosted_game_test_sessions SET last_activity_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$session['id']]);
    return $publicId;
}

function mg_hosted_game_preview_events(PDO $pdo,array $session,int $afterId=0): array
{
    $stmt=$pdo->prepare("SELECT id,public_id,source,severity,event_type,request_action,duration_ms,event_json,created_at
        FROM hosted_game_test_events WHERE session_id=? AND id>? ORDER BY id ASC LIMIT 500");
    $stmt->execute([(int)$session['id'],max(0,$afterId)]);
    return array_map(static function(array $row):array{
        return ['sequence'=>(int)$row['id'],'id'=>(string)$row['public_id'],'source'=>(string)$row['source'],'severity'=>(string)$row['severity'],'event_type'=>(string)$row['event_type'],'action'=>$row['request_action']??null,'duration_ms'=>$row['duration_ms']!==null?(int)$row['duration_ms']:null,'event'=>mg_hosted_game_json_decode($row['event_json']??null),'created_at'=>$row['created_at']];
    },$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]);
}

function mg_hosted_game_preview_run(PDO $pdo,array $session,int $userId,string $publicId,bool $forUpdate=false): ?array
{
    $sql='SELECT * FROM hosted_game_test_runs WHERE session_id=? AND player_user_id=? AND public_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([(int)$session['id'],$userId,$publicId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
}

function mg_hosted_game_preview_run_authorized(PDO $pdo,array $session,int $userId,array $input,bool $forUpdate=false): array
{
    $publicId=trim((string)($input['run_id']??''));$token=trim((string)($input['run_token']??''));
    $run=mg_hosted_game_preview_run($pdo,$session,$userId,$publicId,$forUpdate);
    if(!$run||$token===''||!hash_equals((string)$run['run_token_hash'],hash('sha256',$token)))throw new MgHostedGameException('Valid preview run authorization is required.');
    return $run;
}

function mg_hosted_game_preview_run_payload(array $run): array
{
    return ['run_id'=>(string)$run['public_id'],'status'=>(string)$run['status'],'score'=>$run['score']!==null?(int)$run['score']:null,'qualified'=>(bool)$run['qualified'],'result'=>mg_hosted_game_json_decode($run['result_json']??null),'reward_id'=>$run['simulated_reward_public_id']??null,'reward_status'=>$run['simulated_reward_status']??null,'started_at'=>$run['started_at'],'completed_at'=>$run['completed_at'],'expires_at'=>$run['expires_at'],'test_mode'=>true];
}

function mg_hosted_game_preview_runtime(PDO $pdo,array $session,array $user,string $action,array $input): array
{
    $userId=(int)$user['id'];$manifest=mg_hosted_game_preview_manifest($session);$started=microtime(true);
    try{
        if($action==='session')return ['game'=>['id'=>(string)$session['game_public_id'],'name'=>(string)$session['game_name'],'slug'=>(string)$session['game_slug'],'release_id'=>(string)$session['release_public_id'],'release_version'=>(int)$session['version_number'],'standard'=>mg_hosted_game_standard_public_manifest($manifest)],'program'=>['id'=>$session['test_program_public_id'],'campaign_id'=>$session['test_campaign_public_id'],'mode'=>'test'],'reward'=>['template_id'=>$session['test_template_public_id'],'mode'=>'simulated','inventory_consumed'=>false],'player'=>['signed_in'=>true,'connected'=>true,'display_name'=>(string)($user['display_name']??$user['full_name']??'Test player'),'test_player'=>true],'runtime'=>['sdk_version'=>MG_HOSTED_GAME_STANDARD_SDK_VERSION,'standard_version'=>'1.0.0','max_duration_seconds'=>(int)$manifest['session']['max_duration_seconds'],'scoring'=>$manifest['scoring'],'qualification'=>$manifest['qualification'],'test_mode'=>true],'ready'=>true];
        if($action==='connect')return ['connected'=>true,'test_mode'=>true,'player'=>['display_name'=>(string)($user['display_name']??$user['full_name']??'Test player')]];
        if($action==='start'){
            $pdo->prepare("UPDATE hosted_game_test_runs SET status='expired',updated_at=NOW() WHERE session_id=? AND player_user_id=? AND status='started'")->execute([(int)$session['id'],$userId]);
            $publicId=mg_hosted_game_uuid();$token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$duration=max(30,min(86400,(int)$manifest['session']['max_duration_seconds']));$expires=gmdate('Y-m-d H:i:s',time()+$duration);
            $metadata=is_array($input['metadata']??null)?$input['metadata']:[];$metadata['test_mode']=true;$metadata['release_id']=(string)$session['release_public_id'];
            $pdo->prepare("INSERT INTO hosted_game_test_runs (public_id,session_id,player_user_id,run_token_hash,status,result_json,started_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,'started',?,NOW(),?,NOW(),NOW())")
                ->execute([$publicId,(int)$session['id'],$userId,hash('sha256',$token),mg_hosted_game_json_encode($metadata,65536),$expires]);
            $runId=(int)$pdo->lastInsertId();mg_hosted_game_preview_event($pdo,$session,$runId,'runtime','run_started',['release_id'=>(string)$session['release_public_id'],'duration_seconds'=>$duration]);
            return ['run'=>['run_id'=>$publicId,'run_token'=>$token,'status'=>'started','expires_at'=>gmdate('c',strtotime($expires)),'max_duration_seconds'=>$duration,'test_mode'=>true]];
        }
        if($action==='complete'){
            $run=mg_hosted_game_preview_run_authorized($pdo,$session,$userId,$input,true);if((string)$run['status']!=='started')return ['duplicate'=>true,'run'=>mg_hosted_game_preview_run_payload($run)];
            $score=isset($input['score'])&&$input['score']!==''?filter_var($input['score'],FILTER_VALIDATE_INT):null;if($score===false)throw new InvalidArgumentException('Preview score must be an integer.');
            $qualified=!empty($input['qualified']);$result=is_array($input['result']??null)?$input['result']:[];$result['test_mode']=true;
            $rewardId=$qualified?'test_reward_'.str_replace('-','',mg_hosted_game_uuid()):null;$rewardStatus=$qualified?'simulated_delivered':null;
            $status=$qualified?'qualified':'completed';
            $pdo->prepare("UPDATE hosted_game_test_runs SET status=?,score=?,qualified=?,result_json=?,simulated_reward_public_id=?,simulated_reward_status=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")
                ->execute([$status,$score,$qualified?1:0,mg_hosted_game_json_encode($result,65536),$rewardId,$rewardStatus,(int)$run['id']]);
            mg_hosted_game_preview_event($pdo,$session,(int)$run['id'],'runtime','run_completed',['qualified'=>$qualified,'score'=>$score,'simulated_reward_id'=>$rewardId,'inventory_consumed'=>false]);
            $updated=mg_hosted_game_preview_run($pdo,$session,$userId,(string)$run['public_id'],false)?:$run;
            return ['qualified'=>$qualified,'reward_issued'=>$qualified,'simulated'=>true,'inventory_consumed'=>false,'run'=>mg_hosted_game_preview_run_payload($updated)];
        }
        if($action==='abandon'){
            $run=mg_hosted_game_preview_run_authorized($pdo,$session,$userId,$input,true);if((string)$run['status']==='started')$pdo->prepare("UPDATE hosted_game_test_runs SET status='abandoned',result_json=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([mg_hosted_game_json_encode(['reason'=>(string)($input['reason']??'player_exit'),'test_mode'=>true],32768),(int)$run['id']]);
            mg_hosted_game_preview_event($pdo,$session,(int)$run['id'],'runtime','run_abandoned',['reason'=>(string)($input['reason']??'player_exit')]);
            return ['abandoned'=>true,'run'=>mg_hosted_game_preview_run_payload(mg_hosted_game_preview_run($pdo,$session,$userId,(string)$run['public_id'],false)?:$run)];
        }
        if($action==='status'){
            $run=mg_hosted_game_preview_run($pdo,$session,$userId,trim((string)($input['run_id']??'')),false);if(!$run)throw new MgHostedGameException('Preview run not found.');return ['run'=>mg_hosted_game_preview_run_payload($run)];
        }
        if($action==='event'||$action==='track'){
            $type=strtolower(trim((string)($input['event_type']??'game.event')));if(preg_match('/^[a-z0-9_.:-]{2,120}$/',$type)!==1)throw new InvalidArgumentException('Invalid preview event type.');$event=is_array($input['event']??null)?$input['event']:[];
            $runId=null;if(!empty($input['run_id']))$runId=(int)mg_hosted_game_preview_run_authorized($pdo,$session,$userId,$input,false)['id'];
            $severity=$type==='runtime_error'?'error':'info';mg_hosted_game_preview_event($pdo,$session,$runId,'sdk',$type,$event,$severity,$action);
            return ['recorded'=>true,'event_type'=>$type,'test_mode'=>true];
        }
        if($action==='state_load'){
            $key=trim((string)($input['key']??'default'));if(preg_match('/^[A-Za-z0-9_.:-]{1,120}$/',$key)!==1)throw new InvalidArgumentException('Invalid preview state key.');$stmt=$pdo->prepare('SELECT state_json,updated_at FROM hosted_game_test_state WHERE session_id=? AND player_user_id=? AND state_key=? LIMIT 1');$stmt->execute([(int)$session['id'],$userId,$key]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return ['key'=>$key,'state'=>$row?json_decode((string)$row['state_json'],true):null,'updated_at'=>$row['updated_at']??null,'test_mode'=>true];
        }
        if($action==='state_save'){
            $key=trim((string)($input['key']??'default'));if(preg_match('/^[A-Za-z0-9_.:-]{1,120}$/',$key)!==1)throw new InvalidArgumentException('Invalid preview state key.');$state=mg_hosted_game_json_encode($input['state']??null,65536);$pdo->prepare('INSERT INTO hosted_game_test_state (session_id,player_user_id,state_key,state_json,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE state_json=VALUES(state_json),updated_at=NOW()')->execute([(int)$session['id'],$userId,$key,$state]);mg_hosted_game_preview_event($pdo,$session,null,'runtime','state_saved',['key'=>$key]);return ['key'=>$key,'saved'=>true,'test_mode'=>true];
        }
        if($action==='score_submit'){
            $score=filter_var($input['score']??null,FILTER_VALIDATE_INT);if($score===false)throw new InvalidArgumentException('An integer preview score is required.');$run=mg_hosted_game_preview_run_authorized($pdo,$session,$userId,$input,false);mg_hosted_game_preview_event($pdo,$session,(int)$run['id'],'sdk','score_submitted',['score'=>(int)$score]);return ['score_id'=>mg_hosted_game_uuid(),'score'=>(int)$score,'test_mode'=>true];
        }
        if($action==='leaderboard'){
            $stmt=$pdo->prepare("SELECT player_user_id,MAX(score) score,MAX(completed_at) achieved_at FROM hosted_game_test_runs WHERE session_id=? AND score IS NOT NULL GROUP BY player_user_id ORDER BY score DESC LIMIT 100");$stmt->execute([(int)$session['id']]);$rank=0;$rows=array_map(static function(array $row)use(&$rank):array{$rank++;return ['rank'=>$rank,'player'=>'Test Player '.strtoupper(substr(hash('sha256',(string)$row['player_user_id']),0,6)),'score'=>(int)$row['score'],'achieved_at'=>$row['achieved_at']];},$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]);return ['leaderboard'=>$rows,'test_mode'=>true];
        }
        throw new InvalidArgumentException('Unsupported preview runtime action.');
    }finally{
        $duration=(int)round((microtime(true)-$started)*1000);if($action!=='events')mg_hosted_game_preview_event($pdo,$session,null,'shell','sdk_request',['action'=>$action,'duration_ms'=>$duration],$duration>=1500?'warning':'debug',$action,$duration);
    }
}

function mg_hosted_game_preview_reset(PDO $pdo,array $session,int $actorUserId): void
{
    $pdo->beginTransaction();
    try{
        $pdo->prepare('DELETE FROM hosted_game_test_events WHERE session_id=?')->execute([(int)$session['id']]);
        $pdo->prepare('DELETE FROM hosted_game_test_runs WHERE session_id=?')->execute([(int)$session['id']]);
        $pdo->prepare('DELETE FROM hosted_game_test_state WHERE session_id=?')->execute([(int)$session['id']]);
        $pdo->prepare("UPDATE hosted_game_test_sessions SET reset_at=NOW(),last_activity_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$session['id']]);
        $pdo->commit();
        mg_audit('hosted_game.preview_reset','hosted_game_release',['game_id'=>(string)$session['game_public_id'],'release_id'=>(string)$session['release_public_id'],'session_id'=>(string)$session['public_id']],$actorUserId);
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}

function mg_hosted_game_preview_asset_path(array $session,string $relativePath): string
{
    $relativePath=mg_hosted_game_standard_safe_path(rawurldecode($relativePath));
    if($relativePath==='')throw new MgHostedGameException('Preview asset not found.');
    $root=mg_hosted_game_storage_path((string)$session['storage_key']);
    $path=realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relativePath));
    if($path===false||!is_file($path)||!str_starts_with($path,$root.DIRECTORY_SEPARATOR))throw new MgHostedGameException('Preview asset not found.');
    return $path;
}
