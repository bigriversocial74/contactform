<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionStageERedemptionBehaviorTest extends TestCase
{
    public function testAtomicMerchantRedemptionAgainstRealDatabase(): void
    {
        if(trim((string)getenv('MG_DB_HOST'))===''||trim((string)getenv('MG_DB_NAME'))===''){
            self::markTestSkipped('Database-backed Stage E validation requires MG_DB_HOST and MG_DB_NAME.');
        }

        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__,2).'/scripts/validate_stage_e_redemption_behavior.php').' 2>&1';
        $output=[];
        $exitCode=0;
        exec($command,$output,$exitCode);
        $text=implode("\n",$output);
        self::assertSame(0,$exitCode,$text);
        $summary=json_decode($text,true,512,JSON_THROW_ON_ERROR);
        self::assertSame('stage_e_atomic_redemption_behavior',$summary['suite']??null,$text);

        foreach([
            'delivered_gift_redeemed',
            'merchant_location_authorized',
            'pppm_redeemed',
            'claim_code_usage_recorded',
            'action_center_reconciled',
            'customer_confirmation_created',
            'merchant_confirmation_created',
            'replay_idempotent',
            'invalid_code_attempt_recorded',
            'invalid_code_left_gift_available',
            'fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($summary[$key]??false),$key.' failed: '.$text);
        }
    }
}
