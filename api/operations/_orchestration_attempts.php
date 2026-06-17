<?php
declare(strict_types=1);

require_once __DIR__ . '/_orchestration_recovery.php';

function mg_operations_system_actor_user_id(PDO $pdo): int
{
    $stmt=$pdo->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id WHERE u.status='active' AND r.slug IN ('super_admin','admin') ORDER BY FIELD(r.slug,'super_admin','admin'),u.id LIMIT 1");
    $id=(int)($stmt->fetchColumn()?:0);
    if($id<1)throw new RuntimeException('No active administrator is available for operational automation.');
    return $id;
}

function mg_operations_ensure_initial_orchestration_attempt(PDO $pdo,string $orchestrationPublicId): void
{
    $root=mg_operations_orchestration_load($pdo,$orchestrationPublicId);
    $exists=$pdo->prepare('SELECT COUNT(*) FROM demand_signal_orchestration_attempts WHERE orchestration_id=?');
    $exists->execute([(int)$root['id']]);
    if((int)$exists->fetchColumn()>0)return;
    $pdo->prepare(
        'INSERT INTO demand_signal_orchestration_attempts (public_id,orchestration_id,attempt_no,strategy_id,strategy_version,team_id,orchestration_type,workflow_run_id,swarm_run_id,status,dispatch_key,input_fingerprint,requested_reason,last_error,started_at,completed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        mg_public_uuid(),(int)$root['id'],1,$root['strategy_id'],$root['strategy_version'],$root['team_id'],$root['orchestration_type'],
        $root['workflow_run_id'],$root['swarm_run_id'],$root['status'],$root['dispatch_key'],$root['input_fingerprint'],
        'Initial demand orchestration attempt.',$root['last_error'],$root['started_at'],$root['completed_at'],$root['created_at'],$root['updated_at'],
    ]);
}

function mg_operations_retry_demand_orchestration_safe(PDO $pdo,int $adminUserId,string $publicId,string $idempotencyKey,string $reason,?callable $failureHook=null): array
{
    mg_operations_ensure_initial_orchestration_attempt($pdo,$publicId);
    $root=mg_operations_orchestration_load($pdo,$publicId);
    $existing=$pdo->prepare('SELECT * FROM demand_signal_orchestration_attempts WHERE orchestration_id=? AND request_idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$root['id'],trim($idempotencyKey)]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC))return $row+['duplicate'=>true,'orchestration_id'=>$root['public_id']];
    return mg_operations_retry_demand_orchestration($pdo,$adminUserId,$publicId,$idempotencyKey,$reason,$failureHook);
}

function mg_operations_sync_latest_orchestration_attempt(PDO $pdo,string $orchestrationPublicId,string $status,?string $error=null): void
{
    if(!in_array($status,['claimed','awaiting_approval','running','completed','failed','review_required'],true))return;
    $stmt=$pdo->prepare('SELECT id FROM demand_signal_orchestrations WHERE public_id=? LIMIT 1');
    $stmt->execute([$orchestrationPublicId]);$rootId=(int)($stmt->fetchColumn()?:0);
    if($rootId<1)return;
    $attempt=$pdo->prepare('SELECT id FROM demand_signal_orchestration_attempts WHERE orchestration_id=? ORDER BY attempt_no DESC LIMIT 1 FOR UPDATE');
    $attempt->execute([$rootId]);$attemptId=(int)($attempt->fetchColumn()?:0);
    if($attemptId<1)return;
    $terminal=in_array($status,['completed','failed','review_required'],true);
    $pdo->prepare('UPDATE demand_signal_orchestration_attempts SET status=?,last_error=?,completed_at=IF(?,COALESCE(completed_at,NOW()),NULL),updated_at=NOW() WHERE id=?')
        ->execute([$status,$error,$terminal?1:0,$attemptId]);
}

function mg_operations_reconcile_next_demand_orchestration(PDO $pdo): array
{
    $result=mg_demand_reconcile_next_orchestration($pdo);
    if(!empty($result['processed'])){
        mg_operations_sync_latest_orchestration_attempt(
            $pdo,
            (string)$result['orchestration_id'],
            (string)$result['status'],
            ($result['status']??'')==='failed'?'Demand orchestration attempt failed.':null
        );
    }
    return $result;
}
