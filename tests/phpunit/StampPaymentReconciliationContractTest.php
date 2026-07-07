<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampPaymentReconciliationContractTest extends TestCase
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

    public function testCheckoutQaEndpointProtectsLiveStampPaymentFlow(): void
    {
        $qa = $this->read('api/stamps/checkout-qa.php');

        foreach ([
            'mg_require_api_user',
            'admin.stamps.view',
            'admin.stamps.manage',
            'mg_require_method(\'GET\')',
            'merchant-stamps.php',
            'stamp-checkout.php',
            'assets/js/stamp-checkout.js',
            'api/stamps/checkout-session.php',
            'api/stamps/purchase-complete.php',
            'Continue to secure payment',
            '/api/stamps/checkout-session.php',
            'sandbox-confirm',
            'mg_stamp_purchase_provider_metadata',
            'mg_payment_platform_config',
            'mg_stripe_stub_enabled',
            'verified_webhook_completion',
            'mg_stamp_purchase_complete_verified',
        ] as $marker) {
            self::assertStringContainsString($marker, $qa);
        }
    }

    public function testPurchaseReportReturnsReconciliationFields(): void
    {
        $report = $this->read('api/stamps/purchase-report.php');

        foreach ([
            "LEFT JOIN payment_intents pi ON pi.source_type='stamp_purchase'",
            'reconciliation_state',
            'payment_intent',
            'provider_intent_reference',
            'payment_intent_status',
            'webhook_event',
            'payment_webhook_events',
            'awaiting_webhook',
            'failed_payment',
            'missing_intent',
            'amount_review',
            'ledger_review',
            'schema_ready',
        ] as $marker) {
            self::assertStringContainsString($marker, $report);
        }
    }

    public function testAdminReconciliationPageAndJavascriptExist(): void
    {
        $page = $this->read('stamp-payment-reconciliation.php');
        $js = $this->read('assets/js/admin-stamp-payment-reconciliation.js');

        foreach ([
            'data-stamp-payment-reconciliation-page',
            'Live checkout QA stabilization',
            'Run checkout QA',
            'Reconciliation queue',
            'data-stamp-qa-list',
            'data-stamp-reconciliation-list',
            '/assets/js/admin-stamp-payment-reconciliation.js',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }

        foreach ([
            '/api/stamps/checkout-qa.php',
            '/api/stamps/purchase-report.php',
            'data-stamp-qa-list',
            'data-stamp-reconciliation-list',
            'awaiting_webhook',
            'failed_payment',
            'provider_intent_reference',
            'credited_ledger_entry_id',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }
    }

    public function testExistingAdminStampSurfacesLinkToReconciliation(): void
    {
        $health = $this->read('admin/stamp-health.php');
        $panel = $this->read('includes/admin-stamp-bundles-panel.php');
        $sales = $this->read('assets/js/admin-stamp-sales.js');

        foreach (['/stamp-payment-reconciliation.php', 'Stamp payments'] as $marker) {
            self::assertStringContainsString($marker, $health);
        }

        foreach (['/stamp-payment-reconciliation.php', 'Open reconciliation', 'Reconciliation', 'data-admin-stamp-purchase-list'] as $marker) {
            self::assertStringContainsString($marker, $panel);
        }

        foreach (['payment_intent', 'reconciliation_state', 'provider_key', 'Report unavailable', 'colspan="8"'] as $marker) {
            self::assertStringContainsString($marker, $sales);
        }
    }
}
