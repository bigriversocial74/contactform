<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9CClaimRedemptionLifecycleTest extends TestCase
{
    public function testLifecycleSchemaDefinesClaimsRedemptionsAndActions(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_9c_microgift_lifecycle.sql');
        self::assertIsString($sql);
        foreach(['microgift_claims','microgift_redemptions','microgift_lifecycle_actions'] as $table)self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        self::assertStringContainsString('uq_microgift_claims_idempotency',$sql);
        self::assertStringContainsString('uq_microgift_redemptions_idempotency',$sql);
        self::assertStringContainsString('uq_microgift_lifecycle_idempotency',$sql);
    }

    public function testCredentialVerificationUsesConstantTimeHashAndLockout(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_lifecycle.php');
        self::assertStringContainsString('hash_equals',$source);
        self::assertStringContainsString('failed_attempts',$source);
        self::assertStringContainsString("'locked'",$source);
        self::assertStringContainsString('max_attempts',$source);
    }

    public function testClaimUsesCanonicalAuthorityAndSynchronizesPppmOwnership(): void
    {
        $root=dirname(__DIR__,2);
        $authority=file_get_contents($root.'/api/microgifts/_claim_authority.php');
        $endpoint=file_get_contents($root.'/api/microgifts/claim.php');
        $actionCenter=file_get_contents($root.'/api/account/action-center-claim.php');
        $lifecycle=file_get_contents($root.'/api/microgifts/_lifecycle.php');
        $ownership=file_get_contents($root.'/api/pppm/_ownership.php');
        foreach([$authority,$endpoint,$actionCenter,$lifecycle,$ownership] as $source)self::assertIsString($source);
        self::assertStringContainsString('mg_microgift_assert_claim_replay(',$authority);
        self::assertStringContainsString('mg_microgift_assert_claim_result(',$authority);
        self::assertStringContainsString('PPPM ownership is not synchronized with the Microgift claimant.',$authority);
        self::assertStringContainsString('mg_action_center_project_lifecycle(',$authority);
        self::assertStringContainsString('mg_microgift_claim_canonical(',$endpoint);
        self::assertStringContainsString('mg_microgift_claim_canonical(',$actionCenter);
        self::assertStringContainsString('mg_pppm_transfer_owner_canonical',$lifecycle);
        self::assertStringContainsString('mg_entitlements_sync_pppm_owner',$ownership);
    }

    public function testCustomerRedemptionRouteIsRetiredInFavorOfMerchantAuthority(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/microgifts/redeem.php');
        self::assertIsString($endpoint);
        self::assertStringContainsString('Direct customer redemption has been retired.',$endpoint);
        self::assertStringContainsString("'canonical_endpoint'=>'/api/merchant/microgift-claim.php'",$endpoint);
        self::assertStringContainsString('410,',$endpoint);
        self::assertStringNotContainsString('mg_microgift_redeem(',$endpoint);
    }

    public function testMerchantRedemptionReconcilesPppmActionCenterAndConfirmations(): void
    {
        $root=dirname(__DIR__,2);
        $atomic=file_get_contents($root.'/api/microgifts/_atomic_merchant_redemption.php');
        $reconcile=file_get_contents($root.'/api/microgifts/_redemption_reconciliation.php');
        $endpoint=file_get_contents($root.'/api/merchant/microgift-claim.php');
        $repair=file_get_contents($root.'/api/merchant/microgift-redemption-reconcile.php');
        foreach([$atomic,$reconcile,$endpoint,$repair] as $source)self::assertIsString($source);
        self::assertStringContainsString('mg_location_claim_resolve_authority(',$atomic);
        self::assertStringContainsString('mg_pppm_redeem(',$atomic);
        self::assertStringContainsString('mg_microgift_reconcile_completed_redemption(',$reconcile);
        self::assertStringContainsString('mg_action_center_project_lifecycle(',$reconcile);
        self::assertStringContainsString('mg_microgift_redemption_confirmations(',$reconcile);
        self::assertStringContainsString('mg_microgift_reconcile_completed_redemption(',$endpoint);
        self::assertStringContainsString("mg_require_permission('merchant.location_claim.execute')",$repair);
        self::assertStringContainsString('mg_location_claim_actor_authorized(',$repair);
    }

    public function testLifecycleSupportsCancellationRevocationExpirationAndPaymentPolicy(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_lifecycle.php');
        $policy=file_get_contents(dirname(__DIR__,2).'/api/microgifts/payment-policy.php');
        foreach(['cancel','revoke','expire','refund','dispute_opened','dispute_won','dispute_lost'] as $action)self::assertStringContainsString("'{$action}'",$source.$policy);
        self::assertStringContainsString('microgift_lifecycle_actions',$source);
    }

    public function testReplacementInvalidatesPriorInstanceAndCredential(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/admin/microgift-replace.php');
        self::assertStringContainsString("status='replaced'",$source);
        self::assertStringContainsString('replaced_by_instance_id',$source);
        self::assertStringContainsString("status='revoked'",$source);
        self::assertStringContainsString('mg_microgift_create_credential',$source);
    }

    public function testEndpointsRequireAuthenticationPermissionAndCsrf(): void
    {
        $root=dirname(__DIR__,2);
        $claim=file_get_contents($root.'/api/microgifts/claim.php');
        $redeem=file_get_contents($root.'/api/microgifts/redeem.php');
        $merchant=file_get_contents($root.'/api/merchant/microgift-claim.php');
        $repair=file_get_contents($root.'/api/merchant/microgift-redemption-reconcile.php');
        $admin=file_get_contents($root.'/api/admin/microgift-lifecycle.php');
        self::assertStringContainsString('mg_require_api_user()',$claim.$redeem);
        self::assertStringContainsString('mg_require_csrf_for_write',$claim.$redeem.$merchant.$repair.$admin);
        self::assertStringContainsString("mg_require_permission('merchant.location_claim.execute')",$merchant.$repair);
        self::assertStringContainsString("mg_require_permission('microgift.lifecycle.manage')",$admin);
    }

    public function testMigrationSmokeAndCompatibilityReportExist(): void
    {
        self::assertFileExists(dirname(__DIR__,2).'/scripts/stage9c.php');
        self::assertFileExists(dirname(__DIR__,2).'/scripts/stage9c_smoke.php');
        self::assertFileExists(dirname(__DIR__,2).'/scripts/stage9c_compatibility_report.php');
    }
}
