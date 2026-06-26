<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/evidence-readiness.php';

final class ShareMarketEvidenceReadinessTest extends TestCase
{
    public function testRequiredSignoffsMatchOperatorChecklist(): void
    {
        self::assertSame(['engineering','security','legal','operations','database_backup','product_owner'], mg_share_market_evidence_required_signoffs());
    }

    public function testSignoffChecksRequireApprovedStatus(): void
    {
        $detail = ['operator_signoffs' => [
            ['signoff_type' => 'engineering', 'status' => 'approved'],
            ['signoff_type' => 'security', 'status' => 'revoked'],
        ]];

        $checks = mg_share_market_evidence_signoff_checks($detail);
        self::assertTrue($checks[0]['passed']);
        self::assertFalse($checks[1]['passed']);
        self::assertSame('revoked', $checks[1]['status']);
    }

    public function testLegalEvidenceRequiresApprovedRecord(): void
    {
        $check = mg_share_market_evidence_legal_check(['legal_evidence' => [['status' => 'submitted']]]);
        self::assertFalse($check['passed']);

        $check = mg_share_market_evidence_legal_check(['legal_evidence' => [['status' => 'approved']]]);
        self::assertTrue($check['passed']);
    }

    public function testRollbackEvidenceRequiresAcceptableStatus(): void
    {
        $check = mg_share_market_evidence_rollback_check(['rollback_evidence' => [['rollback_status' => 'plan_recorded']]]);
        self::assertFalse($check['passed']);

        $check = mg_share_market_evidence_rollback_check(['rollback_evidence' => [['rollback_status' => 'rollback_ready']]]);
        self::assertTrue($check['passed']);
    }

    public function testScoreAndBlockers(): void
    {
        $checks = [
            mg_share_market_evidence_check('a', 'A', true, 'ok'),
            mg_share_market_evidence_check('b', 'B', false, 'bad'),
            mg_share_market_evidence_check('c', 'C', true, 'ok'),
            mg_share_market_evidence_check('d', 'D', false, 'warn', 'warning'),
        ];

        self::assertSame(50, mg_share_market_evidence_score($checks));
        self::assertCount(1, mg_share_market_evidence_blockers($checks));
    }

    public function testReadinessApiIsGetOnlyAndPermissioned(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/evidence-readiness.php');

        self::assertStringContainsString("mg_require_method('GET')", $api);
        self::assertStringContainsString('Share Market Admin permission is required', $api);
        self::assertStringContainsString('mg_share_market_evidence_package', $api);
    }

    public function testAuditConsoleRendersReadinessPanel(): void
    {
        $partial = (string)file_get_contents(dirname(__DIR__, 2) . '/includes/account/share-market-execution-audit.php');
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');

        self::assertStringContainsString('data-share-readiness-panel', $partial);
        self::assertStringContainsString('/api/admin/share-market/evidence-readiness.php', $js);
        self::assertStringContainsString('renderReadiness', $js);
    }

    public function testNoRunnerEndpointWasAddedByReadinessPhase(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
