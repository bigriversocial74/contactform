<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/candidate-packet.php';

final class ShareMarketCandidatePacketTest extends TestCase
{
    public function testCandidateIdValidationRejectsInvalidId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_share_market_packet_candidate_id('bad id');
    }

    public function testHashVerificationRequiresPayloadHashMatch(): void
    {
        $hash = str_repeat('a', 64);

        self::assertTrue(mg_share_market_packet_hash_ok([
            'package_hash' => $hash,
            'package_json' => ['package_hash' => $hash],
        ]));
        self::assertFalse(mg_share_market_packet_hash_ok([
            'package_hash' => $hash,
            'package_json' => ['package_hash' => str_repeat('b', 64)],
        ]));
    }

    public function testPacketTrimsRecordsToSafeLimit(): void
    {
        $records = array_map(static fn(int $i): array => ['i' => $i], range(1, 40));

        self::assertCount(25, mg_share_market_packet_trim_records($records));
        self::assertCount(5, mg_share_market_packet_trim_records($records, 5));
    }

    public function testPacketFromCandidateBuildsReviewPayload(): void
    {
        $hash = str_repeat('c', 64);
        $packet = mg_share_market_packet_from_candidate([
            'public_id' => 'candidate-12345678901234567890',
            'status' => 'active',
            'package_hash' => $hash,
            'reviewer_note' => 'Ready for review',
            'package_json' => [
                'package_hash' => $hash,
                'attempt' => ['public_id' => 'attempt-1'],
                'readiness' => ['complete' => true, 'score' => 100, 'checks' => [['key' => 'a', 'passed' => true]], 'blockers' => [], 'summary' => []],
                'signoffs' => ['records' => [['signoff_type' => 'engineering']]],
                'legal_evidence' => ['records' => []],
                'rollback_evidence' => ['records' => []],
                'reservations' => ['records' => []],
                'snapshot_hashes' => [],
                'gate_hash' => str_repeat('d', 64),
                'simulator_hash' => str_repeat('e', 64),
            ],
        ], ['package_hash' => $hash]);

        self::assertSame('phase_17_candidate_packet_v1', $packet['packet_version']);
        self::assertTrue($packet['comparison']['matches_current']);
        self::assertTrue($packet['hash_verification']['recorded_hash_matches_payload']);
        self::assertSame(100, $packet['readiness_snapshot']['score']);
        self::assertFalse($packet['domain_mutations_performed']);
    }

    public function testPacketApiIsGetOnlyAndPermissioned(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/evidence-candidate-packet.php');

        self::assertStringContainsString("mg_require_method('GET')", $api);
        self::assertStringContainsString('Share Market Admin permission is required', $api);
        self::assertStringContainsString('mg_share_market_candidate_packet', $api);
    }

    public function testPacketPageAndConsoleLinksExist(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/account-share-market-candidate-packet.php');
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');
        $packetJs = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-candidate-packet.js');

        self::assertStringContainsString('/assets/js/share-market-candidate-packet.js', $page);
        self::assertStringContainsString('/assets/css/share-market-candidate-packet.css', $page);
        self::assertStringContainsString('/account-share-market-candidate-packet.php?attempt_id=', $js);
        self::assertStringContainsString('/api/admin/share-market/evidence-candidate-packet.php', $packetJs);
    }

    public function testNoRunnerEndpointWasAddedByPacketPhase(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . '/api/admin/share-market/live-runner.php');
        self::assertFileDoesNotExist($root . '/api/admin/share-market/finalize-runner.php');
        self::assertFileDoesNotExist($root . '/includes/share-market/live-runner.php');
    }
}
