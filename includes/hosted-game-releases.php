<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-games.php';
require_once __DIR__ . '/hosted-game-standard-v1.php';

function mg_hosted_game_release_schema_ready(PDO $pdo): bool
{
    return mg_hosted_game_table_exists($pdo, 'hosted_game_releases')
        && mg_hosted_game_table_exists($pdo, 'hosted_game_test_sessions')
        && mg_hosted_game_table_exists($pdo, 'hosted_game_test_runs')
        && mg_hosted_game_table_exists($pdo, 'hosted_game_test_events')
        && mg_hosted_game_table_exists($pdo, 'hosted_game_test_state');
}

function mg_hosted_game_release_by_public_id(PDO $pdo, int $gameId, string $releasePublicId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT hgr.*,u.email AS uploaded_by_email,COALESCE(NULLIF(u.display_name,\'\'),NULLIF(u.full_name,\'\'),u.email) AS uploaded_by_name,
                   au.email AS activated_by_email,COALESCE(NULLIF(au.display_name,\'\'),NULLIF(au.full_name,\'\'),au.email) AS activated_by_name
            FROM hosted_game_releases hgr
            INNER JOIN users u ON u.id=hgr.uploaded_by_user_id
            LEFT JOIN users au ON au.id=hgr.activated_by_user_id
            WHERE hgr.game_id=? AND hgr.public_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$gameId,$releasePublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_hosted_game_release_rows(PDO $pdo, int $gameId): array
{
    $stmt = $pdo->prepare(
        "SELECT hgr.*,u.email AS uploaded_by_email,COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) AS uploaded_by_name,
                au.email AS activated_by_email,COALESCE(NULLIF(au.display_name,''),NULLIF(au.full_name,''),au.email) AS activated_by_name,
                COALESCE(ts.sessions,0) AS test_sessions,ts.last_tested_at
         FROM hosted_game_releases hgr
         INNER JOIN users u ON u.id=hgr.uploaded_by_user_id
         LEFT JOIN users au ON au.id=hgr.activated_by_user_id
         LEFT JOIN (
             SELECT release_id,COUNT(*) sessions,MAX(created_at) last_tested_at
             FROM hosted_game_test_sessions GROUP BY release_id
         ) ts ON ts.release_id=hgr.id
         WHERE hgr.game_id=?
         ORDER BY hgr.version_number DESC,hgr.id DESC"
    );
    $stmt->execute([$gameId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_hosted_game_release_payload(array $row, string $gamePublicId): array
{
    $manifest = mg_hosted_game_json_decode($row['manifest_json'] ?? null);
    return [
        'id'=>(string)$row['public_id'],
        'game_id'=>$gamePublicId,
        'version'=>(int)$row['version_number'],
        'status'=>(string)$row['status'],
        'is_active'=>(string)$row['status']==='active',
        'original_filename'=>(string)$row['original_filename'],
        'release_notes'=>(string)($row['release_notes'] ?? ''),
        'entry_file'=>(string)($row['entry_file'] ?? $manifest['entry'] ?? 'index.html'),
        'checksum'=>(string)$row['package_checksum'],
        'file_count'=>(int)$row['file_count'],
        'extracted_bytes'=>(int)$row['extracted_bytes'],
        'package_zip_bytes'=>(int)($row['package_zip_bytes'] ?? 0),
        'package_download_available'=>trim((string)($row['package_zip_storage_key'] ?? ''))!=='',
        'manifest_schema'=>$row['manifest_schema'] ?? ($manifest['schema'] ?? null),
        'manifest_version'=>$row['manifest_version'] ?? ($manifest['version'] ?? null),
        'sdk_version'=>$row['sdk_version'] ?? null,
        'manifest'=>$manifest,
        'standard'=>$manifest['standard'] ?? null,
        'validation'=>[
            'status'=>(string)($row['validation_status'] ?? 'pending'),
            'result'=>mg_hosted_game_json_decode($row['validation_json'] ?? null),
            'checked_at'=>$row['validated_at'] ?? null,
        ],
        'health'=>[
            'status'=>(string)($row['health_status'] ?? 'not_run'),
            'result'=>mg_hosted_game_json_decode($row['health_json'] ?? null),
            'checked_at'=>$row['health_checked_at'] ?? null,
        ],
        'uploaded_by'=>[
            'user_id'=>(int)$row['uploaded_by_user_id'],
            'name'=>(string)($row['uploaded_by_name'] ?? $row['uploaded_by_email'] ?? 'User'),
            'email'=>(string)($row['uploaded_by_email'] ?? ''),
        ],
        'activated_by'=>!empty($row['activated_by_user_id']) ? [
            'user_id'=>(int)$row['activated_by_user_id'],
            'name'=>(string)($row['activated_by_name'] ?? $row['activated_by_email'] ?? 'User'),
            'email'=>(string)($row['activated_by_email'] ?? ''),
        ] : null,
        'test_sessions'=>(int)($row['test_sessions'] ?? 0),
        'last_tested_at'=>$row['last_tested_at'] ?? null,
        'failure_message'=>$row['failure_message'] ?? null,
        'activated_at'=>$row['activated_at'] ?? null,
        'archived_at'=>$row['archived_at'] ?? null,
        'created_at'=>$row['created_at'] ?? null,
        'updated_at'=>$row['updated_at'] ?? null,
    ];
}

function mg_hosted_game_release_history(PDO $pdo, array $game): array
{
    return array_map(
        static fn(array $row): array => mg_hosted_game_release_payload($row,(string)$game['public_id']),
        mg_hosted_game_release_rows($pdo,(int)$game['id'])
    );
}

function mg_hosted_game_release_update_notes(PDO $pdo, array $game, string $releasePublicId, string $notes, int $actorUserId): array
{
    if (mb_strlen($notes)>10000) throw new InvalidArgumentException('Release notes may not exceed 10,000 characters.');
    $release=mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false);
    if(!$release) throw new MgHostedGameException('Hosted game release not found.');
    $pdo->prepare('UPDATE hosted_game_releases SET release_notes=?,updated_at=NOW() WHERE id=?')
        ->execute([trim($notes)!==''?trim($notes):null,(int)$release['id']]);
    mg_audit('hosted_game.release_notes_updated','hosted_game_release',['game_id'=>(string)$game['public_id'],'release_id'=>$releasePublicId],$actorUserId);
    return mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false) ?: $release;
}

function mg_hosted_game_release_health_check(PDO $pdo, array $game, string $releasePublicId, int $actorUserId): array
{
    $release=mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false);
    if(!$release) throw new MgHostedGameException('Hosted game release not found.');
    $checks=[];
    $errors=[];
    $warnings=[];
    try{
        $root=mg_hosted_game_storage_path((string)$release['storage_key']);
        $checks['release_directory']=['ok'=>is_dir($root),'path'=>'private'];
        $entry=trim((string)($release['entry_file']??'index.html'));
        $entryPath=realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$entry));
        $entryOk=$entryPath!==false&&is_file($entryPath)&&str_starts_with($entryPath,$root.DIRECTORY_SEPARATOR)&&is_readable($entryPath);
        $checks['entry_file']=['ok'=>$entryOk,'entry'=>$entry];
        if(!$entryOk)$errors[]='The release entry file is missing or unreadable.';
        elseif((filesize($entryPath)?:0)>20971520)$errors[]='The release entry file exceeds the 20 MB runtime limit.';
        $manifest=mg_hosted_game_json_decode($release['manifest_json']??null);
        $normalized=mg_hosted_game_standard_normalize_manifest($manifest,$game,[],$entry);
        $checks['manifest']=['ok'=>true,'schema'=>$normalized['schema'],'version'=>$normalized['version'],'compliance'=>$normalized['standard']['compliance']];
        if((string)$normalized['standard']['compliance']!=='standard')$warnings[]='This release uses legacy manifest compatibility mode.';
        $zipKey=trim((string)($release['package_zip_storage_key']??''));
        $zipOk=false;
        if($zipKey!==''){
            try{
                $zipPath=mg_hosted_game_storage_path($zipKey);
                $zipOk=is_file($zipPath)&&hash_file('sha256',$zipPath)===(string)$release['package_checksum'];
            }catch(Throwable){$zipOk=false;}
        }
        $checks['original_zip']=['ok'=>$zipOk,'preserved'=>$zipKey!==''];
        if(!$zipOk)$warnings[]='The original ZIP is unavailable or its checksum could not be confirmed.';
        if(mg_hosted_game_table_exists($pdo,'hosted_game_diagnostic_groups')){
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM hosted_game_diagnostic_groups WHERE game_id=? AND release_public_id=? AND status='open' AND severity IN ('error','critical')");
            $stmt->execute([(int)$game['id'],$releasePublicId]);
            $critical=(int)$stmt->fetchColumn();
            $checks['open_critical_diagnostics']=['ok'=>$critical===0,'count'=>$critical];
            if($critical>0)$warnings[]='Open critical diagnostics exist for this release.';
        }
    }catch(Throwable $error){
        $errors[]=$error->getMessage();
    }
    $status=$errors!==[]?'failed':($warnings!==[]?'warning':'passed');
    $result=['checks'=>$checks,'warnings'=>$warnings,'errors'=>$errors,'checked_by'=>$actorUserId,'checked_at'=>gmdate('c')];
    $pdo->prepare('UPDATE hosted_game_releases SET health_status=?,health_json=?,health_checked_at=NOW(),failure_message=?,updated_at=NOW() WHERE id=?')
        ->execute([$status,mg_hosted_game_json_encode($result,65536),$errors!==[]?mb_substr(implode(' ',$errors),0,500):null,(int)$release['id']]);
    mg_audit('hosted_game.release_health_checked','hosted_game_release',['game_id'=>(string)$game['public_id'],'release_id'=>$releasePublicId,'status'=>$status],$actorUserId);
    return $result+['status'=>$status];
}

function mg_hosted_game_release_activate(PDO $pdo, array $game, string $releasePublicId, int $actorUserId, bool $rollback=false): array
{
    $pdo->beginTransaction();
    try{
        $lockedGame=mg_hosted_game_by_public_id($pdo,(string)$game['public_id'],true);
        if(!$lockedGame)throw new MgHostedGameException('Hosted game not found.');
        if((string)$lockedGame['status']==='archived')throw new MgHostedGameException('Archived games cannot change releases.');
        $release=mg_hosted_game_release_by_public_id($pdo,(int)$lockedGame['id'],$releasePublicId,true);
        if(!$release)throw new MgHostedGameException('Hosted game release not found.');
        if((string)$release['status']==='failed')throw new MgHostedGameException('A failed release cannot be activated.');
        if(!in_array((string)$release['validation_status'],['passed','warning'],true))throw new MgHostedGameException('The release must pass validation before activation.');
        if(!in_array((string)$release['health_status'],['passed','warning'],true))throw new MgHostedGameException('Run and pass the release health check before activation.');
        if((string)($lockedGame['current_release_public_id']??'')===$releasePublicId){
            $pdo->commit();
            return $release;
        }
        $pdo->prepare("UPDATE hosted_game_releases SET status='archived',archived_at=NOW(),updated_at=NOW() WHERE game_id=? AND status='active'")
            ->execute([(int)$lockedGame['id']]);
        $pdo->prepare("UPDATE hosted_game_releases SET status='active',activated_by_user_id=?,activated_at=NOW(),archived_at=NULL,failure_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$actorUserId,(int)$release['id']]);
        $entry=trim((string)($release['entry_file']??'index.html'))?:'index.html';
        $pdo->prepare('UPDATE hosted_games SET current_release_public_id=?,entry_file=?,updated_by_user_id=?,updated_at=NOW() WHERE id=?')
            ->execute([$releasePublicId,$entry,$actorUserId,(int)$lockedGame['id']]);
        $pdo->commit();
        mg_audit($rollback?'hosted_game.release_rolled_back':'hosted_game.release_activated','hosted_game_release',[
            'game_id'=>(string)$game['public_id'],'release_id'=>$releasePublicId,'version'=>(int)$release['version_number']
        ],$actorUserId);
        return mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false)?:$release;
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
}

function mg_hosted_game_release_archive(PDO $pdo,array $game,string $releasePublicId,int $actorUserId): array
{
    $release=mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false);
    if(!$release)throw new MgHostedGameException('Hosted game release not found.');
    if((string)$release['status']==='active'||(string)($game['current_release_public_id']??'')===$releasePublicId){
        throw new MgHostedGameException('The active release cannot be archived or deleted. Activate another release first.');
    }
    $pdo->prepare("UPDATE hosted_game_releases SET status='archived',archived_at=COALESCE(archived_at,NOW()),updated_at=NOW() WHERE id=?")
        ->execute([(int)$release['id']]);
    mg_audit('hosted_game.release_archived','hosted_game_release',['game_id'=>(string)$game['public_id'],'release_id'=>$releasePublicId],$actorUserId);
    return mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$releasePublicId,false)?:$release;
}

function mg_hosted_game_release_flatten(mixed $value,string $prefix=''): array
{
    if(!is_array($value))return [$prefix=>$value];
    $flat=[];
    foreach($value as $key=>$item){
        $path=$prefix===''?(string)$key:$prefix.'.'.$key;
        $flat+=mg_hosted_game_release_flatten($item,$path);
    }
    return $flat;
}

function mg_hosted_game_release_compare(PDO $pdo,array $game,string $leftId,string $rightId): array
{
    if($leftId===$rightId)throw new InvalidArgumentException('Select two different releases to compare.');
    $left=mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$leftId,false);
    $right=mg_hosted_game_release_by_public_id($pdo,(int)$game['id'],$rightId,false);
    if(!$left||!$right)throw new MgHostedGameException('One or both releases could not be found.');
    $leftFlat=mg_hosted_game_release_flatten(mg_hosted_game_json_decode($left['manifest_json']??null));
    $rightFlat=mg_hosted_game_release_flatten(mg_hosted_game_json_decode($right['manifest_json']??null));
    $paths=array_values(array_unique(array_merge(array_keys($leftFlat),array_keys($rightFlat))));
    sort($paths,SORT_STRING);
    $changes=[];
    foreach($paths as $path){
        $hasLeft=array_key_exists($path,$leftFlat);$hasRight=array_key_exists($path,$rightFlat);
        $leftValue=$hasLeft?$leftFlat[$path]:null;$rightValue=$hasRight?$rightFlat[$path]:null;
        if($hasLeft&&$hasRight&&$leftValue===$rightValue)continue;
        $changes[]=['path'=>$path,'type'=>!$hasLeft?'added':(!$hasRight?'removed':'changed'),'left'=>$leftValue,'right'=>$rightValue];
    }
    return [
        'left'=>mg_hosted_game_release_payload($left,(string)$game['public_id']),
        'right'=>mg_hosted_game_release_payload($right,(string)$game['public_id']),
        'changes'=>$changes,
        'change_count'=>count($changes),
    ];
}

function mg_hosted_game_release_zip_path(array $release): string
{
    $key=trim((string)($release['package_zip_storage_key']??''));
    if($key==='')throw new MgHostedGameException('The original uploaded ZIP is not available for this release.');
    $path=mg_hosted_game_storage_path($key);
    if(!is_file($path)||!is_readable($path))throw new MgHostedGameException('The original uploaded ZIP is unavailable.');
    return $path;
}
