<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/preflight-handoff.php';

final class ShareMarketPreflightHandoffTest extends TestCase
{
    public function testLatestAcknowledgementPrefersAcknowledgedStatus(): void
    {
        $ack = mg_share_market_handoff_latest_ack([
            ['public_id' => 'revoked', 'acknowledgement_status' => 'revoked'],
            ['public_id' => 'active', 'acknowledgement_status' => 'acknowledged'],
        ]);

        self::assertSame('active', $ack['public_id']);
    }

    public function testSignoffStatusFindsMissingRequiredSignoffs(): void
    {
        $status = mg_share_market_handoff_signoff_status([
            'summary' => [
                'required_signoffs' => ['engineering','security','legal'],
                'completed_signoffs' => ['engineering'],
            ],
        ]);

        self::assertFalse($status['complete']);
        self::assertSame(['security','legal'], $status['missing']);
    }

    public function testEvidenceStatusUsesReadinessSummaryCounts(): void
    {
        $status = mg_share_market_handoff_evidence_status([
            'summary' => ['legal_evidence_count' => 1, 'rollback_evidence_count' => 2, 'idempotency_reservation_count' => 3],
        ]);

        self::assertSame(1, $status['legal_evidence_count']);
        self::assertSame(2, $status['rollback_evidence_count']);
        self::assertSame(3, $status['idempotency_reservation_count']);
    }

    public function testHandoffChecksRequireAcknowledgementAndCleanReadiness(): void
    {
        $checks = mg_share_market_handoff_checks(
            ['package_hash' => str_repeat('a', 64), 'drift' => ['matches_current' => true, 'drift_status' => 'matching']],
            ['complete' => true, 'score' => 100, 'blockers' => []],
            ['complete' => true, 'missing' => []],
            ['legal_evidence_count' => 1, 'rollback_evidence_count' => 1]
        );

        self::assertNotEmpty($checks);
        self::assertSame([], array_values(array_filter($checks, static fn(array $check): bool => !$check['passed'])));
    }

    public function testHandoffApiIsGetOnlyAndPermissioned(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/preflight-handoff.php');

        self::assertStringContainsString("mg_require_method('GET')", $api);
        self::assertStringContainsString('Share Market Admin permission is required', $api);
        self::assertStringContainsString('mg_share_market_preflight_handoff', $api);
    }

    public function testAuditConsoleInjectsHandoffPanel(): void
    {
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');

        self::assertStringContainsString('data-share-handoff-panel', $js);
        self::assertStringContainsString('/api/admin/share-market/preflight-handoff.php', $js);
        self::assertStringContainsString('renderHandoff', $js);
        self::assertStringContainsString('loadHandoff', $js);
    }
}
