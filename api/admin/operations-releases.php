<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/operations/_operations.php';
$user=mg_require_permission('operations.releases.manage');
$pdo=mg_db();
if($_SERVER['REQUEST_METHOD']==='GET'){
    $stmt=$pdo->query('SELECT public_id,release_version,git_commit_sha,environment,status,validation_summary_json,artifact_manifest_json,rollback_plan_json,approved_at,deployment_started_at,deployed_at,rolled_back_at,failure_message,created_at,updated_at FROM deployment_releases ORDER BY id DESC LIMIT 100');
    mg_ok(['releases'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);$action=trim((string)($input['action']??'create'));
$pdo->beginTransaction();
try{
    if($action==='create'){$release=mg_operations_create_release($pdo,(int)$user['id'],$input);$result=['release'=>$release];}
    else{
        $stmt=$pdo->prepare('SELECT * FROM deployment_releases WHERE public_id=? LIMIT 1 FOR UPDATE');$stmt->execute([trim((string)($input['release_id']??''))]);$release=$stmt->fetch(PDO::FETCH_ASSOC);if(!$release)throw new RuntimeException('Release not found.');
        if($action==='gate'){
            mg_operations_release_gate($pdo,$release,trim((string)($input['gate_key']??'')),trim((string)($input['gate_status']??'')),(int)$user['id'],(array)($input['evidence']??[]),(string)($input['failure_message']??''));$result=['release_id'=>$release['public_id'],'gate_key'=>$input['gate_key'],'status'=>$input['gate_status']];
        }elseif($action==='approve'){
            if((string)$release['status']!=='planned'&&(string)$release['status']!=='validating')throw new RuntimeException('Release is not available for approval.');
            if(!mg_operations_release_can_approve($pdo,(int)$release['id']))throw new RuntimeException('Required release gates have not passed.');
            $pdo->prepare("UPDATE deployment_releases SET status='approved',approved_by_user_id=?,approved_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$user['id'],(int)$release['id']]);$result=['release_id'=>$release['public_id'],'status'=>'approved'];
        }elseif($action==='deploy'){
            if((string)$release['status']!=='approved')throw new RuntimeException('Only an approved release can deploy.');
            $pdo->prepare("UPDATE deployment_releases SET status='deployed',deployed_by_user_id=?,deployment_started_at=COALESCE(deployment_started_at,NOW()),deployed_at=NOW(),artifact_manifest_json=?,updated_at=NOW() WHERE id=?")->execute([(int)$user['id'],json_encode($input['artifact_manifest']??[],JSON_THROW_ON_ERROR),(int)$release['id']]);$result=['release_id'=>$release['public_id'],'status'=>'deployed'];
        }elseif($action==='rollback'){
            if(!in_array((string)$release['status'],['deployed','failed'],true))throw new RuntimeException('Release is not available for rollback.');
            $pdo->prepare("UPDATE deployment_releases SET status='rolled_back',rolled_back_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$release['id']]);$result=['release_id'=>$release['public_id'],'status'=>'rolled_back'];
        }else throw new InvalidArgumentException('Invalid release action.');
    }
    $pdo->commit();mg_audit('operations.release_'.$action,'deployment_release',$result,(int)$user['id']);mg_ok($result,'Release updated.',$action==='create'?201:200);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update release.',500);}
