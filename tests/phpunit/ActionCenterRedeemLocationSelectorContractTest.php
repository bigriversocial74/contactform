<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterRedeemLocationSelectorContractTest extends TestCase
{
    public function testActionCenterClaimEndpointRemainsRecipientClaimOnly(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-claim.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_microgift_claim_canonical(',$source);
        self::assertStringContainsString('Microgift claimed.',$source);
        self::assertStringNotContainsString('mg_microgift_redeem',$source);
        self::assertStringNotContainsString('location_id',$source);
    }

    public function testCustomerSideRedemptionIsRetiredInFavorOfMerchantAuthority(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/account/action-center-redeem.php');
        self::assertIsString($source);
        foreach([
            "mg_require_method('POST')",
            'mg_require_api_user()',
            'mg_require_csrf_for_write($input)',
            'Customer-side redemption has been retired.',
            "['canonical_endpoint'=>'/api/merchant/microgift-claim.php']",
            '410,',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('mg_microgift_redeem(',$source);
        self::assertStringNotContainsString('mg_microgift_atomic_merchant_redeem(',$source);
    }

    public function testMerchantLookupReturnsOnlySafeRedemptionEligibility(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/microgift-claim-lookup.php');
        self::assertIsString($source);
        foreach([
            "mg_require_method('GET')",
            "mg_require_permission('merchant.claims.view')",
            'mg_location_claim_actor_authorized(',
            'canonical_merchant_user_id',
            'mg_microgift_location_allowed($instance,$locationId)',
            "'redeemable'=>\$paid&&\$available&&\$notExpired&&\$locationAllowed",
            "'redemption'=>\$redemptionStmt->fetch(PDO::FETCH_ASSOC)?:null",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('owner_name',$source);
        self::assertStringNotContainsString('claim_code',$source);
    }

    public function testMerchantEndpointUsesOneCanonicalAtomicOperation(): void
    {
        $root=dirname(__DIR__,2);
        $source=file_get_contents($root.'/api/merchant/microgift-claim.php');
        $atomic=file_get_contents($root.'/api/microgifts/_atomic_merchant_redemption.php');
        self::assertIsString($source);
        self::assertIsString($atomic);
        foreach([
            "mg_require_method('POST')",
            "mg_require_permission('merchant.location_claim.execute')",
            'mg_require_csrf_for_write($input)',
            "SELECT merchant_user_id,name,status FROM merchant_locations WHERE public_id=? LIMIT 1",
            'unset($input[\'claimant_user_id\'])',
            'mg_claim_execute_operation(',
            'Microgift redeemed and both parties confirmed.',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        foreach([
            "'customer_notification_id'=>\$confirmations['customer_notification_id']",
            "'merchant_notification_id'=>\$confirmations['merchant_notification_id']",
            'mg_microgift_redemption_confirmations(',
        ] as $needle){
            self::assertStringContainsString($needle,$atomic);
        }
        self::assertStringNotContainsString('/api/gifts/verify-merchant-claim.php',$source);
        self::assertStringNotContainsString('/api/gifts/redeem-merchant-claim.php',$source);
    }

    public function testMerchantUiUsesCanonicalLookupAndPersistentAtomicRedemptionKey(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/merchant-claims.js');
        self::assertIsString($source);
        foreach([
            '/api/merchant/microgift-claim-lookup.php?instance_id=',
            "Microgifter.post('/api/merchant/microgift-claim.php', payload)",
            'payload.idempotency_key = redemptionWorkflowKey(payload.instance_id, payload.location_id)',
            'window.sessionStorage.getItem(storageKey)',
            'Redemption confirmed',
            'Customer confirmation',
            'Merchant confirmation',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('/api/gifts/verify-merchant-claim.php',$source);
        self::assertStringNotContainsString('/api/gifts/redeem-merchant-claim.php',$source);
    }
}
