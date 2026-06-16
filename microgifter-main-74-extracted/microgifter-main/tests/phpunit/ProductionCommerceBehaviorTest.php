<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionCommerceBehaviorTest extends TestCase
{
    public function testCommerceBehaviorCommand(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed validation requires MG_DB_HOST.');
        }

        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_commerce_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);

        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('commerce_capture_behavior',$result['suite']??null);
        foreach(['capture_success','ledger_balanced','pppm_issued','entitlements_granted','receipt_finalized','notifications_created_once','exact_replay','midflow_failure_rolled_back','fixtures_clean'] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testRunnerUsesOwningTransactions(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_commerce_behavior.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_finance_record_paid_order(',$source);
        self::assertStringContainsString('SAVEPOINT commerce_capture_failure',$source);
        self::assertStringContainsString('ROLLBACK TO SAVEPOINT commerce_capture_failure',$source);
        self::assertStringContainsString('$pdo->rollBack()',$source);
    }
}
