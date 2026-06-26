<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/execution-signoff.php';

final class ShareMarketOperatorSignoffTest extends TestCase
{
    public function testSignoffTypesMatchStageTwentyOneSchema(): void
    {
        self::assertSame(['engineering','security','legal','operations','database_backup','product_owner'], mg_share_market_signoff_types());
    }

    public function testLegalEvidenceTypesMatchStageTwentyOneSchema(): void
    {
        self::assertContains('legal_note', mg_share_market_legal_evidence_types());
        self::assertContains('policy_reference', mg_share_market_legal_evidence_types());
        self::assertContains('board_approval', mg_share_market_legal_evidence_types());
        self::assertContains('external_review', mg_share_market_legal_evidence_types());
    }

    public function testRollbackStatusesMatchStageTwentyOneSchema(): void
    {
        self::assertContains('plan_recorded', mg_share_market_rollback_statuses());
        self::assertContains('rollback_ready', mg_share_market_rollback_statuses());
        self::assertContains('rollback_tested', mg_share_market_rollback_statuses());
        self::assertContains('rollback_completed', mg_share_market_rollback_statuses());
    }

    public function testTextValidationRequiresRequiredValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_share_market_signoff_text('', 20, true);
    }

    public function testTextValidationRejectsLongValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_share_market_signoff_text(str_repeat('x', 300), 20, false);
    }

    public function testPayloadHashIsStableHashString(): void
    {
        $hash = mg_share_market_signoff_payload_hash(['type' => 'engineering', 'decision' => 'approved']);

        self::assertSame(64, strlen($hash));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function testApisArePostOnlyAndPermissioned(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            '/api/admin/share-market/execution-signoff.php',
            '/api/admin/share-market/execution-legal-evidence.php',
            '/api/admin/share-market/execution-rollback-evidence.php',
        ] as $path) {
            $file = (string)file_get_contents($root . $path);
            self::assertStringContainsString("mg_require_method('POST')", $file);
            self::assertStringContainsString('mg_require_csrf_for_write', $file);
            self::assertStringContainsString('Share Market Admin permission is required', $file);
            self::assertStringContainsString('No Buy-In value state was changed', $file);
        }
    }

    public function testAuditConsoleIncludesSignoffForms(): void
    {
        $partial = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/account/share-market-execution-audit.php');
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');

        self::assertStringContainsString('data-share-signoff-form', $partial);
        self::assertStringContainsString('data-share-legal-form', $partial);
        self::assertStringContainsString('data-share-rollback-form', $partial);
        self::assertStringContainsString('/api/admin/share-market/execution-signoff.php', $js);
        self::assertStringContainsString('/api/admin/share-market/execution-legal-evidence.php', $js);
        self::assertStringContainsString('/api/admin/share-market/execution-rollback-evidence.php', $js);
    }

    public function testNoRunnerEndpointWasAddedBySignoffPhase(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
