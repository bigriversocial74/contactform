<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftLifecycleTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testLifecycleMigrationDefinesDeliveryAndClaimHistoryTables(): void
    {
        $sql = $this->source('database/stage_3_gift_lifecycle.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gift_deliveries', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gift_claims', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gift_claim_attempts', $sql);
        self::assertStringContainsString('failed_attempts SMALLINT UNSIGNED', $sql);
        self::assertStringNotContainsString('ADD COLUMN IF NOT EXISTS', $sql);
        self::assertStringNotContainsString('ADD UNIQUE KEY IF NOT EXISTS', $sql);
    }

    public function testMerchantClaimMigrationDefinesLocationsCodesAndGiftEligibility(): void
    {
        $sql = $this->source('database/stage_3_merchant_claim_codes.sql');
        foreach (['merchant_locations','merchant_claim_codes','gift_merchant_eligibility','code_hash CHAR(64)','usage_limit INT UNSIGNED','usage_count INT UNSIGNED','merchant_claim_code_id','verified_by_user_id','redeemed_by_user_id','PRIMARY KEY (id)'] as $needle) {
            self::assertStringContainsString($needle, $sql);
        }
        self::assertStringNotContainsString('PRIMARY KEY (gift_id, merchant_user_id, location_id)', $sql);
    }

    public function testMigrationRunnerIncludesBothGiftLifecycleMigrations(): void
    {
        $runner = $this->source('scripts/run_migrations.php');
        self::assertStringContainsString("'stage_3_gift_lifecycle.sql'", $runner);
        self::assertStringContainsString("'stage_3_merchant_claim_codes.sql'", $runner);
    }

    public function testPublishDoesNotGenerateOrReturnGiftClaimCodes(): void
    {
        $publish = $this->source('api/gifts/publish.php');
        self::assertStringContainsString("mg_require_permission('gift.publish')", $publish);
        self::assertStringContainsString('Only the gift owner can publish this gift.', $publish);
        self::assertStringContainsString("'status' => 'sent'", $publish);
        self::assertStringNotContainsString('claim_code', $publish);
        self::assertStringNotContainsString('INSERT INTO gift_claims', $publish);
        self::assertStringNotContainsString('random_bytes(6)', $publish);
    }

    public function testMerchantClaimCodesArePepperedAndNeverReturnedInPlaintext(): void
    {
        $config = $this->source('api/config.php');
        $codes = $this->source('api/merchant/claim-codes.php');
        $verify = $this->source('api/gifts/verify-merchant-claim.php');
        self::assertStringContainsString('MG_CLAIM_CODE_PEPPER', $config);
        self::assertStringContainsString('hash_hmac', $codes);
        self::assertStringContainsString('hash_hmac', $verify);
        self::assertStringContainsString('hash_equals', $verify);
        self::assertStringContainsString('code_last4', $codes);
    }

    public function testVerificationRequiresMerchantPermissionLocationAndEligibility(): void
    {
        $verify = $this->source('api/gifts/verify-merchant-claim.php');
        foreach (["mg_require_permission('merchant.gifts.redeem')",'merchant_locations','gift_merchant_eligibility','merchant_claim_codes','gift_claim_attempts','location_id'] as $needle) {
            self::assertStringContainsString($needle, $verify);
        }
    }

    public function testRedemptionRequiresVerifiedMerchantAndUpdatesCodeUsage(): void
    {
        $redeem = $this->source('api/gifts/redeem-merchant-claim.php');
        foreach (["mg_require_permission('merchant.gifts.redeem')",'verified_by_user_id','redeemed_by_user_id','usage_count = usage_count + 1',"status = 'redeemed'","status = 'claimed'",'mg_gift_event($pdo'] as $needle) {
            self::assertStringContainsString($needle, $redeem);
        }
    }

    public function testScannerRedemptionUsesServerSideLocationCodeAndProtectsVerifiedClaims(): void
    {
        $scanner = $this->source('api/merchant/scanner-claim.php');
        $sidebar = $this->source('includes/agent-sidebar.php');
        self::assertStringContainsString('data-scanner-api=', $sidebar);
        self::assertStringContainsString("mg_require_permission('merchant.gifts.redeem')", $scanner);
        self::assertMatchesRegularExpression('/mg_scanner_claim_(identifier|context)/', $scanner);
        foreach (['mg_scanner_claim_assert_location_binding','already verified for another merchant location','This scanner location does not have an active claim code assigned.','require_confirmation','confirmed',"status='verified'","status='redeemed'",'usage_count=usage_count+1','gift.scanner_claim_redeemed'] as $needle) {
            self::assertStringContainsString($needle, $scanner);
        }
    }

    public function testMerchantManagementApisAreOwnerScoped(): void
    {
        $locations = $this->source('api/merchant/locations.php');
        $codes = $this->source('api/merchant/claim-codes.php');
        $eligibility = $this->source('api/gifts/merchant-eligibility.php');
        foreach ([$locations, $codes, $eligibility] as $source) self::assertStringContainsString('mg_require_csrf_for_write', $source);
        foreach (['merchant.locations.manage','mg_merchant_ensure_workspace','workspace_id=?'] as $needle) self::assertStringContainsString($needle, $locations);
        self::assertStringContainsString("mg_require_permission('merchant.claim_codes.manage')", $codes);
        self::assertStringContainsString('merchant_user_id = ?', $codes);
        self::assertStringContainsString('Only the gift owner can assign merchants.', $eligibility);
        self::assertStringContainsString('Merchant already assigned.', $eligibility);
    }
}
