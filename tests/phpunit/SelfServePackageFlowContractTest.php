<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SelfServePackageFlowContractTest extends TestCase
{
    public function testUpgradeRequestUsesHandoffHelper(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/subscriptions/request-upgrade.php');

        self::assertIsString($source);
        self::assertStringContainsString('_checkout_handoff.php', $source);
        self::assertStringContainsString('mg_subscription_package_change_request($pdo, $user, $plan, $note)', $source);
        self::assertStringContainsString('mg_subscription_checkout_try_start($pdo, $user, $request)', $source);
        self::assertStringContainsString('checkout_started', $source);
        self::assertStringContainsString('checkout_attempted', $source);
        self::assertStringContainsString('checkout_error', $source);
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
        self::assertStringContainsString('Continue to checkout', $source);
        self::assertStringContainsString('Opening checkout', $source);
        self::assertStringContainsString('request-upgrade.php', $source);
        self::assertStringContainsString('checkout.php', $source);
    }
}
