<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PackageFlowQaContractTest extends TestCase
{
    public function testFreeToPaidActivationChainIsCovered(): void
    {
        $request = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/request-upgrade.php');
        $changes = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_package_changes.php');
        $webhook = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_package_webhook.php');
        $billing = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_package_billing.php');
        $entitlements = file_get_contents(dirname(__DIR__, 2) . '/includes/package-entitlements.php');
        $status = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/package-change-status.php');

        self::assertIsString($request);
        self::assertIsString($changes);
        self::assertIsString($webhook);
        self::assertIsString($billing);
        self::assertIsString($entitlements);
        self::assertIsString($status);

        self::assertStringContainsString('mg_subscription_checkout_try_start', $request);
        self::assertStringContainsString('pending_payment', $changes);
        self::assertStringContainsString('checkout.session.completed', $webhook);
        self::assertStringContainsString('mg_platform_account_subscription_upsert', $webhook);
        self::assertStringContainsString('mg_platform_account_subscription_grant_merchant_role', $billing);
        self::assertStringContainsString("'package_id' => 'free'", $entitlements);
        self::assertStringContainsString("'merchant_access' => false", $entitlements);
        self::assertStringContainsString('active_access', $status);
        self::assertStringContainsString('payment_pending', $status);
    }

    public function testPackageUsageEndpointCoversAllEnforcedLimits(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/account/package-limits.php');

        self::assertIsString($source);
        foreach ([
            'max_microgifts' => 'catalog_products',
            'max_rewards' => 'reward_templates',
            'max_active_campaigns' => 'campaigns',
            'max_crm_contacts' => 'campaign_contacts',
            'monthly_stamps_included' => 'wallet_items',
            'max_locations' => 'merchant_locations',
            'max_team_seats' => 'merchant_team_members',
        ] as $limit => $table) {
            self::assertStringContainsString($limit, $source);
            self::assertStringContainsString($table, $source);
        }
        foreach (['email_stamps_enabled','sms_stamps_enabled','stamp_overage_enabled','bulk_stamp_purchase_enabled'] as $channel) {
            self::assertStringContainsString($channel, $source);
        }
    }

    public function testBackendLimitEnforcementContractsRemainConnected(): void
    {
        $products = file_get_contents(dirname(__DIR__, 2) . '/api/catalog/products.php');
        $locations = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/locations.php');
        $team = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/team.php');
        $campaigns = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/campaigns.php');
        $rewards = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/reward-templates.php');
        $campaignLimits = file_get_contents(dirname(__DIR__, 2) . '/api/public/campaigns/_limits.php');
        $crmGift = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/crm-send-gift.php');
        $crmInvite = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/crm-send-reward-invite.php');

        foreach ([$products,$locations,$team,$campaigns,$rewards,$campaignLimits,$crmGift,$crmInvite] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('max_microgifts', $products);
        self::assertStringContainsString('max_locations', $locations);
        self::assertStringContainsString('max_team_seats', $team);
        self::assertStringContainsString('max_active_campaigns', $campaigns);
        self::assertStringContainsString('max_rewards', $rewards);
        self::assertStringContainsString('max_crm_contacts', $campaignLimits);
        self::assertStringContainsString('monthly_stamps_included', $campaignLimits);
        self::assertStringContainsString('monthly_stamps_included', $crmGift);
        self::assertStringContainsString('email_stamps_enabled', $crmGift);
        self::assertStringContainsString('email_stamps_enabled', $crmInvite);
    }

    public function testPackageLimitUiReappliesLocksForDynamicActions(): void
    {
        $overview = file_get_contents(dirname(__DIR__, 2) . '/assets/js/merchant-workspace.js');
        $module = file_get_contents(dirname(__DIR__, 2) . '/assets/js/merchant-module-limits.js');
        $activation = file_get_contents(dirname(__DIR__, 2) . '/assets/js/subscription-activation-status.js');
        $merchantView = file_get_contents(dirname(__DIR__, 2) . '/includes/merchant-view.php');

        self::assertIsString($overview);
        self::assertIsString($module);
        self::assertIsString($activation);
        self::assertIsString($merchantView);

        self::assertStringContainsString('data-package-limit-cards', $merchantView);
        self::assertStringContainsString('applyPackageLocks', $overview);
        self::assertStringContainsString('MutationObserver', $module);
        self::assertStringContainsString('observeDynamicActions', $module);
        self::assertStringContainsString('/api/account/package-limits.php', $module);
        self::assertStringContainsString('data-crm-action="reward"', $module);
        self::assertStringContainsString('/api/subscriptions/package-change-status.php', $activation);
    }
}
