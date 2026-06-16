<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/subscriptions/_subscriptions.php';

$limit=max(1,min((int)($argv[1]??100),500));
$pdo=mg_db();
$sql="SELECT s.*,p.interval_unit,p.interval_count FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.recovery_status='clear' AND s.status IN ('pending_payment','trialing','active','past_due','cancel_pending') AND s.next_billing_at IS NOT NULL AND s.next_billing_at<=NOW() ORDER BY s.next_billing_at,s.id LIMIT ".(int)$limit;
$stmt=$pdo->query($sql);
$subscriptions=$stmt->fetchAll(PDO::FETCH_ASSOC);
$summary=['scanned'=>0,'succeeded'=>0,'processing'=>0,'failed'=>0,'canceled'=>0];
foreach($subscriptions as $subscription){
    $summary['scanned']++;
    $pdo->beginTransaction();
    try{
        $lock=$pdo->prepare("SELECT s.*,p.interval_unit,p.interval_count FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.id=? LIMIT 1 FOR UPDATE");
        $lock->execute([(int)$subscription['id']]);$current=$lock->fetch(PDO::FETCH_ASSOC);
        if(!$current||(string)($current['recovery_status']??'clear')!=='clear'||$current['next_billing_at']===null||strtotime((string)$current['next_billing_at'])>time()){$pdo->rollBack();continue;}
        if((string)$current['status']==='cancel_pending'&&(int)$current['cancel_at_period_end']===1){
            $pdo->prepare("UPDATE subscriptions SET status='canceled',canceled_at=NOW(),next_billing_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$current['id']]);
            mg_subscription_event($pdo,(int)$current['id'],'subscription.canceled','cancel_pending','canceled',null,'period_ended',[]);
            $pdo->commit();$summary['canceled']++;continue;
        }
        try{
            $attempt=mg_subscription_attempt($pdo,$current);
            $pdo->commit();
            $summary[(string)$attempt['status']==='succeeded'?'succeeded':'processing']++;
        }catch(Throwable $paymentError){
            if($pdo->inTransaction()){
                mg_subscription_mark_failure($pdo,$current,$paymentError->getMessage());
                $pdo->commit();
            }
            $summary['failed']++;
        }
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        $summary['failed']++;
        fwrite(STDERR,'Subscription '.$subscription['public_id'].' failed: '.$error->getMessage().PHP_EOL);
    }
}
fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
