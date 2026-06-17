<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionMoneyBehaviorTest extends TestCase
{
    public function testCanonicalLedgerBehaviorAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed behavioral validation requires MG_DB_HOST.');
        }

        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_money_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);

        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('stage7_money_behavior',$result['suite']??null);
        foreach([
            'balanced_post',
            'exact_replay',
            'conflicting_replay_rejected',
            'unbalanced_post_rejected',
            'reversal_integrity',
            'rollback_clean',
        ] as $assertion){
            self::assertTrue((bool)($result[$assertion]??false),$assertion.' failed: '.$raw);
        }
    }

    public function testBehaviorSuiteUsesCanonicalStage7Authority(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_money_behavior.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_ledger_post(',$source);
        self::assertStringContainsString('mg_ledger_reverse(',$source);
        self::assertStringContainsString('ledger_reversal_links',$source);
        self::assertStringContainsString('$pdo->rollBack()',$source);
        self::assertStringNotContainsString('financial_ledger_entries',$source);
    }
}
