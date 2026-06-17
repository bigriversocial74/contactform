<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/api/integrations/_provider_dispatch.php';

final class ProviderEventDispatchBehaviorTest extends TestCase
{
    private function scalar(PDO $pdo,string $sql,array $params=[]): mixed{$s=$pdo->prepare($sql);$s->execute($params);return $s->fetchColumn();}
    private function envelope(string $provider,string $eventId,string $type,array $data,string $key,int $now): array{$body=mg_webhook_json(['id'=>$eventId,'data'=>$data]);return ['provider_key'=>$provider,'provider_event_id'=>$eventId,'event_type'=>$type,'raw_body'=>$body,'secret'=>$key,'timestamp'=>(string)$now,'signature'=>mg_webhook_signature($key,(string)$now,$body),'now'=>$now];}
    public function testProviderDispatchRoutesAndAlertsAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed provider dispatch validation requires MG_DB_HOST.');
        $pdo=mg_db();mg_webhook_intake_install($pdo);mg_ops_alert_install($pdo);mg_provider_dispatch_install($pdo);
        $run='pd_'.bin2hex(random_bytes(6));$key='key_'.$run;$now=time();$pdo->beginTransaction();
        try{
            $called=0;$handlers=['payment_dispute'=>function() use (&$called): array{$called++;return ['ok'=>true];}];
            $first=mg_provider_intake_dispatch($pdo,$this->envelope('stripe','evt_'.$run,'charge.dispute.created',['value'=>'ok'],$key,$now),$handlers);
            self::assertSame('processed',$first['status']);self::assertSame('payment_dispute',$first['dispatch']['domain']);self::assertSame(1,$called);
            $replay=mg_provider_intake_dispatch($pdo,$this->envelope('stripe','evt_'.$run,'charge.dispute.created',['value'=>'ok'],$key,$now),$handlers);
            self::assertTrue($replay['duplicate']);self::assertSame(1,$called);
            $unknown=mg_provider_intake_dispatch($pdo,$this->envelope('unknown','evt_'.$run.'_u','thing.happened',['value'=>'x'],$key,$now),[]);
            self::assertSame('ops_review',$unknown['dispatch']['domain']);
            $payout=mg_provider_intake_dispatch($pdo,$this->envelope('stripe','evt_'.$run.'_p','payout.failed',['value'=>'x'],$key,$now),[]);
            self::assertSame('payout_callback',$payout['dispatch']['domain']);
            $failed=mg_provider_intake_dispatch($pdo,$this->envelope('stripe','evt_'.$run.'_f','payment.dispute.created',['value'=>'x'],$key,$now),['payment_dispute'=>function(): array{throw new RuntimeException('domain down');}]);
            self::assertSame('retryable',$failed['status']);
            self::assertGreaterThanOrEqual(3,(int)$this->scalar($pdo,'SELECT COUNT(*) FROM ops_alerts WHERE alert_key LIKE ?',['provider-dispatch:%'.$run.'%']));
            $conflict=false;try{mg_provider_intake_dispatch($pdo,$this->envelope('stripe','evt_'.$run,'charge.dispute.created',['value'=>'changed'],$key,$now),$handlers);}catch(MgWebhookIntakeException $e){$conflict=$e->httpStatus===409;}self::assertTrue($conflict);
            self::assertSame(1,(int)$this->scalar($pdo,'SELECT COUNT(*) FROM provider_webhook_quarantine WHERE provider_event_id=?',['evt_'.$run]));
        }finally{
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->prepare('DELETE FROM provider_webhook_quarantine WHERE provider_event_id LIKE ?')->execute(['evt_'.$run.'%']);
            $pdo->prepare('DELETE FROM provider_webhook_events WHERE provider_event_id LIKE ?')->execute(['evt_'.$run.'%']);
            $pdo->prepare('DELETE FROM ops_alerts WHERE alert_key LIKE ?')->execute(['provider-dispatch:%'.$run.'%']);
        }
    }
}
