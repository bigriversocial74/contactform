<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionPaymentReversalMicrogiftBehaviorTest extends TestCase
{
    public function testRefundAndDisputeMicrogiftReconciliationAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed payment reversal validation requires MG_DB_HOST.');
        }
        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_payment_reversal_microgifts.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('payment_reversal_microgift_behavior',$result['suite']??null);
        foreach([
            'full_refund_revoked','refund_credentials_revoked','refund_action_center_reconciled',
            'partial_refund_reviewed','dispute_open_suspended','dispute_won_restored',
            'dispute_lost_revoked','redeemed_recovery_reviewed','replay_safe','rollback_safe','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }
}
