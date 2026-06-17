<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/api/integrations/_webhook_intake.php';

final class WebhookIntakeBehaviorTest extends TestCase
{
    private function scalar(PDO $pdo,string $sql,array $params=[]): mixed
    {
        $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
    }

    public function testReplayQuarantineAndRetryableDispatchAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed webhook validation requires MG_DB_HOST.');
        $pdo=mg_db();mg_webhook_intake_install($pdo);
        $run='webhook_'.bin2hex(random_bytes(6));$secret='whsec_'.$run;$now=time();
        $pdo->beginTransaction();
        try{
            $payload=['id'=>'evt_'.$run,'object'=>'event','private_token'=>'hidden-'.$run,'data'=>['value'=>'ok']];
            $body=mg_webhook_json($payload);$sig=mg_webhook_signature($secret,(string)$now,$body);
            $dispatcher=function(PDO $pdo,string $provider,string $type,array $payload) use ($run): array{return ['dispatch_key'=>$provider.':'.$type.':'.$run];};
            $first=mg_webhook_intake($pdo,['provider_key'=>'stripe','provider_event_id'=>'evt_'.$run,'event_type'=>'payment.updated','raw_body'=>$body,'secret'=>$secret,'timestamp'=>(string)$now,'signature'=>$sig,'now'=>$now],$dispatcher);
            self::assertSame('processed',$first['status']);
            self::assertFalse($first['duplicate']);
            self::assertStringNotContainsString('hidden-'.$run,(string)$this->scalar($pdo,'SELECT payload_json FROM provider_webhook_events WHERE public_id=?',[$first['event_id']]));
            $replay=mg_webhook_intake($pdo,['provider_key'=>'stripe','provider_event_id'=>'evt_'.$run,'event_type'=>'payment.updated','raw_body'=>$body,'secret'=>$secret,'timestamp'=>(string)$now,'signature'=>$sig,'now'=>$now],$dispatcher);
            self::assertTrue($replay['duplicate']);
            self::assertSame($first['event_id'],$replay['event_id']);
            $conflict=false;$changed=mg_webhook_json(['id'=>'evt_'.$run,'object'=>'event','data'=>['value'=>'changed']]);$changedSig=mg_webhook_signature($secret,(string)$now,$changed);
            try{mg_webhook_intake($pdo,['provider_key'=>'stripe','provider_event_id'=>'evt_'.$run,'event_type'=>'payment.updated','raw_body'=>$changed,'secret'=>$secret,'timestamp'=>(string)$now,'signature'=>$changedSig,'now'=>$now],$dispatcher);}catch(MgWebhookIntakeException $e){$conflict=$e->httpStatus===409;}
            self::assertTrue($conflict);
            self::assertSame(1,(int)$this->scalar($pdo,'SELECT COUNT(*) FROM provider_webhook_quarantine WHERE provider_event_id=? AND reason=?',['evt_'.$run,'conflicting_replay']));
            $badSig=false;
            try{mg_webhook_intake($pdo,['provider_key'=>'stripe','provider_event_id'=>'evt_'.$run.'_bad','event_type'=>'payment.updated','raw_body'=>$body,'secret'=>$secret,'timestamp'=>(string)$now,'signature'=>'bad','now'=>$now],$dispatcher);}catch(MgWebhookIntakeException $e){$badSig=$e->httpStatus===401;}
            self::assertTrue($badSig);
            self::assertSame(1,(int)$this->scalar($pdo,'SELECT COUNT(*) FROM provider_webhook_quarantine WHERE provider_event_id=? AND reason=?',['evt_'.$run.'_bad','signature_invalid']));
            $stale=false;$old=$now-1000;$oldSig=mg_webhook_signature($secret,(string)$old,$body);
            try{mg_webhook_intake($pdo,['provider_key'=>'stripe','provider_event_id'=>'evt_'.$run.'_old','event_type'=>'payment.updated','raw_body'=>$body,'secret'=>$secret,'timestamp'=>(string)$old,'signature'=>$oldSig,'now'=>$now],$dispatcher);}catch(MgWebhookIntakeException $e){$stale=$e->httpStatus===401;}
            self::assertTrue($stale);
            $failBody=mg_webhook_json(['id'=>'evt_'.$run.'_retry','data'=>['value'=>'retry']]);$failSig=mg_webhook_signature($secret,(string)$now,$failBody);
            $retry=mg_webhook_intake($pdo,['provider_key'=>'stripe','provider_event_id'=>'evt_'.$run.'_retry','event_type'=>'payment.failed','raw_body'=>$failBody,'secret'=>$secret,'timestamp'=>(string)$now,'signature'=>$failSig,'now'=>$now],function(): array{throw new RuntimeException('dispatcher offline');});
            self::assertSame('retryable',$retry['status']);
            self::assertSame(1,(int)$this->scalar($pdo,'SELECT COUNT(*) FROM provider_webhook_events WHERE provider_event_id=? AND status=?',['evt_'.$run.'_retry','retryable']));
        }finally{
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->prepare('DELETE FROM provider_webhook_quarantine WHERE provider_event_id LIKE ?')->execute(['evt_'.$run.'%']);
            $pdo->prepare('DELETE FROM provider_webhook_events WHERE provider_event_id LIKE ?')->execute(['evt_'.$run.'%']);
        }
    }
}
