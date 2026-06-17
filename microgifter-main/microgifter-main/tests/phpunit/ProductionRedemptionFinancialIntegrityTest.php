<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionRedemptionFinancialIntegrityTest extends TestCase
{
    public function testRedemptionFinancialIntegrityAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed redemption financial validation requires MG_DB_HOST.');
        }
        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_redemption_financial_integrity.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('redemption_financial_integrity_behavior',$result['suite']??null);
        foreach([
            'canonical_merchant_enforced','immutable_snapshot_used','wallet_not_double_credited',
            'exact_replay_safe','conflicting_replay_rejected','recovery_review_created',
            'refund_blocks_cashout','rollback_safe','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testValidatorUsesCanonicalMoneyAndReversalAuthorities(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/scripts/validate_redemption_financial_integrity.php');
        self::assertIsString($source);
        foreach([
            'mg_microgift_redeem(','mg_finance_refund_order(','mg_cashout_request(',
            'merchant_wallet_precredited_at_payment','after_redemption',
            'SAVEPOINT rfi_redemption_failure','ROLLBACK TO SAVEPOINT rfi_redemption_failure',
        ] as $needle){self::assertStringContainsString($needle,$source);}
    }
}
