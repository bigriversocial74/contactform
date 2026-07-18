<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SelfServePackageFlowContractTest extends TestCase
{
    public function testUpgradeRequestUsesHandoffHelper(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/request-upgrade.php');
        self::assertIsString($source);
        foreach(['mg_subscription_billing_v2_request($pdo, $user, $plan, $billingCycle, $note)','mg_subscription_checkout_try_start($pdo, $user, $request)','mg_subscription_billing_v2_schedule_change','mg_subscription_billing_v2_attach_portal'] as $needle) self::assertStringContainsString($needle,$source);

    }

    public function testEnterprisePathRemainsReviewOnly(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/request-upgrade.php');

        self::assertIsString($source);
        self::assertStringContainsString('enterprise', $source);
        self::assertStringContainsString('submitted for review', $source);
    }

    public function testClientCopyUsesPaymentLanguageForSelfServe(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/subscription-checkout.js');

        self::assertIsString($source);
        self::assertStringContainsString('Continue to payment', $source);
        self::assertStringContainsString('Opening payment', $source);
        self::assertStringContainsString('request-upgrade.php', $source);
        self::assertStringContainsString('checkout.php', $source);
    }
}
