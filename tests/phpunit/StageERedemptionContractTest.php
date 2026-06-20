<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StageERedemptionContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testAtomicServiceOwnsTheCompleteRedemption(): void
    {
        $source=$this->source('api/microgifts/_atomic_merchant_redemption.php');
        foreach([
            "['issued','delivered','claim_pending','claimed','redeemable']",
            'mg_microgift_canonical_merchant($pdo,$instance)',
            'mg_location_claim_resolve_authority(',
            'mg_location_claim_record_attempt(',
            'mg_pppm_redeem(',
            'mg_location_claim_increment_usage(',
            'mg_action_center_refresh_existing_lifecycle(',
            'mg_microgift_redemption_confirmations(',
            "'microgift_redeemed'",
            "'merchant_redemption'",
            "'merchant_claim.completed'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testMerchantEndpointDoesNotTrustAClaimantPayload(): void
    {
        $source=$this->source('api/merchant/microgift-claim.php');
        self::assertStringContainsString("unset($input['claimant_user_id'])",$source);
        self::assertStringContainsString('mg_claim_execute_operation(',$source);
        self::assertStringContainsString('Microgift redeemed and both parties confirmed.',$source);
        self::assertStringNotContainsString('mg_action_center_project_lifecycle(',$source);
    }

    public function testMerchantWorkspaceUsesCanonicalRoutesAndData(): void
    {
        $script=$this->source('assets/js/merchant-claims.js');
        $view=$this->source('includes/merchant-claims-view.php');
        $dashboard=$this->source('api/merchant/claims-dashboard.php');

        self::assertStringContainsString('/api/merchant/microgift-claim-lookup.php?instance_id=',$script);
        self::assertStringContainsString("Microgifter.post('/api/merchant/microgift-claim.php', payload)",$script);
        self::assertStringNotContainsString('/api/gifts/verify-merchant-claim.php',$script);
        self::assertStringNotContainsString('/api/gifts/redeem-merchant-claim.php',$script);
        self::assertStringContainsString('name="instance_id"',$view);
        self::assertStringContainsString('name="claim_code"',$view);
        self::assertStringContainsString('microgift_claim_attempts',$dashboard);
        self::assertStringContainsString('microgift_redemptions',$dashboard);
        self::assertStringNotContainsString('gift_claims',$dashboard);
    }

    public function testCustomerEndpointPointsToMerchantRedemption(): void
    {
        $source=$this->source('api/account/action-center-redeem.php');
        self::assertStringContainsString('Customer-side redemption has been retired.',$source);
        self::assertStringContainsString("'/api/merchant/microgift-claim.php'",$source);
        self::assertStringContainsString(',410,',$source);
        self::assertStringNotContainsString('mg_microgift_redeem(',$source);
    }
}
