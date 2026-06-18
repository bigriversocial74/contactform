<?php
declare(strict_types=1);

function mg_admin_system_health_notifications(PDO $pdo,array $tables): array
{
    if(empty($tables['notification_delivery_jobs'])){
        return ['available'=>false,'status'=>'critical','message'=>'Notification delivery queue is unavailable.','recent_failures'=>[]];
    }
    $row=$pdo->query(
        "SELECT COUNT(*) total_jobs,
          COALESCE(SUM(status='queued'),0) queued_jobs,
          COALESCE(SUM(status='processing'),0) processing_jobs,
          COALESCE(SUM(status IN ('sent','delivered')),0) successful_jobs,
          COALESCE(SUM(status='failed'),0) failed_jobs,
          COALESCE(SUM(status='suppressed'),0) suppressed_jobs,
          COALESCE(SUM(status='cancelled'),0) cancelled_jobs,
          COALESCE(SUM(status='queued' AND next_attempt_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)),0) overdue_jobs,
          MIN(CASE WHEN status='queued' THEN next_attempt_at END) oldest_queued_at
         FROM notification_delivery_jobs"
    )->fetch(PDO::FETCH_ASSOC)?:[];
    $totals=[];
    foreach($row as $key=>$value){
        $totals[$key]=$key==='oldest_queued_at'?($value!==null?(string)$value:null):(int)$value;
    }
    $failures=$pdo->query(
        "SELECT public_id,channel,attempt_count,failure_code,failure_message,failed_at,updated_at
         FROM notification_delivery_jobs
         WHERE status='failed'
         ORDER BY COALESCE(failed_at,updated_at) DESC,id DESC LIMIT 12"
    )->fetchAll(PDO::FETCH_ASSOC);
    $recent=array_map(static fn(array $item):array=>[
        'id'=>(string)$item['public_id'],
        'channel'=>(string)$item['channel'],
        'attempts'=>(int)$item['attempt_count'],
        'code'=>$item['failure_code']!==null?(string)$item['failure_code']:null,
        'message'=>$item['failure_message']!==null?mb_substr((string)$item['failure_message'],0,240):null,
        'failed_at'=>$item['failed_at']!==null?(string)$item['failed_at']:(string)$item['updated_at'],
    ],$failures);
    $status=(int)($totals['failed_jobs']??0)>0?'critical':((int)($totals['overdue_jobs']??0)>0?'warning':'healthy');
    return array_merge($totals,['available'=>true,'status'=>$status,'recent_failures'=>$recent]);
}
