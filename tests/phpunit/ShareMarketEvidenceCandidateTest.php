<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/evidence-candidates.php';

final class ShareMarketEvidenceCandidateTest extends TestCase
{
    public function testCandidateNoteValidationRejectsLongNotes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_share_market_candidate_note(str_repeat('x', 2200));
    }

    public function testCandidateCompareReportsMatchingHash(): void
    {
        $hash = str_repeat('a', 64);
        $comparison = mg_share_market_candidate_compare(['package_hash' => $hash], ['package_hash' => $hash]);

        self::assertTrue($comparison['matches_current']);
        self::assertSame('matching', $comparison['comparison_status']);
    }

    public function testCandidateCompareReportsDriftedHash(): void
    {
        $comparison = mg_share_market_candidate_compare(['package_hash' => str_repeat('a', 64)], ['package_hash' => str_repeat('b', 64)]);

        self::assertFalse($comparison['matches_current']);
        self::assertSame('drifted', $comparison['comparison_status']);
    }

    public function testCandidateDecodeDecodesPackageJsonAndCompares(): void
    {
        $row = mg_share_market_candidate_decode([
            'public_id' => 'candidate-1',
            'package_hash' => str_repeat('c', 64),
            'package_json' => '{"package_hash":"' . str_repeat('c', 64) . '"}',
        ], ['package_hash' => str_repeat('c', 64)]);

        self::assertIsArray($row['package_json']);
        self::assertTrue($row['comparison']['matches_current']);
    }

    public function testMigrationIsRegistered(): void
    {
        $manifest = (string)file_get_contents(dirname(__DIR__, 2) . '/config/migrations.php');
        $migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/stage_22_buy_in_evidence_candidates.sql');

        self::assertStringContainsString('stage_22_buy_in_evidence_candidates.sql', $manifest);
        self::assertStringContainsString('share_market_evidence_candidates', $migration);
        self::assertStringContainsString('Stores selected evidence package hashes', $migration);
    }

    public function testCandidateApiIsGetAndPostOnlyWithCsrfForWrites(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/evidence-candidates.php');

        self::assertStringContainsString('mg_share_market_candidate_list', $api);
        self::assertStringContainsString('mg_require_csrf_for_write', $api);
        self::assertStringContainsString('mg_share_market_candidate_record', $api);
        self::assertStringContainsString('mg_share_market_candidate_revoke', $api);
        self::assertStringContainsString('No Buy-In value state was changed', $api);
    }

    public function testAuditConsoleInjectsCandidateControls(): void
    {
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');

        self::assertStringContainsString('data-share-candidate-panel', $js);
        self::assertStringContainsString('data-share-candidate-form', $js);
        self::assertStringContainsString('/api/admin/share-market/evidence-candidates.php', $js);
        self::assertStringContainsString('recordCandidate', $js);
        self::assertStringContainsString('revokeCandidate', $js);
    }

    public function testNoRunnerEndpointWasAddedByCandidatePhase(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
