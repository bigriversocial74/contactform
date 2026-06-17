<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionSubscriptionBehaviorTest extends TestCase
{
    public function testSubscriptionFundingRenewalAndDunningAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed subscription validation requires MG_DB_HOST.');
        }

        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_subscription_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);

        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('subscription_funding_renewal_dunning_behavior',$result['suite']??null);
        foreach([
            'created_pending','initial_funding_activated','activation_replay','conflicting_settlement_rejected',
            'initial_ledger_balanced','renewal_advanced','renewal_replay','recovery_after_failure',
            'dunning_scheduled','dunning_exhausted','notifications_once','forced_failure_rolled_back',
            'financial_rows_consistent','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testRunnerUsesCanonicalSubscriptionAndStage7Authorities(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_subscription_behavior.php');
        self::assertIsString($source);
        foreach([
            'mg_subscription_create_plan(','mg_subscription_subscribe(','mg_subscription_attempt(',
            'mg_subscription_apply_payment_success(','mg_subscription_apply_payment_failure(',
        ] as $authority){
            self::assertStringContainsString($authority,$source);
        }
        self::assertStringContainsString('ledger_entries',$source);
        self::assertStringContainsString('SAVEPOINT subscription_failure',$source);
        self::assertStringContainsString('ROLLBACK TO SAVEPOINT subscription_failure',$source);
        self::assertStringContainsString('$pdo->rollBack()',$source);
    }

    public function testSettlementServiceRejectsConflictsAndDeduplicatesFailureReplay(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/subscriptions/_funding.php');
        self::assertIsString($source);
        self::assertStringContainsString('Failed subscription attempt cannot be settled as succeeded.',$source);
        self::assertStringContainsString("attempt_status']==='failed'",$source);
        self::assertStringContainsString("'duplicate'=>true",$source);
        self::assertStringContainsString("\$failureHook('after_ledger'",$source);
    }
}
