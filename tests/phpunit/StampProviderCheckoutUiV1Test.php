<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampProviderCheckoutUiV1Test extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root() . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testStampProviderCheckoutApiReturnsProviderPayload(): void
    {
        $api = $this->read('api/stamps/provider-checkout.php');
        $purchases = $this->read('api/stamps/_purchases.php');

        foreach ([
            'mg_stamp_purchase_provider_checkout_payload',
            'provider_checkout',
            'provider_key',
            'client_secret',
            'publishable_key',
            'can_pay_with_stripe',
            'can_confirm_sandbox',
        ] as $marker) {
            self::assertStringContainsString($marker, $api . "\n" . $purchases);
        }

        foreach ([
            'mg_stamp_purchase_provider_key',
            "return 'stripe'",
            "return 'sandbox'",
            'Live Stamp purchases require Stripe platform checkout credentials',
            'mg_payment_provider_retrieve_intent',
            'mg_payment_platform_config',
        ] as $marker) {
            self::assertStringContainsString($marker, $purchases);
        }

        self::assertStringNotContainsString("provider_key' => mg_payment_checkout_provider_key($pdo, null)", $purchases);
    }

    public function testStampCheckoutUiLoadsProviderPaymentFlow(): void
    {
        $page = $this->read('stamp-checkout.php');
        $js = $this->read('assets/js/stamp-checkout.js');

        foreach ([
            'data-sidebar-contract="mg-app-sidebar"',
            "'/assets/js/stamp-checkout.js?v=20260707-provider-checkout'",
            'Pay through the configured provider',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }

        foreach ([
            '/api/stamps/provider-checkout.php?purchase_id=',
            'https://js.stripe.com/v3/',
            'stamp-stripe-payment-element',
            'stripe.confirmPayment',
            'Pay securely with Stripe',
            '/api/stamps/sandbox-confirm.php',
            'Complete sandbox payment',
            'Ledger credit will post after verified webhook confirmation',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }
    }

    public function testSandboxConfirmationIsTestModeOnlyAndIntentBound(): void
    {
        $sandbox = $this->read('api/stamps/sandbox-confirm.php');

        foreach ([
            'mg_payment_is_live()',
            'Sandbox Stamp checkout confirmation is unavailable in live mode.',
            "provider_key'] !== 'sandbox'",
            'mg_stamp_purchase_complete_verified',
            'stamps.purchase_sandbox_completed',
            'Stamp purchase payment intent does not match purchase snapshot',
        ] as $marker) {
            self::assertStringContainsString($marker, $sandbox);
        }
    }
}
