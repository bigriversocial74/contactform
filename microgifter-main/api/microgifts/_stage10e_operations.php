<?php
declare(strict_types=1);

require_once __DIR__ . '/_claim_operations.php';

function mg_claim_dashboard(PDO $pdo,int $merchantUserId,int $days=30): array
{
    $days=max(1,min($days,365));
    $summary=$pdo->prepare("SELECT COUNT(*) attempts,SUM(result='approved') approved,SUM(result<>'approved') rejected,COUNT(DISTINCT location_id) locations,COUNT(DISTINCT actor_user_id) actors FROM microgift_claim_attempts WHERE merchant_user_id=? AND attempted_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY)");
    $summary->execute([$merchantUserId]);
    $locations=$pdo->prepare("SELECT l.public_id location_id,l.name,COUNT(a.id) attempts,SUM(a.result='approved') approved,SUM(a.result<>'approved') rejected FROM microgift_claim_attempts a LEFT JOIN merchant_locations l ON l.id=a.location_id WHERE a.merchant_user_id=? AND a.attempted_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY) GROUP BY a.location_id,l.public_id,l.name ORDER BY attempts DESC LIMIT 25");
    $locations->execute([$merchantUserId]);
    $results=$pdo->prepare("SELECT result,COUNT(*) total FROM microgift_claim_attempts WHERE merchant_user_id=? AND attempted_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY) GROUP BY result ORDER BY total DESC");
    $results->execute([$merchantUserId]);
    $escalations=$pdo->prepare("SELECT status,severity,COUNT(*) total FROM microgift_claim_escalations WHERE merchant_user_id=? GROUP BY status,severity ORDER BY status,severity");
    $escalations->execute([$merchantUserId]);
    $row=$summary->fetch(PDO::FETCH_ASSOC)?:[];
    $attempts=(int)($row['attempts']??0);
    $approved=(int)($row['approved']??0);
    return ['period_days'=>$days,'summary'=>$row+['success_rate'=>$attempts?round($approved/$attempts,4):0.0],'locations'=>$locations->fetchAll(PDO::FETCH_ASSOC),'results'=>$results->fetchAll(PDO::FETCH_ASSOC),'escalations'=>$escalations->fetchAll(PDO::FETCH_ASSOC)];
}

function mg_outbox_claim_batch(PDO $pdo,int $limit=50): array
{
    $limit=max(1,min($limit,200));
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->query("SELECT * FROM microgift_operational_outbox WHERE status IN ('pending','failed') AND available_at<=NOW() ORDER BY id ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED");
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rows){
            $ids=array_map(static fn(array $row):int=>(int)$row['id'],$rows);
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $pdo->prepare("UPDATE microgift_operational_outbox SET status='processing',locked_at=NOW(),attempt_count=attempt_count+1,updated_at=NOW() WHERE id IN ({$marks})")->execute($ids);
        }
        $pdo->commit();
        return $rows;
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
}

function mg_outbox_complete(PDO $pdo,int $id,bool $delivered,?string $error=null,int $maxAttempts=8): void
{
    if($delivered){
        $pdo->prepare("UPDATE microgift_operational_outbox SET status='delivered',delivered_at=NOW(),locked_at=NULL,last_error=NULL,updated_at=NOW() WHERE id=?")->execute([$id]);
        return;
    }
    $stmt=$pdo->prepare('SELECT attempt_count FROM microgift_operational_outbox WHERE id=?');
    $stmt->execute([$id]);
    $attempts=(int)$stmt->fetchColumn();
    $status=$attempts>=$maxAttempts?'dead':'failed';
    $delay=min(3600,max(30,2**min($attempts,10)));
    $pdo->prepare("UPDATE microgift_operational_outbox SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND),locked_at=NULL,last_error=?,updated_at=NOW() WHERE id=?")->execute([$status,$delay,substr((string)$error,0,500),$id]);
}

function mg_run_retention(PDO $pdo,int $securityDays=365,int $rateDays=30,int $outboxDays=90): array
{
    $securityDays=max(30,$securityDays);
    $rateDays=max(1,$rateDays);
    $outboxDays=max(7,$outboxDays);
    $publicId=mg_microgift_uuid();
    $pdo->prepare("INSERT INTO microgift_retention_runs (public_id,job_name,status,cutoff_at,started_at,created_at) VALUES (?,'stage10f_retention','running',DATE_SUB(NOW(),INTERVAL ? DAY),NOW(),NOW())")->execute([$publicId,$securityDays]);
    $runId=(int)$pdo->lastInsertId();
    $affected=0;
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("DELETE FROM microgift_claim_rate_limits WHERE updated_at<DATE_SUB(NOW(),INTERVAL ? DAY) AND (blocked_until IS NULL OR blocked_until<NOW())");
        $stmt->execute([$rateDays]);
        $affected+=$stmt->rowCount();
        $stmt=$pdo->prepare("DELETE FROM microgift_operational_outbox WHERE status='delivered' AND delivered_at<DATE_SUB(NOW(),INTERVAL ? DAY)");
        $stmt->execute([$outboxDays]);
        $affected+=$stmt->rowCount();
        $stmt=$pdo->prepare("DELETE FROM microgift_claim_attempt_security WHERE expires_at IS NOT NULL AND expires_at<NOW()");
        $stmt->execute();
        $affected+=$stmt->rowCount();
        $pdo->commit();
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        $pdo->prepare("UPDATE microgift_retention_runs SET status='failed',details_json=?,completed_at=NOW() WHERE id=?")->execute([mg_microgift_json(['error'=>substr($error->getMessage(),0,300)]),$runId]);
        throw $error;
    }
    $pdo->prepare("UPDATE microgift_retention_runs SET status='completed',affected_rows=?,details_json=?,completed_at=NOW() WHERE id=?")->execute([$affected,mg_microgift_json(['security_days'=>$securityDays,'rate_days'=>$rateDays,'outbox_days'=>$outboxDays]),$runId]);
    return ['run_id'=>$publicId,'affected_rows'=>$affected,'status'=>'completed'];
}
