<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PackageLimitsUiContractTest extends TestCase
{
    public function testMerchantOverviewMountsPackageUsageCards(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/merchant-view.php');

        self::assertIsString($source);
        self::assertStringContainsString('Package usage', $source);
        self::assertStringContainsString('data-package-limit-cards', $source);
        self::assertStringContainsString('/account-subscriptions.php', $source);
    }

    public function testMerchantWorkspaceRendersUsageAndLocksActions(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/merchant-workspace.js');

        self::assertIsString($source);
        self::assertStringContainsString('package_limits', $source);
        self::assertStringContainsString('renderPackageLimitCards', $source);
        self::assertStringContainsString('applyPackageLocks', $source);
        self::assertStringContainsString('limitUpgradeMessage', $source);
        self::assertStringContainsString('is-package-locked', $source);
        foreach (['max_microgifts','max_rewards','max_active_campaigns','max_crm_contacts','max_locations','max_team_seats','monthly_stamps_included'] as $key) {
            self::assertStringContainsString($key, $source);
        }
    }

    public function testLocationAndTeamFormsShowPackageLimitErrorsBeforePost(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/merchant-workspace.js');

        self::assertIsString($source);
        self::assertStringContainsString("metricAtLimit('max_locations')", $source);
        self::assertStringContainsString("metricAtLimit('max_team_seats')", $source);
        self::assertStringContainsString("limitUpgradeMessage('max_locations')", $source);
        self::assertStringContainsString("limitUpgradeMessage('max_team_seats')", $source);
    }

    public function testPackageLimitStylesArePresent(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/css/layout-fixes.css');

        self::assertIsString($source);
        self::assertStringContainsString('mg-package-limit-grid', $source);
        self::assertStringContainsString('mg-package-limit-card', $source);
        self::assertStringContainsString('is-limit-hit', $source);
        self::assertStringContainsString('is-package-locked', $source);
    }
}
