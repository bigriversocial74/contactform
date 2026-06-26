<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SubscriptionCheckoutHandoffContractTest extends TestCase
{
    public function testCheckoutEndpointRequiresAuthCsrfAndRequestId(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/subscriptions/checkout.php');

        self::assertIsString($endpoint);
        self::assertStringContainsString("mg_require_method('POST')", $endpoint);
        self::assertStringContainsString('mg_require_api_user()', $endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $endpoint);
        self::assertStringContainsString('mg_subscription_checkout_start', $endpoint);
    }

    public function testCheckoutHelperCreatesStripeSubscriptionSession(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/subscriptions/_checkout_handoff.php');

        self::assertIsString($helper);
        self::assertStringContainsString("'mode' => 'subscription'", $helper);
        self::assertStringContainsString("'/v1/checkout/sessions'", $helper);
        self::assertStringContainsString("'source_type' => 'subscription_package_change'", $helper);
        self::assertStringContainsString("'subscription_data' => ['metadata' => \$metadata]", $helper);
        self::assertStringContainsString("'recurring' => ['interval' => 'month']", $helper);
        self::assertStringContainsString("'package_change_request_id'", $helper);
    }

    public function testSubscriptionCardsUseCheckoutHandoffScript(): void
    {
        $root = dirname(__DIR__, 2);
        $footer = file_get_contents($root . '/includes/footer.php');
        $script = file_get_contents($root . '/assets/js/subscription-checkout.js');

        self::assertIsString($footer);
        self::assertIsString($script);
        self::assertStringContainsString('/assets/js/subscription-checkout.js', $footer);
        self::assertStringContainsString('/api/subscriptions/request-upgrade.php', $script);
        self::assertStringContainsString('/api/subscriptions/checkout.php', $script);
        self::assertStringContainsString('stopImmediatePropagation', $script);
    }
}
