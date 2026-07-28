<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SubscriptionCartRoutingContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testPlanSelectionRoutesToSubscriptionCartBeforeBillingRequest(): void
    {
        $page = $this->source('account-subscriptions.php');
        $routing = $this->source('assets/js/subscription-cart-routing-v1.js');

        self::assertStringContainsString('/assets/js/subscription-cart-routing-v1.js?v=1.0.0', $page);
        self::assertStringContainsString("return '/cart.php?' + params.toString();", $routing);
        self::assertStringContainsString("window.addEventListener('click', route, true);", $routing);
        self::assertStringNotContainsString('/api/subscriptions/request-upgrade.php', $routing);
    }

    public function testSubscriptionCartCreatesBillingRequestOnlyFromCheckoutButton(): void
    {
        $cart = $this->source('cart.php');
        $checkout = $this->source('assets/js/subscription-cart-checkout-v1.js');

        self::assertStringContainsString('data-subscription-cart', $cart);
        self::assertStringContainsString('data-subscription-cart-checkout', $cart);
        self::assertStringContainsString("MG.post('/api/subscriptions/request-upgrade.php'", $checkout);
        self::assertStringContainsString("source: 'subscription_cart'", $checkout);
        self::assertStringContainsString('window.location.assign(checkoutUrl);', $checkout);
        self::assertStringNotContainsString('window.alert', $checkout);
        self::assertStringNotContainsString('MG.toast', $checkout);
    }
}
