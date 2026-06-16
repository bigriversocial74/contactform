<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9CClaimRedemptionLifecycleTest extends TestCase
{
    public function testLifecycleSchemaDefinesClaimsRedemptionsAndActions(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_9c_microgift_lifecycle.sql');
        self::assertIsString($sql);
        foreach(['microgift_claims','microgift_redemptions','microgift_lifecycle_actions'] as $table){self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);}
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

    public function testClaimIsIdempotentAndSynchronizesPppmEntitlements(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_lifecycle.php');
        $ownership=file_get_contents(dirname(__DIR__,2).'/api/pppm/_ownership.php');
        self::assertStringContainsString('SELECT public_id,status FROM microgift_claims WHERE idempotency_key=?',$source);
        self::assertStringContainsString('mg_pppm_transfer_owner_canonical',$source);
        self::assertStringContainsString('mg_entitlements_sync_pppm_owner',$ownership);
        self::assertStringContainsString("status='redeemable'",$source);
        self::assertStringContainsString("status='consumed'",$source);
    }

    public function testRedemptionIsTransactionalRequestBoundAndLocationAware(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_lifecycle.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/microgifts/redeem.php');
        self::assertStringContainsString('SELECT r.*,mi.public_id instance_public_id FROM microgift_redemptions r',$source);
        self::assertStringContainsString('mg_microgift_assert_redemption_replay',$source);
        self::assertStringContainsString('Redemption idempotency key is already bound to a different request.',$source);
        self::assertStringContainsString('mg_microgift_canonical_merchant',$source);
        self::assertStringContainsString('Microgift is not redeemable by this merchant.',$source);
        self::assertStringContainsString('mg_microgift_location_allowed',$source);
        self::assertStringContainsString("status='redeemed'",$source);
        self::assertStringContainsString('merchant_wallet_precredited_at_payment',$source);
        self::assertStringContainsString("\$failureHook('after_redemption'",$source);
        self::assertStringContainsString('beginTransaction',$endpoint);
    }

    public function testLifecycleSupportsCancellationRevocationExpirationAndPaymentPolicy(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/microgifts/_lifecycle.php');
        $policy=file_get_contents(dirname(__DIR__,2).'/api/microgifts/payment-policy.php');
        foreach(['cancel','revoke','expire','refund','dispute_opened','dispute_won','dispute_lost'] as $action){self::assertStringContainsString("'{$action}'",$source.$policy);}
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
        $claim=file_get_contents(dirname(__DIR__,2).'/api/microgifts/claim.php');
        $redeem=file_get_contents(dirname(__DIR__,2).'/api/microgifts/redeem.php');
        $admin=file_get_contents(dirname(__DIR__,2).'/api/admin/microgift-lifecycle.php');
        self::assertStringContainsString('mg_require_api_user()',$claim);
        self::assertStringContainsString('mg_require_api_user()',$redeem);
        self::assertStringContainsString('mg_require_csrf_for_write',$claim.$redeem.$admin);
        self::assertStringContainsString("mg_require_permission('microgift.lifecycle.manage')",$admin);
    }

    public function testMigrationSmokeAndCompatibilityReportExist(): void
    {
        self::assertFileExists(dirname(__DIR__,2).'/scripts/stage9c.php');
        self::assertFileExists(dirname(__DIR__,2).'/scripts/stage9c_smoke.php');
        self::assertFileExists(dirname(__DIR__,2).'/scripts/stage9c_compatibility_report.php');
    }
}
