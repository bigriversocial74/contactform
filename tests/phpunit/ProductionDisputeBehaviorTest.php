<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionDisputeBehaviorTest extends TestCase
{
    public function testDisputeChargebackAndEntitlementReconciliationAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')==='')self::markTestSkipped('Database-backed dispute validation requires MG_DB_HOST.');
        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_dispute_behavior.php').' 2>&1';
        $output=[];$exitCode=0;exec($command,$output,$exitCode);$raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);
        $result=json_decode($raw,true);self::assertIsArray($result,$raw);
        self::assertSame('payment_dispute_chargeback_behavior',$result['suite']??null);
        foreach(['open_reserved','open_replay','conflicting_replay_rejected','full_entitlements_suspended','won_restored','lost_finalized','fee_balanced','partial_review','pppm_preserved','receipts_consistent','notifications_once','forced_failure_rolled_back','fixtures_clean'] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testWebhookUsesCanonicalDisputeAuthority(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/payments/webhook.php');
        $service=file_get_contents(dirname(__DIR__,2).'/api/payments/_disputes.php');
        $helper=file_get_contents(dirname(__DIR__,2).'/api/payments/_dispute_entitlements.php');
        self::assertIsString($endpoint);self::assertIsString($service);self::assertIsString($helper);
        self::assertStringContainsString("require_once __DIR__ . '/_disputes.php'",$endpoint);
        self::assertStringContainsString('mg_dispute_process_webhook(',$endpoint);
        self::assertStringContainsString('mg_dispute_post_reserve(',$service);
        self::assertStringContainsString('mg_dispute_post_won(',$service);
        self::assertStringContainsString('mg_dispute_post_lost(',$service);
        self::assertStringContainsString('mg_entitlements_suspend_for_order(',$service);
        self::assertStringContainsString('mg_dispute_revoke_entitlements(',$service);
        self::assertStringContainsString('entitlement.revoked',$helper);
        self::assertStringContainsString('Dispute terminal event conflicts with the recorded outcome.',$service);
    }
}
