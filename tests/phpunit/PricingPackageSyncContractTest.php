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
            'Direct Feed Distribution',
            'Engagement Campaigns',
            'Landing Pages',
            'Pre Sale Commerce',
            'Automated Commerce Solutions',
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
            'foreach ($plans as $plan)',
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
            'now read the same package source',
            'data-package-total',
            'implementation_id',
            'pricing_change',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}