<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampPurchaseCheckoutHardeningTest extends TestCase
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

    public function testMerchantStampPurchaseCreatesCheckoutIntent(): void
    {
        $purchase = $this->read('api/stamps/purchase.php');
        $merchantJs = $this->read('assets/js/merchant-stamps.js');

        foreach ([
            'mg_stamp_purchase_create_intent',
            'mg_stamp_purchase_payload',
            'stamps.purchase_checkout_created',
            'checkout_created',
            'Complete payment before ledger credit',
        ] as $marker) {
            self::assertStringContainsString($marker, $purchase);
        }

        self::assertStringNotContainsString('data-confirm-stamps', $merchantJs);
        self::assertStringNotContainsString('data-complete-purchase', $merchantJs);
        self::assertStringNotContainsString('/api/stamps/purchase-complete.php', $merchantJs);
        self::assertStringContainsString('/stamp-checkout.php?purchase=', $merchantJs);
        self::assertStringContainsString('Checkout status', $merchantJs);
    }

    public function testManualCompletionIsAdminOnly(): void
    {
        $complete = $this->read('api/stamps/purchase-complete.php');

        foreach ([
            'admin.stamps.manage',
            'Permission denied.',
            'account_user_id is required',
            'mg_stamp_purchase_complete_verified',
            'stamps.purchase_admin_completed',
            'Stamp purchase manually completed by admin.',
        ] as $marker) {
            self::assertStringContainsString($marker, $complete);
        }
    }

    public function testProviderWebhookCreditsStampPurchase(): void
    {
        $webhook = $this->read('api/payments/_webhook.php');
        $purchases = $this->read('api/stamps/_purchases.php');

        foreach ([
            "require_once dirname(__DIR__) . '/stamps/_purchases.php'",
            'mg_payment_webhook_find_stamp_purchase',
            'mg_payment_webhook_assert_stamp_purchase_amount',
            'stamp_purchase_id',
            'mg_stamp_purchase_complete_verified',
        ] as $marker) {
            self::assertStringContainsString($marker, $webhook);
        }

        foreach ([
            'mg_stamp_purchase_create_intent',
            "source_type' => 'stamp_purchase'",
            'mg_payment_create_source_intent',
            'mg_stamp_purchase_complete_verified',
            'completion_source',
            'verified_payment',
            '/stamp-checkout.php?purchase=',
        ] as $marker) {
            self::assertStringContainsString($marker, $purchases);
        }
    }

    public function testStampProviderCheckoutEndpointExistsAndUsesOwnerScope(): void
    {
        $endpoint = $this->read('api/stamps/checkout-session.php');
        $purchases = $this->read('api/stamps/_purchases.php');

        foreach ([
            'mg_require_api_user',
            'mg_require_method(\'POST\')',
            'mg_require_csrf_for_write',
            'mg_stamp_purchase_load($pdo, (int)$user[\'id\']',
            'mg_stamp_purchase_find_intent',
            'mg_stamp_purchase_create_provider_checkout_session',
            'stamps.purchase_provider_checkout_created',
            'source_type',
            'stamp_purchase',
            'checkout_url',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }

        foreach ([
            'mg_stamp_purchase_provider_metadata',
            'source_reference',
            'stamp_purchase_id',
            'payment_intent_id',
            'account_user_id',
            'bundle_key',
            'stamps',
            'price_cents',
            'currency',
            'client_reference_id',
            'payment_intent_data',
            '/v1/checkout/sessions',
            'stamp_checkout:',
        ] as $marker) {
            self::assertStringContainsString($marker, $purchases);
        }
    }

    public function testStampCheckoutPageAndStatusApiExist(): void
    {
        $page = $this->read('stamp-checkout.php');
        $js = $this->read('assets/js/stamp-checkout.js');
        $api = $this->read('api/stamps/purchase-status.php');

        foreach (['data-stamp-checkout','Complete Stamp purchase','/assets/js/stamp-checkout.js'] as $marker) {
            self::assertStringContainsString($marker, $page);
        }

        foreach (['data-sidebar-contract="mg-app-sidebar"', "'/includes/app-sidebar.php'", 'mg-app-workspace'] as $marker) {
            self::assertStringContainsString($marker, $page);
        }

        foreach ([
            '/api/stamps/purchase-status.php?purchase_id=',
            '/api/stamps/checkout-session.php',
            'Continue to secure payment',
            'Provider checkout not configured',
            'Redirecting to secure payment',
            'verified webhook',
            'Ledger entry',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }

        self::assertStringNotContainsString('/api/stamps/purchase-complete.php', $js);
        self::assertStringNotContainsString('sandbox-confirm', $js);
        self::assertStringNotContainsString('Complete sandbox payment', $js);

        foreach (['mg_stamp_purchase_load','mg_stamp_purchase_find_intent','mg_stamp_purchase_payload'] as $marker) {
            self::assertStringContainsString($marker, $api);
        }
    }
}
