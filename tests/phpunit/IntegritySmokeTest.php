<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IntegritySmokeTest extends TestCase
{
    public function testLifecycleEntryPointsUseCanonicalV1Authorities(): void
    {
        $root=dirname(__DIR__,2);
        $claim=file_get_contents($root.'/api/account/action-center-claim.php');
        $customerRedeem=file_get_contents($root.'/api/account/action-center-redeem.php');
        $merchantRedeem=file_get_contents($root.'/api/merchant/microgift-claim.php');
        $atomic=file_get_contents($root.'/api/microgifts/_atomic_merchant_redemption.php');

        foreach([$claim,$customerRedeem,$merchantRedeem,$atomic] as $source){
            self::assertIsString($source);
        }

        self::assertStringContainsString('mg_microgift_integrity_claim',$claim);
        self::assertStringContainsString('mg_require_csrf_for_write',$claim.$customerRedeem.$merchantRedeem);

        self::assertStringContainsString('Customer-side redemption has been retired.',$customerRedeem);
        self::assertStringContainsString("'/api/merchant/microgift-claim.php'",$customerRedeem);
        self::assertStringNotContainsString('mg_microgift_redeem(',$customerRedeem);

        self::assertStringContainsString('mg_claim_execute_operation(',$merchantRedeem);
        self::assertStringContainsString('unset($input[\'claimant_user_id\'])',$merchantRedeem);
        self::assertStringContainsString('mg_location_claim_resolve_authority(',$atomic);
        self::assertStringContainsString('mg_action_center_refresh_existing_lifecycle(',$atomic);
        self::assertStringContainsString('mg_microgift_redemption_confirmations(',$atomic);
    }
}
