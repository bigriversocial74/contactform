<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftLifecycleTest extends TestCase
{
    public function testLifecycleMigrationDefinesDeliveryAndClaimHistoryTables(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_gift_lifecycle.sql');

        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gift_deliveries', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gift_claims', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gift_claim_attempts', $sql);
        self::assertStringContainsString('failed_attempts SMALLINT UNSIGNED', $sql);
        self::assertStringNotContainsString('ADD COLUMN IF NOT EXISTS', $sql);
        self::assertStringNotContainsString('ADD UNIQUE KEY IF NOT EXISTS', $sql);
    }

    public function testMerchantClaimMigrationDefinesLocationsCodesAndGiftEligibility(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_merchant_claim_codes.sql');

        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS merchant_locations', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS merchant_claim_codes', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS gift_merchant_eligibility', $sql);
        self::assertStringContainsString('code_hash CHAR(64)', $sql);
        self::assertStringContainsString('usage_limit INT UNSIGNED', $sql);
        self::assertStringContainsString('usage_count INT UNSIGNED', $sql);
        self::assertStringContainsString('merchant_claim_code_id', $sql);
        self::assertStringContainsString('verified_by_user_id', $sql);
        self::assertStringContainsString('redeemed_by_user_id', $sql);
        self::assertStringContainsString('id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        self::assertStringContainsString('PRIMARY KEY (id)', $sql);
        self::assertStringNotContainsString('PRIMARY KEY (gift_id, merchant_user_id, location_id)', $sql);
    }

    public function testMigrationRunnerIncludesBothGiftLifecycleMigrations(): void
    {
        $runner = file_get_contents(dirname(__DIR__, 2) . '/scripts/run_migrations.php');

        self::assertIsString($runner);
        self::assertStringContainsString("'stage_3_gift_lifecycle.sql'", $runner);
        self::assertStringContainsString("'stage_3_merchant_claim_codes.sql'", $runner);
    }

    public function testPublishDoesNotGenerateOrReturnGiftClaimCodes(): void
    {
        $publish = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/publish.php');

        self::assertIsString($publish);
        self::assertStringContainsString("mg_require_permission('gift.publish')", $publish);
        self::assertStringContainsString('Only the gift owner can publish this gift.', $publish);
        self::assertStringContainsString("'status' => 'sent'", $publish);
        self::assertStringNotContainsString('claim_code', $publish);
        self::assertStringNotContainsString('INSERT INTO gift_claims', $publish);
        self::assertStringNotContainsString('random_bytes(6)', $publish);
    }

    public function testMerchantClaimCodesArePepperedAndNeverReturnedInPlaintext(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2) . '/api/config.php');
        $codes = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/claim-codes.php');
        $verify = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/verify-merchant-claim.php');

        foreach ([$config, $codes, $verify] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('MG_CLAIM_CODE_PEPPER', $config);
        self::assertStringContainsString("hash_hmac('sha256', \$code, \$pepper)", $codes);
        self::assertStringContainsString("hash_hmac('sha256', \$merchantCode, \$pepper)", $verify);
        self::assertStringContainsString('hash_equals', $verify);
        self::assertStringContainsString('code_last4', $codes);
        self::assertStringNotContainsString("'code' => \$code", $codes);
    }

    public function testVerificationRequiresMerchantPermissionLocationAndEligibility(): void
    {
        $verify = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/verify-merchant-claim.php');

        self::assertIsString($verify);
        self::assertStringContainsString("mg_require_permission('merchant.gifts.redeem')", $verify);
        self::assertStringContainsString('merchant_locations', $verify);
        self::assertStringContainsString('gift_merchant_eligibility', $verify);
        self::assertStringContainsString('merchant_claim_codes', $verify);
        self::assertStringContainsString('gift_claim_attempts', $verify);
        self::assertStringContainsString('$attempts >= 5', $verify);
        self::assertStringContainsString("\$locked ? 'locked' : 'pending'", $verify);
        self::assertStringContainsString('location_id', $verify);
    }

    public function testRedemptionRequiresVerifiedMerchantAndUpdatesCodeUsage(): void
    {
        $redeem = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/redeem-merchant-claim.php');

        self::assertIsString($redeem);
        self::assertStringContainsString("mg_require_permission('merchant.gifts.redeem')", $redeem);
        self::assertStringContainsString("\$claim['status'] !== 'verified'", $redeem);
        self::assertStringContainsString('verified_by_user_id', $redeem);
        self::assertStringContainsString('redeemed_by_user_id', $redeem);
        self::assertStringContainsString('usage_count = usage_count + 1', $redeem);
        self::assertStringContainsString("status = 'redeemed'", $redeem);
        self::assertStringContainsString("status = 'claimed'", $redeem);
        self::assertStringContainsString('mg_gift_event($pdo', $redeem);
    }

    public function testScannerRedemptionUsesServerSideLocationCodeAndProtectsVerifiedClaims(): void
    {
        $scanner = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/scanner-claim.php');
        $sidebar = file_get_contents(dirname(__DIR__, 2) . '/includes/agent-sidebar.php');

        self::assertIsString($scanner);
        self::assertIsString($sidebar);
        self::assertStringContainsString('data-scanner-api="/api/merchant/scanner-claim.php"', $sidebar);
        self::assertStringContainsString("mg_require_permission('merchant.gifts.redeem')", $scanner);
        self::assertStringContainsString('mg_scanner_claim_identifier', $scanner);
        self::assertStringContainsString('mg_scanner_claim_assert_location_binding', $scanner);
        self::assertStringContainsString('already verified for another merchant location', $scanner);
        self::assertStringContainsString('This scanner location does not have an active claim code assigned.', $scanner);
        self::assertStringContainsString('require_confirmation', $scanner);
        self::assertStringContainsString('confirmed', $scanner);
        self::assertStringContainsString("status='verified'", $scanner);
        self::assertStringContainsString("status='redeemed'", $scanner);
        self::assertStringContainsString('usage_count=usage_count+1', $scanner);
        self::assertStringContainsString('gift.scanner_claim_redeemed', $scanner);
    }

    public function testMerchantManagementApisAreOwnerScoped(): void
    {
        $locations = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/locations.php');
        $codes = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/claim-codes.php');
        $eligibility = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/merchant-eligibility.php');

        foreach ([$locations, $codes, $eligibility] as $source) {
            self::assertIsString($source);
            self::assertStringContainsString('mg_require_csrf_for_write', $source);
        }

        self::assertStringContainsString('merchant.locations.manage', $locations);
        self::assertStringContainsString("mg_require_permission('merchant.claim_codes.manage')", $codes);
        self::assertStringContainsString('mg_merchant_ensure_workspace', $locations);
        self::assertStringContainsString('workspace_id=?', $locations);
        self::assertStringContainsString('merchant_user_id = ?', $codes);
        self::assertStringContainsString('Only the gift owner can assign merchants.', $eligibility);
        self::assertStringContainsString('Merchant already assigned.', $eligibility);
    }
}
