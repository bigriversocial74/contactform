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
            'provider_event_id',
            'awaiting_webhook',
            'failed_payment',
            'paid_uncredited',
            'Paid provider intent, not credited',
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
            'Webhook recovery + provider sync',
            'data-stamp-qa-list',
            'data-stamp-reconciliation-list',
            'data-stamp-reconciliation-filters',
            'paid_uncredited',
            'data-export-stamp-reconciliation',
            '/assets/js/admin-stamp-payment-reconciliation.js',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }

        foreach ([
            '/api/stamps/checkout-qa.php',
            '/api/stamps/purchase-report.php',
            '/api/stamps/reconciliation-action.php',
            '/api/stamps/webhook-recovery.php',
            'data-stamp-qa-list',
            'data-stamp-reconciliation-list',
            'data-stamp-action',
            'data-stamp-recovery',
            'retry_checkout',
            'mark_failed',
            'mark_cancelled',
            'mark_reviewed',
            'sync_provider_status',
            'webhook_detail',
            'reprocess_webhook',
            'flag_paid_uncredited',
            'Export CSV',
            'awaiting_webhook',
            'failed_payment',
            'paid_uncredited',
            'provider_intent_reference',
            'provider_event_id',
            'credited_ledger_entry_id',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }
    }

    public function testReconciliationActionEndpointIsAdminOnlyAndFailClosed(): void
    {
        $endpoint = $this->read('api/stamps/reconciliation-action.php');

        foreach ([
            'mg_require_api_user',
            'admin.stamps.manage',
            'mg_require_method(\'POST\')',
            'mg_require_csrf_for_write',
            'retry_checkout',
            'mark_failed',
            'mark_cancelled',
            'mark_reviewed',
            'mg_stamp_purchase_load_any',
            'mg_stamp_purchase_find_intent',
            'mg_stamp_purchase_create_provider_checkout_session',
            'Credited Stamp purchases cannot be retried',
            'Succeeded provider payments cannot be marked failed',
            'Succeeded provider payments cannot be cancelled',
            'stamps.purchase_reconciliation_',
            'mg_stamp_purchase_payload',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }
    }

    public function testWebhookRecoveryEndpointIsAdminOnlyAndUsesExistingProviderWebhookPaths(): void
    {
        $endpoint = $this->read('api/stamps/webhook-recovery.php');

        foreach ([
            'mg_require_api_user',
            'admin.stamps.manage',
            'mg_require_method(\'POST\')',
            'mg_require_csrf_for_write',
            'webhook_detail',
            'reprocess_webhook',
            'sync_provider_status',
            'flag_paid_uncredited',
            'payment_webhook_events',
            'mg_payment_webhook_identifiers',
            'mg_payment_process_webhook_event',
            'mg_payment_provider_retrieve_intent',
            'mg_payment_normalize_intent_status',
            'paid_uncredited',
            'Only signed webhook events can be reprocessed',
            'Processed webhook events are already complete',
            'Provider reports paid; Stamp purchase still needs verified webhook or admin-only credit review.',
            'stamps.webhook_reprocessed',
            'stamps.provider_status_sync',
            'stamps.paid_uncredited_review_flagged',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }

        self::assertStringNotContainsString('mg_stamp_purchase_complete_verified($pdo', $endpoint, 'Recovery endpoint must not directly credit Stamps during provider sync.');
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
