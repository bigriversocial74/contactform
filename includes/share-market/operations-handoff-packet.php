<?php
declare(strict_types=1);

require_once __DIR__ . '/handoff-archives.php';

if (!function_exists('mg_share_market_ops_packet_archive_id')) {
    function mg_share_market_ops_packet_archive_id($value): string
    {
        $id = trim((string)$value);
        if ($id !== '' && preg_match('/^[A-Za-z0-9-]{20,80}$/', $id) !== 1) throw new InvalidArgumentException('Archive identifier is invalid.');
        return $id;
    }
}

if (!function_exists('mg_share_market_ops_packet_pick_archive')) {
    function mg_share_market_ops_packet_pick_archive(array $archives, string $archiveId = ''): array
    {
        foreach (($archives['items'] ?? []) as $row) {
            if ($archiveId === '' || (string)($row['public_id'] ?? '') === $archiveId) return $row;
        }
        throw new InvalidArgumentException($archiveId === '' ? 'No handoff archive was found.' : 'Handoff archive was not found.');
    }
}

if (!function_exists('mg_share_market_ops_packet')) {
    function mg_share_market_ops_packet(PDO $pdo, string $attemptId, string $archiveId = '', array $actor = []): array
    {
        $archiveId = mg_share_market_ops_packet_archive_id($archiveId);
        $archives = mg_share_market_handoff_archives($pdo, $attemptId, $actor);
        $archive = mg_share_market_ops_packet_pick_archive($archives, $archiveId);
        $handoff = $archive['handoff_json'] ?? [];
        $ready = $handoff['readiness'] ?? [];
        $signoffs = $handoff['signoffs'] ?? [];
        $evidence = $handoff['evidence'] ?? [];
        return [
            'packet_version' => 'phase_22_operations_handoff_packet_v1',
            'generated_at' => gmdate('c'),
            'attempt_id' => $attemptId,
            'archive' => [
                'public_id' => (string)($archive['public_id'] ?? ''),
                'handoff_hash' => (string)($archive['handoff_hash'] ?? ''),
                'handoff_ready' => (bool)($archive['handoff_ready'] ?? false),
                'reviewer_note' => (string)($archive['reviewer_note'] ?? ''),
                'created_at' => (string)($archive['created_at'] ?? ''),
                'created_by_name' => (string)($archive['created_by_name'] ?? ''),
                'candidate_public_id' => (string)($archive['candidate_public_id'] ?? ''),
                'acknowledgement_public_id' => (string)($archive['acknowledgement_public_id'] ?? ''),
            ],
            'drift' => $archive['drift'] ?? [],
            'acknowledgement' => $handoff['acknowledgement'] ?? null,
            'hashes' => $handoff['hashes'] ?? [],
            'readiness' => [
                'score' => (int)($ready['score'] ?? 0),
                'complete' => (bool)($ready['complete'] ?? false),
                'blockers' => $ready['blockers'] ?? [],
            ],
            'signoffs' => $signoffs,
            'evidence' => $evidence,
            'checks' => $handoff['checks'] ?? [],
            'open_checks' => $handoff['open_checks'] ?? [],
            'summary' => [
                'handoff_ready' => (bool)($handoff['handoff_ready'] ?? false),
                'archive_matches_current' => (bool)($archive['drift']['matches_current'] ?? false),
                'missing_signoff_count' => count($signoffs['missing'] ?? []),
                'open_check_count' => count($handoff['open_checks'] ?? []),
                'blocker_count' => count($ready['blockers'] ?? []),
                'legal_evidence_count' => (int)($evidence['legal_evidence_count'] ?? 0),
                'rollback_evidence_count' => (int)($evidence['rollback_evidence_count'] ?? 0),
            ],
            'domain_mutations_performed' => false,
        ];
    }
}
