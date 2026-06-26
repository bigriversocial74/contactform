<?php
declare(strict_types=1);

require_once __DIR__ . '/candidate-packet.php';

if (!function_exists('mg_share_market_compare_keys')) {
    function mg_share_market_compare_keys(array $left, array $right, string $field): array
    {
        $leftRows = $left[$field]['records'] ?? [];
        $rightRows = $right[$field]['records'] ?? [];
        $leftKeys = [];
        $rightKeys = [];
        foreach ($leftRows as $row) $leftKeys[] = (string)($row['public_id'] ?? $row['signoff_type'] ?? $row['evidence_type'] ?? $row['rollback_status'] ?? '');
        foreach ($rightRows as $row) $rightKeys[] = (string)($row['public_id'] ?? $row['signoff_type'] ?? $row['evidence_type'] ?? $row['rollback_status'] ?? '');
        $leftKeys = array_values(array_unique(array_filter($leftKeys)));
        $rightKeys = array_values(array_unique(array_filter($rightKeys)));
        sort($leftKeys);
        sort($rightKeys);
        return [
            'left_count' => count($leftRows),
            'right_count' => count($rightRows),
            'added' => array_values(array_diff($rightKeys, $leftKeys)),
            'removed' => array_values(array_diff($leftKeys, $rightKeys)),
            'same_keys' => $leftKeys === $rightKeys,
        ];
    }
}

if (!function_exists('mg_share_market_compare_hash_list')) {
    function mg_share_market_compare_hash_list(array $left, array $right): array
    {
        $norm = static function (array $rows): array {
            $out = [];
            foreach ($rows as $row) $out[] = (string)($row['payload_hash'] ?? $row['package_hash'] ?? $row['public_id'] ?? '');
            $out = array_values(array_unique(array_filter($out)));
            sort($out);
            return $out;
        };
        $leftHashes = $norm($left);
        $rightHashes = $norm($right);
        return [
            'left_count' => count($leftHashes),
            'right_count' => count($rightHashes),
            'added' => array_values(array_diff($rightHashes, $leftHashes)),
            'removed' => array_values(array_diff($leftHashes, $rightHashes)),
            'same_hashes' => $leftHashes === $rightHashes,
        ];
    }
}

if (!function_exists('mg_share_market_compare_blockers')) {
    function mg_share_market_compare_blockers(array $left, array $right): array
    {
        $keyer = static function (array $rows): array {
            $keys = [];
            foreach ($rows as $row) $keys[] = (string)($row['key'] ?? $row['label'] ?? '');
            $keys = array_values(array_unique(array_filter($keys)));
            sort($keys);
            return $keys;
        };
        $leftKeys = $keyer($left['blockers'] ?? []);
        $rightKeys = $keyer($right['blockers'] ?? []);
        return [
            'left_count' => count($leftKeys),
            'right_count' => count($rightKeys),
            'added' => array_values(array_diff($rightKeys, $leftKeys)),
            'removed' => array_values(array_diff($leftKeys, $rightKeys)),
            'same_blockers' => $leftKeys === $rightKeys,
        ];
    }
}

if (!function_exists('mg_share_market_candidate_compare_packets')) {
    function mg_share_market_candidate_compare_packets(array $leftPacket, array $rightPacket): array
    {
        $leftReady = $leftPacket['readiness_snapshot'] ?? [];
        $rightReady = $rightPacket['readiness_snapshot'] ?? [];
        return [
            'comparison_version' => 'phase_18_candidate_comparison_v1',
            'generated_at' => gmdate('c'),
            'left_candidate' => $leftPacket['candidate'] ?? [],
            'right_candidate' => $rightPacket['candidate'] ?? [],
            'package_hashes' => [
                'left' => (string)($leftPacket['candidate']['package_hash'] ?? ''),
                'right' => (string)($rightPacket['candidate']['package_hash'] ?? ''),
                'same' => (string)($leftPacket['candidate']['package_hash'] ?? '') === (string)($rightPacket['candidate']['package_hash'] ?? ''),
            ],
            'readiness' => [
                'left_score' => (int)($leftReady['score'] ?? 0),
                'right_score' => (int)($rightReady['score'] ?? 0),
                'score_delta' => (int)($rightReady['score'] ?? 0) - (int)($leftReady['score'] ?? 0),
                'left_complete' => (bool)($leftReady['complete'] ?? false),
                'right_complete' => (bool)($rightReady['complete'] ?? false),
                'blockers' => mg_share_market_compare_blockers($leftReady, $rightReady),
            ],
            'signoffs' => mg_share_market_compare_keys($leftPacket, $rightPacket, 'signoff_snapshot'),
            'legal_evidence' => mg_share_market_compare_keys($leftPacket, $rightPacket, 'legal_evidence_snapshot'),
            'rollback_evidence' => mg_share_market_compare_keys($leftPacket, $rightPacket, 'rollback_evidence_snapshot'),
            'reservations' => mg_share_market_compare_keys($leftPacket, $rightPacket, 'reservation_snapshot'),
            'snapshot_hashes' => mg_share_market_compare_hash_list($leftPacket['snapshot_hashes'] ?? [], $rightPacket['snapshot_hashes'] ?? []),
            'gate_hashes' => [
                'left' => (string)($leftPacket['gate_hash'] ?? ''),
                'right' => (string)($rightPacket['gate_hash'] ?? ''),
                'same' => (string)($leftPacket['gate_hash'] ?? '') === (string)($rightPacket['gate_hash'] ?? ''),
            ],
            'simulator_hashes' => [
                'left' => (string)($leftPacket['simulator_hash'] ?? ''),
                'right' => (string)($rightPacket['simulator_hash'] ?? ''),
                'same' => (string)($leftPacket['simulator_hash'] ?? '') === (string)($rightPacket['simulator_hash'] ?? ''),
            ],
            'domain_mutations_performed' => false,
        ];
    }
}

if (!function_exists('mg_share_market_candidate_comparison')) {
    function mg_share_market_candidate_comparison(PDO $pdo, string $attemptId, string $leftCandidateId, string $rightCandidateId, array $actor = []): array
    {
        $leftCandidateId = mg_share_market_packet_candidate_id($leftCandidateId);
        $rightCandidateId = mg_share_market_packet_candidate_id($rightCandidateId);
        if ($leftCandidateId === $rightCandidateId) throw new InvalidArgumentException('Select two different candidates to compare.');
        $left = mg_share_market_candidate_packet($pdo, $attemptId, $leftCandidateId, $actor);
        $right = mg_share_market_candidate_packet($pdo, $attemptId, $rightCandidateId, $actor);
        return mg_share_market_candidate_compare_packets($left, $right);
    }
}
