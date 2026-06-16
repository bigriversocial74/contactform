<?php
declare(strict_types=1);
require_once __DIR__.'/_webhook_intake.php';
require_once dirname(__DIR__).'/ops/_alerts.php';
$delivery=dirname(__DIR__).'/communications/_delivery.php';if(is_file($delivery))require_once $delivery;
$disputes=dirname(__DIR__).'/payments/_disputes.php';if(is_file($disputes))require_once $disputes;

final class MgProviderDispatchException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409){parent::__construct($message);}
}

function mg_provider_dispatch_install(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS provider_dispatch_routes (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,provider_key VARCHAR(80) NOT NULL,event_type VARCHAR(140) NOT NULL,domain_key VARCHAR(80) NOT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_provider_dispatch_route(provider_key,event_type)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $routes=[['stripe','charge.dispute.created','payment_dispute'],['stripe','payment.dispute.created','payment_dispute'],['stripe','dispute.opened','payment_dispute'],['stripe','charge.dispute.closed.won','payment_dispute'],['stripe','payment.dispute.won','payment_dispute'],['stripe','dispute.won','payment_dispute'],['stripe','charge.dispute.closed.lost','payment_dispute'],['stripe','payment.dispute.lost','payment_dispute'],['stripe','dispute.lost','payment_dispute'],['sendgrid','email.delivered','delivery_callback'],['sendgrid','email.bounced','delivery_callback'],['postmark','email.delivered','delivery_callback'],['stripe','payout.paid','payout_callback'],['stripe','payout.failed','payout_callback']];
    $stmt=$pdo->prepare("INSERT INTO provider_dispatch_routes (provider_key,event_type,domain_key,status,created_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE domain_key=VALUES(domain_key),status=VALUES(status)");
    foreach($routes as $r)$stmt->execute([$r[0],$r[1],$r[2],'active']);
}
function mg_provider_dispatch_ensure(PDO $pdo): void{if(!$pdo->inTransaction())mg_provider_dispatch_install($pdo);}function mg_provider_dispatch_scalar(PDO $pdo,string $sql,array $p=[]): mixed{$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchColumn();}
function mg_provider_dispatch_route(PDO $pdo,string $provider,string $type): ?string{mg_provider_dispatch_ensure($pdo);$s=$pdo->prepare("SELECT domain_key FROM provider_dispatch_routes WHERE provider_key=? AND event_type=? AND status='active' LIMIT 1");$s->execute([$provider,$type]);$v=$s->fetchColumn();return is_string($v)&&$v!==''?$v:null;}
function mg_provider_dispatch_alert(PDO $pdo,string $key,string $sourceType,string $sourceId,string $severity,string $title,string $body): array{return mg_ops_alert_upsert($pdo,['alert_key'=>$key,'source_type'=>$sourceType,'source_id'=>$sourceId,'severity'=>$severity,'title'=>$title,'body'=>$body]);}
function mg_provider_dispatch(PDO $pdo,string $provider,string $type,array $payload,array $handlers=[]): array
{
    $domain=mg_provider_dispatch_route($pdo,$provider,$type);$eventId=(string)($payload['id']??$payload['event_id']??$payload['provider_event_id']??sha1(mg_webhook_json($payload)));
    if($domain===null){$alert=mg_provider_dispatch_alert($pdo,'provider-dispatch:unknown:'.$provider.':'.$type.':'.$eventId,'provider_event',$provider.':'.$eventId,'warning','Unknown provider event requires review','No active route exists for '.$provider.' '.$type.'.');return ['domain'=>'ops_review','status'=>'review','alert_id'=>$alert['alert_id']];}
    try{
        if(isset($handlers[$domain])&&is_callable($handlers[$domain])){$result=$handlers[$domain]($pdo,$provider,$type,$payload);return ['domain'=>$domain,'status'=>'processed','dispatch_key'=>$domain.':'.$eventId,'result'=>$result];}
        if($domain==='delivery_callback'&&function_exists('mg_delivery_process_callback')){$result=mg_delivery_process_callback($pdo,$provider,$eventId,$type,$payload);return ['domain'=>$domain,'status'=>'processed','dispatch_key'=>'delivery:'.$eventId,'result'=>$result];}
        if($domain==='payment_dispute'&&function_exists('mg_dispute_process_webhook')){throw new MgProviderDispatchException('Payment dispute route requires endpoint event envelope.',422);}
        if($domain==='payout_callback'){$alert=mg_provider_dispatch_alert($pdo,'provider-dispatch:payout:'.$provider.':'.$eventId,'provider_payout_event',$provider.':'.$eventId,'warning','Payout provider event requires review','Payout callback route is registered but no payout handler is attached.');return ['domain'=>$domain,'status'=>'review','alert_id'=>$alert['alert_id'],'dispatch_key'=>'payout-review:'.$eventId];}
        throw new MgProviderDispatchException('Provider route handler is unavailable.',503);
    }catch(Throwable $e){mg_provider_dispatch_alert($pdo,'provider-dispatch:failed:'.$provider.':'.$type.':'.$eventId,'provider_event',$provider.':'.$eventId,'critical','Provider dispatch failed','Dispatch failed for '.$provider.' '.$type.': '.$e->getMessage());throw $e;}
}
function mg_provider_intake_dispatch(PDO $pdo,array $input,array $handlers=[]): array{return mg_webhook_intake($pdo,$input,function(PDO $pdo,string $provider,string $type,array $payload) use ($handlers): array{return mg_provider_dispatch($pdo,$provider,$type,$payload,$handlers);});}
