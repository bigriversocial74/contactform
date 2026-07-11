<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PaymentsUiCleanupContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    public function testMerchantPaymentsUsesOneConciseTabbedInterface(): void
    {
        $view=file_get_contents($this->root.'/includes/merchant-payments-view.php');
        self::assertIsString($view);

        foreach([
            'data-payments-tab="methods"',
            'data-payments-tab="orders"',
            'data-payments-tab="refunds"',
            'data-payments-tab="payouts"',
            'data-payments-tab="disputes"',
            'data-payments-tab="reconciliation"',
            'data-financial-kpis',
            'data-cash-payment-toggle',
            'data-stripe-payment-toggle',
        ] as $needle){
            self::assertStringContainsString($needle,$view);
        }

        self::assertSame(6,substr_count($view,'data-payments-tab='));
        self::assertStringNotContainsString('mg-payments-commandbar',$view);
        self::assertStringNotContainsString('mg-payments-hero',$view);
        self::assertStringNotContainsString('Checkout readiness center',$view);
        self::assertStringNotContainsString('mg-payments-side',$view);
        self::assertStringNotContainsString('<aside',$view);
        self::assertStringNotContainsString('data-financial-tab=',$view);
    }

    public function testMerchantPaymentStatsStayInOneHorizontalRow(): void
    {
        $css=file_get_contents($this->root.'/assets/css/merchant-payments.css');
        self::assertIsString($css);
        self::assertStringContainsString('grid-template-columns:repeat(5,minmax(180px,1fr))!important',$css);
        self::assertStringContainsString('overflow-x:auto',$css);
        self::assertStringContainsString('min-width:180px',$css);
        self::assertStringNotContainsString('.mg-payments-side',$css);
        self::assertStringNotContainsString('.mg-payments-hero',$css);
    }

    public function testMerchantMethodPreferencesIncludeCashAndActualStripeState(): void
    {
        $api=file_get_contents($this->root.'/api/merchant/payment-methods.php');
        $js=file_get_contents($this->root.'/assets/js/merchant-payments.js');
        $page=file_get_contents($this->root.'/merchant-payments.php');
        self::assertIsString($api);
        self::assertIsString($js);
        self::assertIsString($page);

        foreach([
            "'cash' => [",
            "'stripe' => [",
            '$cashEnabled',
            '$stripeEnabled',
            'mg_payment_connect_status',
            "? 'ready'",
            "? 'pending_onboarding' : 'not_connected'",
            "'stripe_onboarding_connected' => !empty(\$stripeAccount['connected'])",
            "'stripe_ready' => !empty(\$stripeAccount['ready'])",
        ] as $needle){
            self::assertStringContainsString($needle,$api);
        }

        self::assertStringContainsString('stripe_enabled',$js);
        self::assertStringContainsString('cash_enabled',$js);
        self::assertStringNotContainsString('/assets/js/merchant-connect.js',$page);
    }

    public function testAdminPaymentsUsesFocusedMethodConfigurationAndReadinessTabs(): void
    {
        $page=file_get_contents($this->root.'/admin-payments.php');
        $js=file_get_contents($this->root.'/assets/js/admin-payments.js');
        self::assertIsString($page);
        self::assertIsString($js);

        foreach([
            'data-admin-payment-tab="methods"',
            'data-admin-payment-tab="stripe"',
            'data-admin-payment-tab="readiness"',
            'data-admin-cash-payment-toggle',
            'data-admin-stripe-payment-toggle',
            'data-payment-settings-form',
            'data-payment-checks',
        ] as $needle){
            self::assertStringContainsString($needle,$page);
        }

        self::assertSame(3,substr_count($page,'data-admin-payment-tab='));
        self::assertStringNotContainsString('mg-payment-hero',$page);
        self::assertStringContainsString('activatePage',$js);
        self::assertStringContainsString("saveSettings('method')",$js);
    }
}
