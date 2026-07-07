<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampOperationsDashboardContractTest extends TestCase
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

    public function testOperationsDashboardPageIsReadOnlyOpsSurface(): void
    {
        $page = $this->read('admin/stamp-operations.php');

        foreach ([
            'data-stamp-operations-page',
            'Stamp operations dashboard',
            'Stamp purchase ledger remains the source of truth',
            'data-stamp-ops-count="needs_attention"',
            'data-stamp-ops-count="paid_uncredited"',
            'data-stamp-ops-count="awaiting_webhook"',
            'data-stamp-ops-count="failed_payment"',
            'data-stamp-ops-count="reconciled"',
            'data-stamp-ops-links',
            'data-stamp-ops-risk-list',
            'data-stamp-ops-action-list',
            '/stamp-payment-reconciliation.php?filter=review',
            '/assets/js/admin-stamp-operations-dashboard.js',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
    }

    public function testOperationsDashboardApiIsReadOnlyAndSummarizesLedgerState(): void
    {
        $endpoint = $this->read('api/stamps/operations-dashboard.php');

        foreach ([
            'mg_require_api_user',
            'admin.stamps.view',
            'admin.stamps.manage',
            'mg_require_method(\'GET\')',
            'stamp_purchases',
            'payment_intents',
            'audit_logs',
            'paid_uncredited',
            'awaiting_webhook',
            'failed_payment',
            'amount_review',
            'ledger_review',
            'payment_review',
            'needs_attention',
            'quick_links',
            'risky_records',
            'recent_recovery_actions',
            'source_of_truth',
            'read_only',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }

        self::assertStringNotContainsString('mg_require_csrf_for_write', $endpoint, 'Read-only operations dashboard should not require CSRF.');
        self::assertStringNotContainsString('mg_stamp_purchase_complete_verified', $endpoint, 'Operations dashboard must not credit Stamps.');
        self::assertStringNotContainsString('UPDATE ', $endpoint, 'Operations dashboard must not update records.');
        self::assertStringNotContainsString('INSERT ', $endpoint, 'Operations dashboard must not insert records.');
    }

    public function testOperationsDashboardRuntimeRendersQueuesAndAuditActions(): void
    {
        $js = $this->read('assets/js/admin-stamp-operations-dashboard.js');

        foreach ([
            '/api/stamps/operations-dashboard.php',
            'data-stamp-ops-count',
            'data-stamp-ops-links',
            'data-stamp-ops-risk-list',
            'data-stamp-ops-action-list',
            'source_of_truth',
            'risky_records',
            'recent_recovery_actions',
            'reconciliation_url',
            'Open queue',
            'Review',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }
    }

    public function testReconciliationRuntimeAcceptsLinkedFiltersFromOperationsDashboard(): void
    {
        $js = $this->read('assets/js/admin-stamp-payment-reconciliation.js');

        foreach ([
            'new URLSearchParams',
            "params.get('filter')",
            "params.get('q')",
            'updateFilterButtons',
            'applyFilter',
            'amount_review',
            'ledger_review',
            'payment_review',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }
    }
}
