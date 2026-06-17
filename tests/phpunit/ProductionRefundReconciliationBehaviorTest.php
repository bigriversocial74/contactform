<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionRefundReconciliationBehaviorTest extends TestCase
{
    public function testRefundReconciliationAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed refund reconciliation validation requires MG_DB_HOST.');
        }
        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_refund_reconciliation_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('refund_behavior',$result['suite']??null);
        foreach([
            'partial_refund','full_refund','ledger_balanced','exact_replay','conflicting_replay_rejected',
            'entitlement_policy','pppm_preserved','receipt_and_audit_consistent','notifications_created_once',
            'forced_failure_rolled_back','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testRunnerUsesCanonicalAuthorityAndTransactionRollback(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_refund_reconciliation_behavior.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_finance_refund_order(',$source);
        self::assertStringContainsString('after_microgift_reconciliation',$source);
        self::assertStringContainsString('SAVEPOINT refund_failure',$source);
        self::assertStringContainsString('ROLLBACK TO SAVEPOINT refund_failure',$source);
        self::assertStringContainsString('$pdo->rollBack()',$source);
    }
}
