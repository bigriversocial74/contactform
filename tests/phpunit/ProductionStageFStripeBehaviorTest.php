<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionStageFStripeBehaviorTest extends TestCase
{
    public function testStripeConnectCheckoutAndWebhookAgainstRealDatabase(): void
    {
        if(trim((string)getenv('MG_DB_HOST'))===''||trim((string)getenv('MG_DB_NAME'))===''){
            self::markTestSkipped('Database-backed Stage F validation requires MG_DB_HOST and MG_DB_NAME.');
        }

        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__,2).'/scripts/validate_stage_f_stripe_behavior.php').' 2>&1';
        $output=[];
        $exitCode=0;
        exec($command,$output,$exitCode);
        $text=implode("\n",$output);
        self::assertSame(0,$exitCode,$text);
        $summary=json_decode($text,true,512,JSON_THROW_ON_ERROR);
        self::assertSame('stage_f_stripe_connect_webhook_behavior',$summary['suite']??null,$text);

        foreach([
            'platform_readiness',
            'connect_onboarding_created',
            'connect_account_ready',
            'platform_fee_snapshotted',
            'stripe_checkout_created',
            'destination_and_fee_bound',
            'stripe_signature_verified',
            'webhook_capture_completed',
            'merchant_net_correct',
            'platform_revenue_correct',
            'webhook_replay_safe',
            'fulfillment_once',
            'invalid_signature_rejected',
            'fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($summary[$key]??false),$key.' failed: '.$text);
        }
    }
}
