<?php
declare(strict_types=1);

require_once __DIR__.'/_recovery.php';
require_once __DIR__.'/_notifications.php';
require_once dirname(__DIR__).'/subscriptions/_recovery.php';

function mg_tip_route_payment_event(PDO $pdo,string $provider,array $event,?callable $subscriptionRecoveryHook=null): array
{
    $type=trim((string)($event['type']??''));
    if(mg_tip_is_recovery_event($type)){
        $result=mg_tip_process_recovery_event($pdo,$provider,$event);
        if(empty($result['duplicate'])){
            $subscriptionRecovery=mg_subscription_reconcile_tip_recovery($pdo,$result,$subscriptionRecoveryHook);
            if($subscriptionRecovery!==null)$result['subscription_recovery']=$subscriptionRecovery;
        }
        if(empty($result['duplicate'])&&!empty($result['recovery_type'])){
            $result['alert_ids']=mg_tip_notify_recovery($pdo,$result,$result);
        }
        return $result;
    }
    $result=mg_tip_process_payment_event_result($pdo,$provider,$event);
    if((string)($result['status']??'')==='posted'&&empty($result['duplicate'])){
        $result['alert_id']=mg_tip_notify_recipient($pdo,$result);
    }
    return $result;
}
