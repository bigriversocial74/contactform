<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantModuleLimitStatesContractTest extends TestCase
{
    public function testMerchantWorkspaceLoadsModuleLimitAssets(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/merchant-workspace.php');

        self::assertIsString($source);
        self::assertStringContainsString('merchant-module-limits.css', $source);
        self::assertStringContainsString('merchant-module-limits.js', $source);
        self::assertStringContainsString('$canMerchantAccess', $source);
        self::assertStringContainsString('data-merchant-view', $source);
    }

    public function testModuleLimitScriptCoversMerchantModules(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/merchant-module-limits.js');

        self::assertIsString($source);
        self::assertStringContainsString('/api/account/package-limits.php', $source);
        self::assertStringContainsString('data-module-limit-banner', $source);
        self::assertStringContainsString('is-package-locked', $source);
        foreach (['products','reward_templates','campaigns','merchant_crm','stamps','campaign_stamps'] as $view) {
            self::assertStringContainsString($view, $source);
        }
        foreach (['max_microgifts','max_rewards','max_active_campaigns','max_crm_contacts','monthly_stamps_included'] as $limit) {
            self::assertStringContainsString($limit, $source);
        }
    }

    public function testModuleLimitScriptLocksExpectedActions(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/merchant-module-limits.js');

        self::assertIsString($source);
        foreach (['a[href="/build.php"]','a[href="#reward-builder"]','a[href="#campaign-builder"]','data-crm-bulk-action="reward"','data-crm-action="reward"'] as $selector) {
            self::assertStringContainsString($selector, $source);
        }
        self::assertStringContainsString('data-stage12-template-status', $source);
        self::assertStringContainsString('data-stage12-campaign-status', $source);
        self::assertStringContainsString('Upgrade Package', $source);
    }

    public function testModuleLimitStylesArePresent(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/css/merchant-module-limits.css');

        self::assertIsString($source);
        self::assertStringContainsString('mg-module-limit-banner', $source);
        self::assertStringContainsString('is-limit', $source);
        self::assertStringContainsString('is-package-locked', $source);
    }
}
