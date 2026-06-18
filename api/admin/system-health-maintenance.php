<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health_access.php';

mg_require_method('POST');
$user=mg_admin_system_health_require_user(true);
$input=mg_input();
mg_require_csrf_for_write($input);
$action=strtolower(trim((string)($input['action']??'')));
$pdo=mg_db();

function mg_admin_health_record(PDO $pdo,string $key,string $status,string $summary,array $details=[]): void
{
    if(!in_array($status,['pass','warn','fail'],true))$status='warn';
    $pdo->prepare(
        "INSERT INTO operational_check_results
         (public_id,check_key,check_scope,status,summary,details_json,checked_at,expires_at,created_at)
         VALUES (?,?,'admin_system_health',?,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),UTC_TIMESTAMP())"
    )->execute([
        mg_public_uuid(),
        mb_substr($key,0,120),
        $status,
        mb_substr($summary,0,500),
        $details!==[]?json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,
    ]);
}

try{
    if($action==='verify_storage'){
        $result=mg_storage_assert_ready(false,true);
        $message='Persistent storage verification passed.';
        mg_admin_health_record($pdo,'storage.persistent_volume','pass',$message,[
            'driver'=>$result['driver'],'persistent'=>$result['persistent'],'writable'=>$result['writable'],
        ]);
        mg_audit('admin.system_health.storage_verified','storage',['driver'=>$result['driver']],(int)$user['id']);
        mg_ok(['action'=>$action,'message'=>$message],$message);
    }

    if($action==='retry_notifications'){
        $pdo->beginTransaction();
        $ids=$pdo->query(
            "SELECT id FROM notification_delivery_jobs
             WHERE status='failed' AND attempt_count<5
               AND COALESCE(failed_at,updated_at)>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)
             ORDER BY COALESCE(failed_at,updated_at) ASC,id ASC
             LIMIT 100 FOR UPDATE"
        )->fetchAll(PDO::FETCH_COLUMN);
        if($ids!==[]){
            $pdo->prepare(
                "UPDATE notification_delivery_jobs
                 SET status='queued',next_attempt_at=UTC_TIMESTAMP(),failed_at=NULL,
                     failure_code=NULL,failure_message=NULL,updated_at=UTC_TIMESTAMP()
                 WHERE id IN (".implode(',',array_fill(0,count($ids),'?')).")"
            )->execute(array_map('intval',$ids));
        }
        $pdo->commit();
        $count=count($ids);
        $message=$count>0?"Requeued {$count} failed notification deliveries.":'No eligible failed notification deliveries were found.';
        mg_admin_health_record($pdo,'notifications.failed_retry',$count>0?'warn':'pass',$message,['requeued'=>$count]);
        mg_audit('admin.system_health.notifications_retried','notification_delivery_job',['count'=>$count],(int)$user['id']);
        mg_ok(['action'=>$action,'message'=>$message,'affected'=>$count],$message);
    }

    mg_fail('Unknown maintenance action.',422);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','admin.system_health.maintenance_failed','System health maintenance failed.',[
        'action'=>$action,'exception_class'=>$error::class,
    ],(int)$user['id']);
    mg_fail('Unable to complete the maintenance action.',500);
}
