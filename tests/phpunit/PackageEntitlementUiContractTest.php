<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PackageEntitlementUiContractTest extends TestCase
{
    public function testEntitlementHelperDefinesFreeAndMerchantPackageContext(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/includes/package-entitlements.php');

        self::assertIsString($helper);
        self::assertStringContainsString('mg_package_entitlement_free_context', $helper);
        self::assertStringContainsString("'package_id' => 'free'", $helper);
        self::assertStringContainsString("'merchant_access' => false", $helper);
        self::assertStringContainsString('mg_user_package_context', $helper);
        self::assertStringContainsString('platform_account_subscriptions', $helper);
        self::assertStringContainsString('mg_package_limit_allows_create', $helper);
        self::assertStringContainsString('max_active_campaigns', $helper);
        self::assertStringContainsString('max_rewards', $helper);
    }

    public function testHeaderAndMenusUsePackageAccessFlags(): void
    {
        $root = dirname(__DIR__, 2);
        $header = file_get_contents($root . '/includes/header.php');
        $loggedInMenu = file_get_contents($root . '/includes/header-templates/logged-in.php');
        $createMenu = file_get_contents($root . '/includes/header-templates/create-menu.php');
        $agentSidebar = file_get_contents($root . '/includes/agent-sidebar.php');
        $merchantWorkspace = file_get_contents($root . '/includes/merchant-workspace.php');

        self::assertIsString($header);
        self::assertIsString($loggedInMenu);
        self::assertIsString($createMenu);
        self::assertIsString($agentSidebar);
        self::assertIsString($merchantWorkspace);

        self::assertStringContainsString('mg_user_package_context', $header);
        self::assertStringContainsString('$can_merchant_nav', $header);
        self::assertStringContainsString('data-package-id', $header);
        self::assertStringContainsString('Commerce center', $loggedInMenu);
        self::assertStringContainsString('if ($can_merchant_nav)', $loggedInMenu);
        self::assertStringContainsString('$can_create_campaigns', $createMenu);
        self::assertStringContainsString('$can_create_rewards', $createMenu);
        self::assertStringContainsString('$can_manage_locations', $createMenu);
        self::assertStringContainsString("'visible' => $canMerchantNav", $agentSidebar);
        self::assertStringContainsString('data-merchant-access', $merchantWorkspace);
        self::assertStringContainsString('Merchant workspace is not active.', $merchantWorkspace);
    }

    public function testMerchantApisEnforcePackageAccessAndLimits(): void
    {
        $root = dirname(__DIR__, 2);
        $merchantCore = file_get_contents($root . '/api/merchant/_merchant.php');
        $campaigns = file_get_contents($root . '/api/merchant/campaigns.php');
        $rewards = file_get_contents($root . '/api/merchant/reward-templates.php');
        $billing = file_get_contents($root . '/api/subscriptions/_package_billing.php');

        self::assertIsString($merchantCore);
        self::assertIsString($campaigns);
        self::assertIsString($rewards);
        self::assertIsString($billing);

        self::assertStringContainsString('mg_merchant_require_permission', $merchantCore);
        self::assertStringContainsString('mg_package_require_merchant_access', $merchantCore);
        self::assertStringContainsString('mg_merchant_require_permission', $campaigns);
        self::assertStringContainsString('max_active_campaigns', $campaigns);
        self::assertStringContainsString('mg_package_require_limit_available', $campaigns);
        self::assertStringContainsString('mg_reward_templates_require_access', $rewards);
        self::assertStringContainsString('max_rewards', $rewards);
        self::assertStringContainsString('mg_package_require_limit_available', $rewards);
        self::assertStringContainsString('mg_platform_account_subscription_grant_merchant_role', $billing);
        self::assertStringContainsString("slug='merchant'", $billing);
    }
}
