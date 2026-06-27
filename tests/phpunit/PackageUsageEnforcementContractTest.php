<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PackageUsageEnforcementContractTest extends TestCase
{
    public function testCatalogProductCreateChecksMicrogiftLimit(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/catalog/products.php');

        self::assertIsString($source);
        self::assertStringContainsString('package-entitlements.php', $source);
        self::assertStringContainsString('max_microgifts', $source);
        self::assertStringContainsString('mg_package_require_limit_available', $source);
        self::assertStringContainsString('Product limit reached.', $source);
        self::assertStringContainsString("status<>'archived'", $source);
    }

    public function testMerchantLocationCreateChecksLocationLimit(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/locations.php');

        self::assertIsString($source);
        self::assertStringContainsString('max_locations', $source);
        self::assertStringContainsString('mg_package_require_limit_available', $source);
        self::assertStringContainsString('Location limit reached.', $source);
        self::assertStringContainsString('$isCreate', $source);
    }

    public function testAccountPackageLimitsEndpointExposesUsageAndSendFlags(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/account/package-limits.php');

        self::assertIsString($source);
        foreach (['max_microgifts','max_rewards','max_active_campaigns','max_crm_contacts','monthly_stamps_included','max_locations','max_team_seats'] as $key) {
            self::assertStringContainsString($key, $source);
        }
        self::assertStringContainsString('email_stamps_enabled', $source);
        self::assertStringContainsString('sms_stamps_enabled', $source);
        self::assertStringContainsString('stamp_overage_enabled', $source);
        self::assertStringContainsString('bulk_stamp_purchase_enabled', $source);
    }

    public function testMerchantOverviewIncludesPackageLimitPayload(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/overview.php');

        self::assertIsString($source);
        self::assertStringContainsString('package_limits', $source);
        self::assertStringContainsString('mg_merchant_overview_limit_metric', $source);
        self::assertStringContainsString('mg_user_package_context', $source);
        self::assertStringContainsString('max_locations', $source);
        self::assertStringContainsString('max_team_seats', $source);
    }
}
