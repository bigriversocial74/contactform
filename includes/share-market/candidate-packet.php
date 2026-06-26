<?php
declare(strict_types=1);

require_once __DIR__ . '/evidence-candidates.php';

if (!function_exists('mg_share_market_packet_candidate_id')) {
    function mg_share_market_packet_candidate_id($value): string
    {
        $id = trim((string)$value);
        if ($id === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $id) !== 1) throw new InvalidArgumentException('Candidate identifier is invalid.');
        return $id;
    }
}

if (!function_exists('mg_share_market_packet_hash_ok')) {
    function mg_share_market_packet_hash_ok(array $candidate): bool
    {
        $payload = $candidate['package_json'] ?? [];
        if (!is_array($payload)) return false;
        $recorded = (string)($candidate['package_hash'] ?? '');
        $inside = (string)($payload['package_hash'] ?? '');
        return $recorded !== '' && $recorded === $inside;
    }
}

if (!function_exists('mg_share_market_packet_trim_records')) {
    function mg_share_market_packet_trim_records(array $records, int $limit = 25): array
    {
        return array_slice(array_values($records), 0, max(1, min($limit, 100)));
    }
}

if (!function_exists('mg_share_market_packet_from_candidate')) {
    function mg_share_market_packet_from_candidate(array $candidate, array $currentExport): array
    {
        $payload = is_array($candidate['package_json'] ?? null) ? $candidate['package_json'] : [];
        $readiness = is_array($payload['readiness'] ?? null) ? $payload['readiness'] : [];
        $comparison = mg_share_market_candidate_compare($candidate, $currentExport);
        return [
            'packet_version' => 'phase_17_candidate_packet_v1',
            'generated_at' => gmdate('c'),
            'candidate' => [
                'public_id' => (string)($candidate['public_id'] ?? ''),
                'status' => (string)($candidate['status'] ?? ''),
                'package_hash' => (string)($candidate['package_hash'] ?? ''),
                'reviewer_note' => (string)($candidate['reviewer_note'] ?? ''),
                'created_at' => (string)($candidate['created_at'] ?? ''),
                'created_by_name' => (string)($candidate['created_by_name'] ?? ''),
            ],
            'comparison' => $comparison,
            'hash_verification' => [
                'recorded_hash_matches_payload' => mg_share_market_packet_hash_ok($candidate),
                'recorded_hash' => (string)($candidate['package_hash'] ?? ''),
                'payload_hash' => (string)($payload['package_hash'] ?? ''),
                'current_hash' => (string)($currentExport['package_hash'] ?? ''),
            ],
            'attempt' => is_array($payload['attempt'] ?? null) ? $payload['attempt'] : [],
            'approval_request' => $payload['approval_request'] ?? null,
            'readiness_snapshot' => [
                'complete' => (bool)($readiness['complete'] ?? false),
                'score' => (int)($readiness['score'] ?? 0),
                'checks' => mg_share_market_packet_trim_records($readiness['checks'] ?? []),
                'blockers' => mg_share_market_packet_trim_records($readiness['blockers'] ?? []),
                'summary' => $readiness['summary'] ?? [],
            ],
            'signoff_snapshot' => is_array($payload['signoffs'] ?? null) ? $payload['signoffs'] : [],
            'legal_evidence_snapshot' => is_array($payload['legal_evidence'] ?? null) ? $payload['legal_evidence'] : [],
            'rollback_evidence_snapshot' => is_array($payload['rollback_evidence'] ?? null) ? $payload['rollback_evidence'] : [],
            'reservation_snapshot' => is_array($payload['reservations'] ?? null) ? $payload['reservations'] : [],
            'snapshot_hashes' => $payload['snapshot_hashes'] ?? [],
            'gate_hash' => (string)($payload['gate_hash'] ?? ''),
            'simulator_hash' => (string)($payload['simulator_hash'] ?? ''),
            'domain_mutations_performed' => false,
        ];
    }
}

if (!function_exists('mg_share_market_candidate_packet')) {
    function mg_share_market_candidate_packet(PDO $pdo, string $attemptId, string $candidateId, array $actor = []): array
    {
        if (!mg_share_market_candidates_schema_available($pdo)) throw new RuntimeException('Buy-In evidence candidate schema is not installed.');
        $candidateId = mg_share_market_packet_candidate_id($candidateId);
        $attempt = mg_share_market_candidate_fetch_attempt($pdo, $attemptId);
        $currentExport = mg_share_market_evidence_export($pdo, $attemptId, $actor);
        $stmt = $pdo->prepare('SELECT c.*,u.email AS created_by_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS created_by_name FROM share_market_evidence_candidates c LEFT JOIN users u ON u.id=c.created_by_user_id WHERE c.public_id=? AND c.execution_attempt_id=? LIMIT 1');
        $stmt->execute([$candidateId, (int)$attempt['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Evidence candidate not found.');
        $candidate = mg_share_market_candidate_decode($row, $currentExport);
        return mg_share_market_packet_from_candidate($candidate, $currentExport);
    }
}
