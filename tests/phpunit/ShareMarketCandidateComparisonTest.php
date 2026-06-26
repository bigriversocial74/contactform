<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/candidate-comparison.php';

final class ShareMarketCandidateComparisonTest extends TestCase
{
    public function testCompareKeysReportsAddedAndRemovedRecords(): void
    {
        $result = mg_share_market_compare_keys(
            ['signoff_snapshot' => ['records' => [['public_id' => 'a'], ['public_id' => 'b']]]],
            ['signoff_snapshot' => ['records' => [['public_id' => 'b'], ['public_id' => 'c']]]],
            'signoff_snapshot'
        );

        self::assertSame(2, $result['left_count']);
        self::assertSame(2, $result['right_count']);
        self::assertSame(['c'], $result['added']);
        self::assertSame(['a'], $result['removed']);
        self::assertFalse($result['same_keys']);
    }

    public function testCompareHashListReportsHashDelta(): void
    {
        $left = [['payload_hash' => str_repeat('a', 64)], ['payload_hash' => str_repeat('b', 64)]];
        $right = [['payload_hash' => str_repeat('b', 64)], ['payload_hash' => str_repeat('c', 64)]];
        $result = mg_share_market_compare_hash_list($left, $right);

        self::assertSame([str_repeat('c', 64)], $result['added']);
        self::assertSame([str_repeat('a', 64)], $result['removed']);
        self::assertFalse($result['same_hashes']);
    }

    public function testCompareBlockersReportsResolvedAndNewBlockers(): void
    {
        $result = mg_share_market_compare_blockers(
            ['blockers' => [['key' => 'legal'], ['key' => 'rollback']]],
            ['blockers' => [['key' => 'rollback'], ['key' => 'simulator']]]
        );

        self::assertSame(['simulator'], $result['added']);
        self::assertSame(['legal'], $result['removed']);
        self::assertFalse($result['same_blockers']);
    }

    public function testComparePacketsBuildsReadOnlyComparison(): void
    {
        $left = [
            'candidate' => ['public_id' => 'left-candidate-1234567890', 'package_hash' => str_repeat('a', 64), 'status' => 'superseded'],
            'readiness_snapshot' => ['score' => 80, 'complete' => false, 'blockers' => [['key' => 'legal']]],
            'signoff_snapshot' => ['records' => [['public_id' => 's1']]],
            'legal_evidence_snapshot' => ['records' => []],
            'rollback_evidence_snapshot' => ['records' => []],
            'reservation_snapshot' => ['records' => []],
            'snapshot_hashes' => [['payload_hash' => str_repeat('b', 64)]],
            'gate_hash' => str_repeat('c', 64),
            'simulator_hash' => str_repeat('d', 64),
        ];
        $right = $left;
        $right['candidate'] = ['public_id' => 'right-candidate-1234567890', 'package_hash' => str_repeat('e', 64), 'status' => 'active'];
        $right['readiness_snapshot'] = ['score' => 100, 'complete' => true, 'blockers' => []];

        $comparison = mg_share_market_candidate_compare_packets($left, $right);

        self::assertSame('phase_18_candidate_comparison_v1', $comparison['comparison_version']);
        self::assertFalse($comparison['package_hashes']['same']);
        self::assertSame(20, $comparison['readiness']['score_delta']);
        self::assertSame(['legal'], $comparison['readiness']['blockers']['removed']);
        self::assertFalse($comparison['domain_mutations_performed']);
    }

    public function testComparisonApiIsGetOnlyAndPermissioned(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/evidence-candidate-comparison.php');

        self::assertStringContainsString("mg_require_method('GET')", $api);
        self::assertStringContainsString('Share Market Admin permission is required', $api);
        self::assertStringContainsString('mg_share_market_candidate_comparison', $api);
    }

    public function testComparisonPageAndConsoleLinksExist(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/account-share-market-candidate-comparison.php');
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');
        $compareJs = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-candidate-comparison.js');

        self::assertStringContainsString('/assets/js/share-market-candidate-comparison.js', $page);
        self::assertStringContainsString('/assets/css/share-market-candidate-comparison.css', $page);
        self::assertStringContainsString('/account-share-market-candidate-comparison.php?attempt_id=', $js);
        self::assertStringContainsString('/api/admin/share-market/evidence-candidate-comparison.php', $compareJs);
    }

    public function testNoRunnerEndpointWasAddedByComparisonPhase(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
