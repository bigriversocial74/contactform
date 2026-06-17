<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionDeliveryBehaviorTest extends TestCase
{
    public function testDeliveryRetryAndCallbackReconciliationAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed delivery validation requires MG_DB_HOST.');
        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_delivery_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('messaging_delivery_retry_reconciliation_behavior',$result['suite']??null);
        foreach(['event_queued','event_replay','conflict_rejected','claim_exclusive','success_terminal','transient_retry','retry_exhausted','permanent_failed','suppression_enforced','security_not_suppressed','callback_replay','callback_conflict_rejected','secrets_redacted','forced_failure_rolled_back','fixtures_clean'] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testDeliveryAuthorityDefinesQueueAttemptAndCallbackContracts(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/communications/_delivery.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_delivery_enqueue(',$source);
        self::assertStringContainsString('mg_delivery_claim_next(',$source);
        self::assertStringContainsString('mg_delivery_process_job(',$source);
        self::assertStringContainsString('mg_delivery_process_callback(',$source);
        self::assertStringContainsString('message_delivery_attempts',$source);
    }
}
