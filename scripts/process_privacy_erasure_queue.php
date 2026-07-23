<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR,"This worker is CLI-only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/security.php';
require_once dirname(__DIR__) . '/includes/privacy/account-erasure.php';

$options=getopt('', ['request-id::','limit::','force','dry-run']);
$requestId=max(0,(int)($options['request-id']??0));
$limit=max(1,min(200,(int)($options['limit']??25)));
$force=array_key_exists('force',$options);
$dryRun=array_key_exists('dry-run',$options);
$pdo=mg_db();

$where=['pr.request_type="delete"','pr.user_id IS NOT NULL','pr.status IN ("approved","restricted","blocked_by_hold","partially_completed")'];
$params=[];
if($requestId>0){$where[]='pr.id=?';$params[]=$requestId;}
elseif(!$force){$where[]='COALESCE(pr.grace_ends_at,pr.response_due_at)<=NOW()';}
$sql='SELECT pr.id,pr.public_id,pr.status,pr.user_id,COALESCE(pr.grace_ends_at,pr.response_due_at) AS due_at FROM privacy_requests pr WHERE '.implode(' AND ',$where).' ORDER BY due_at,pr.id LIMIT '.$limit;
$stmt=$pdo->prepare($sql);$stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];

printf("Privacy erasure worker: %d request(s), force=%s, dry_run=%s\n",count($items),$force?'yes':'no',$dryRun?'yes':'no');
$completed=0;$blocked=0;$notDue=0;$failed=0;
foreach($items as $item){
    printf("- #%d %s status=%s due=%s\n",(int)$item['id'],$item['public_id'],$item['status'],$item['due_at']??'—');
    if($dryRun)continue;
    try{
        $pdo->beginTransaction();
        $result=mg_privacy_finalize_request($pdo,(int)$item['id'],null,$force);
        $pdo->commit();
        $status=(string)($result['status']??'unknown');
        if($status==='completed'){$completed++;}
        elseif($status==='blocked_by_hold'){$blocked++;}
        elseif(!empty($result['not_due'])){$notDue++;}
        printf("  result=%s%s\n",$status,!empty($result['receipt_hash'])?' receipt='.$result['receipt_hash']:'');
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        $failed++;
        mg_security_log('error','privacy.worker.failed','Privacy erasure queue item failed.',['request_id'=>(int)$item['id'],'exception_class'=>$error::class,'exception_message'=>$error->getMessage()]);
        fwrite(STDERR,"  failed: {$error->getMessage()}\n");
    }
}
printf("Completed=%d Blocked=%d NotDue=%d Failed=%d\n",$completed,$blocked,$notDue,$failed);
exit($failed>0?1:0);
