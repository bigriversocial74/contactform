<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampMonthlyCloseContractTest extends TestCase
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

    public function testMonthlyClosePageProvidesPreviewAndExportLinks(): void
    {
        $page = $this->read('admin/stamp-monthly-close.php');

        foreach ([
            'data-stamp-monthly-close-page',
            'Stamp ledger export + monthly close',
            'data-stamp-close-period',
            'data-load-stamp-close',
            'data-stamp-close-count="entries"',
            'data-stamp-close-count="accounts"',
            'data-stamp-close-count="credits"',
            'data-stamp-close-count="debits"',
            'data-stamp-close-count="exceptions"',
            'data-stamp-close-export="ledger"',
            'data-stamp-close-export="reconciliation"',
            'data-stamp-close-ledger-summary',
            'data-stamp-close-exceptions',
            'data-stamp-close-recent-ledger',
            '/assets/js/admin-stamp-monthly-close.js',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
    }

    public function testMonthlyCloseReportApiIsReadOnlyAndUsesLedgerSources(): void
    {
        $endpoint = $this->read('api/stamps/monthly-close-report.php');

        foreach ([
            'mg_require_api_user',
            'admin.stamps.view',
            'admin.stamps.manage',
            'mg_require_method(\'GET\')',
            'period must be YYYY-MM',
            'stamp_ledger_entries',
            'account_stamp_balances',
            'stamp_purchases',
            'payment_intents',
            'ledger_totals',
            'ledger_summary',
            'balance_summary',
            'purchase_summary',
            'reconciliation_summary',
            'exceptions',
            'recent_ledger_entries',
            'export_urls',
            'source_of_truth',
            'read_only',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }

        self::assertStringNotContainsString('mg_require_csrf_for_write', $endpoint, 'Read-only monthly close report should not require CSRF.');
        self::assertStringNotContainsString('mg_stamp_purchase_complete_verified', $endpoint, 'Monthly close report must not credit Stamps.');
        self::assertStringNotContainsString('UPDATE ', $endpoint, 'Monthly close report must not update records.');
        self::assertStringNotContainsString('INSERT ', $endpoint, 'Monthly close report must not insert records.');
    }

    public function testLedgerExportEndpointStreamsLedgerAndReconciliationCsv(): void
    {
        $endpoint = $this->read('api/stamps/ledger-export.php');

        foreach ([
            'mg_require_api_user',
            'admin.stamps.view',
            'admin.stamps.manage',
            'mg_require_method(\'GET\')',
            'type must be ledger or reconciliation',
            'Content-Type: text/csv',
            'Content-Disposition: attachment',
            'fputcsv',
            'stamp-ledger-',
            'stamp-reconciliation-',
            'entry_id',
            'account_user_id',
            'delta',
            'balance_after',
            'reconciliation_state',
            'paid_uncredited',
            'amount_review',
            'ledger_review',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }

        self::assertStringNotContainsString('mg_require_csrf_for_write', $endpoint, 'Read-only export endpoint should not require CSRF.');
        self::assertStringNotContainsString('mg_stamp_purchase_complete_verified', $endpoint, 'Export endpoint must not credit Stamps.');
        self::assertStringNotContainsString('UPDATE ', $endpoint, 'Export endpoint must not update records.');
        self::assertStringNotContainsString('INSERT ', $endpoint, 'Export endpoint must not insert records.');
    }

    public function testMonthlyCloseRuntimeRendersClosePreview(): void
    {
        $js = $this->read('assets/js/admin-stamp-monthly-close.js');

        foreach ([
            '/api/stamps/monthly-close-report.php?period=',
            'data-stamp-close-count',
            'data-stamp-close-export',
            'data-stamp-close-ledger-summary',
            'data-stamp-close-exceptions',
            'data-stamp-close-recent-ledger',
            'export_urls',
            'ledger_summary',
            'exceptions',
            'recent_ledger_entries',
            'source_of_truth',
            'Review',
        ] as $marker) {
            self::assertStringContainsString($marker, $js);
        }
    }

    public function testOperationsDashboardLinksToMonthlyClose(): void
    {
        $operations = $this->read('admin/stamp-operations.php');
        self::assertStringContainsString('/admin/stamp-monthly-close.php', $operations);
        self::assertStringContainsString('Monthly close', $operations);
    }
}
