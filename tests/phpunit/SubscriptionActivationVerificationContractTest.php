<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SubscriptionActivationVerificationContractTest extends TestCase
{
    public function testCheckoutWebhookActivatesPlatformSubscriptionAndClosesRequest(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_package_webhook.php');

        self::assertIsString($source);
        self::assertStringContainsString('checkout.session.completed', $source);
        self::assertStringContainsString('subscription_package_change', $source);
        self::assertStringContainsString('mg_platform_account_subscription_upsert', $source);
        self::assertStringContainsString("status='completed'", $source);
        self::assertStringContainsString('checkout_url=NULL', $source);
        self::assertStringContainsString('platform_account_subscription_id', $source);
        self::assertStringContainsString('subscription.package_checkout_completed', $source);
    }

    public function testPlatformSubscriptionUpsertGrantsWorkspaceRole(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/_package_billing.php');

        self::assertIsString($source);
        self::assertStringContainsString('mg_platform_account_subscription_grant_merchant_role', $source);
        self::assertStringContainsString("roles WHERE slug='merchant'", $source);
        self::assertStringContainsString("status='active'", $source);
        self::assertStringContainsString('mg_platform_account_subscription_snapshot', $source);
    }

    public function testFreeUsersDoNotReceiveWorkspaceAccessBeforeActivation(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/package-entitlements.php');

        self::assertIsString($source);
        self::assertStringContainsString("'package_id' => 'free'", $source);
        self::assertStringContainsString("'merchant_access' => false", $source);
        self::assertStringContainsString('mg_package_entitlement_active_statuses', $source);
        self::assertStringContainsString("'active', 'trialing', 'cancel_pending', 'past_due'", $source);
        self::assertStringContainsString("'merchant_access' => $active && $packageId !== 'free'", $source);
    }

    public function testPackageChangeStatusReportsActivationState(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/package-change-status.php');

        self::assertIsString($source);
        self::assertStringContainsString('package-entitlements.php', $source);
        self::assertStringContainsString('mg_user_package_context', $source);
        self::assertStringContainsString('activation', $source);
        self::assertStringContainsString('workspace_access', $source);
        self::assertStringContainsString('payment_pending', $source);
        self::assertStringContainsString('review_pending', $source);
    }

    public function testSubscriptionPageLoadsActivationStatusScript(): void
    {
        $page = file_get_contents(dirname(__DIR__, 2) . '/account-subscriptions.php');
        $script = file_get_contents(dirname(__DIR__, 2) . '/assets/js/subscription-activation-status.js');

        self::assertIsString($page);
        self::assertIsString($script);
        self::assertStringContainsString('subscription-activation-status.js', $page);
        self::assertStringContainsString('</body>', $page);
        self::assertStringContainsString('/api/subscriptions/package-change-status.php', $script);
        self::assertStringContainsString('checkout', $script);
        self::assertStringContainsString('pending_payment', $script);
        self::assertStringContainsString('workspace_access', $script);
    }
}
