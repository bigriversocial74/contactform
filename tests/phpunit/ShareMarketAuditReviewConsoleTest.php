<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/execution-audit-review.php';

final class ShareMarketAuditReviewConsoleTest extends TestCase
{
    public function testReviewStatusesIncludeAuditScaffoldStates(): void
    {
        $statuses = mg_share_market_audit_review_statuses();

        self::assertContains('created', $statuses);
        self::assertContains('preflight_ready', $statuses);
        self::assertContains('blocked_by_gate', $statuses);
        self::assertContains('reconciliation_mismatch', $statuses);
        self::assertContains('failed', $statuses);
    }

    public function testReviewLimitIsBounded(): void
    {
        self::assertSame(25, mg_share_market_audit_review_limit(0));
        self::assertSame(1, mg_share_market_audit_review_limit(1));
        self::assertSame(100, mg_share_market_audit_review_limit(1000));
    }

    public function testDecodeRowDecodesJsonFields(): void
    {
        $row = mg_share_market_audit_review_decode_row([
            'release_gate_json' => '{"blocked":true}',
            'simulator_json' => '{"executed":false}',
            'metadata_json' => '{"phase":"phase_11_audit_scaffolding"}',
        ]);

        self::assertTrue($row['release_gate_json']['blocked']);
        self::assertFalse($row['simulator_json']['executed']);
        self::assertSame('phase_11_audit_scaffolding', $row['metadata_json']['phase']);
    }

    public function testConsolePageLoadsAuditAssets(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/account-share-market-execution-audit.php');

        self::assertStringContainsString('/assets/js/share-market-execution-audit.js', $page);
        self::assertStringContainsString('share-market-execution-audit.php', $page);
        self::assertStringContainsString('share_market.admin', $page);
    }

    public function testConsolePartialIsReadOnlyCopy(): void
    {
        $partial = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/account/share-market-execution-audit.php');

        self::assertStringContainsString('data-share-audit-root', $partial);
        self::assertStringContainsString('Read-only console', $partial);
        self::assertStringContainsString('data-share-audit-list', $partial);
        self::assertStringContainsString('data-share-audit-modal', $partial);
    }

    public function testAuditReviewApisAreGetOnly(): void
    {
        $list = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/execution-audit-list.php');
        $detail = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/execution-audit-detail.php');

        self::assertStringContainsString("mg_require_method('GET')", $list);
        self::assertStringContainsString("mg_require_method('GET')", $detail);
        self::assertStringContainsString('mg_share_market_audit_review_list', $list);
        self::assertStringContainsString('mg_share_market_audit_review_detail', $detail);
    }

    public function testNoRunnerEndpointWasAddedByConsolePhase(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
