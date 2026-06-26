<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/evidence-export.php';

final class ShareMarketEvidenceExportTest extends TestCase
{
    public function testExportHashIsStableHashString(): void
    {
        $hash = mg_share_market_export_hash(['attempt' => 'abc', 'score' => 100]);

        self::assertSame(64, strlen($hash));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function testExportCountsGroupRowsByStatus(): void
    {
        $counts = mg_share_market_export_counts([
            ['status' => 'approved'],
            ['status' => 'approved'],
            ['status' => 'rejected'],
        ], 'status');

        self::assertSame(['approved' => 2, 'rejected' => 1], $counts);
    }

    public function testExportRowsOnlyKeepsRequestedFields(): void
    {
        $rows = mg_share_market_export_rows([
            ['public_id' => 'one', 'status' => 'approved', 'private_note' => 'hidden'],
        ], ['public_id', 'status']);

        self::assertSame([['public_id' => 'one', 'status' => 'approved']], $rows);
    }

    public function testSnapshotHashesAreTrimmedForExport(): void
    {
        $rows = mg_share_market_export_snapshot_hashes([
            ['public_id' => 's1', 'snapshot_type' => 'target_snapshot', 'payload_hash' => str_repeat('a', 64), 'created_at' => '2026-06-26', 'snapshot_json' => ['full' => true]],
        ]);

        self::assertSame('s1', $rows[0]['public_id']);
        self::assertSame('target_snapshot', $rows[0]['snapshot_type']);
        self::assertArrayNotHasKey('snapshot_json', $rows[0]);
    }

    public function testExportApiIsGetOnlyAndPermissioned(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/evidence-export.php');

        self::assertStringContainsString("mg_require_method('GET')", $api);
        self::assertStringContainsString('Share Market Admin permission is required', $api);
        self::assertStringContainsString('mg_share_market_evidence_export', $api);
    }

    public function testAuditConsoleIncludesExportPanelAndDownload(): void
    {
        $partial = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/account/share-market-execution-audit.php');
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');

        self::assertStringContainsString('data-share-export-panel', $partial);
        self::assertStringContainsString('data-share-export-download', $partial);
        self::assertStringContainsString('/api/admin/share-market/evidence-export.php', $js);
        self::assertStringContainsString('downloadExport', $js);
    }

    public function testNoRunnerEndpointWasAddedByEvidenceExportPhase(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
