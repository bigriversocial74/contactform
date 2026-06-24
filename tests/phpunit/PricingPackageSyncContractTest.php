<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class PricingPackageSyncContractTest extends TestCase
{
    public function testSharedPricingPackageSourceExists(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/includes/pricing-packages.php');
        self::assertIsString($source);
        foreach([
            'function mg_pricing_packages(): array',
            'function mg_public_pricing_packages(): array',
            'function mg_pricing_package_summary(): array',
            'Promotional CRM',
            'paid Microgifts',
            'promotional Rewards',
            'monthly Stamps',
            'max_microgifts',
            'max_rewards',
            'monthly_stamps_included',
            'bulk_stamp_purchase_enabled',
            'email_stamps_enabled',
            'sms_stamps_enabled',
            'PKG-PRICING-GROWTH',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testPublicPricingPageReadsSharedPackageSource(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/pricing.php');
        self::assertIsString($source);
        foreach([
            "require_once __DIR__ . '/includes/pricing-packages.php'",
            '$plans = mg_public_pricing_packages();',
            '$summary = mg_pricing_package_summary();',
            'data-package-id',
            'Admin synced source',
            "foreach ($plans as $plan)",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString("['Starter','$29'",$source);
    }

    public function testAdminPackageModerationReadsSamePackageSource(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/admin/package-moderation.php');
        self::assertIsString($source);
        foreach([
            "require_once dirname(__DIR__) . '/includes/pricing-packages.php'",
            '$packages = mg_pricing_packages();',
            '$summary = mg_pricing_package_summary();',
            'Package moderation',
            'Microgifts are paid products',
            'Rewards are promotions',
            'Send Stamp',
            'monthly_stamps_included',
            'bulk_stamp_purchase_enabled',
            'stamp_limit_change',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
