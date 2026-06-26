<?php
declare(strict_types=1);

require_once __DIR__ . '/evidence-readiness.php';

if (!function_exists('mg_share_market_export_hash')) {
    function mg_share_market_export_hash(array $payload): string
    {
        return mg_share_market_execution_audit_hash($payload);
    }
}

if (!function_exists('mg_share_market_export_counts')) {
    function mg_share_market_export_counts(array $rows, string $field): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $key = (string)($row[$field] ?? 'missing');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        ksort($counts);
        return $counts;
    }
}

if (!function_exists('mg_share_market_export_rows')) {
    function mg_share_market_export_rows(array $rows, array $fields): array
    {
        return array_map(static function (array $row) use ($fields): array {
            $out = [];
            foreach ($fields as $field) $out[$field] = $row[$field] ?? null;
            return $out;
        }, $rows);
    }
}

if (!function_exists('mg_share_market_export_snapshot_hashes')) {
    function mg_share_market_export_snapshot_hashes(array $rows): array
    {
        return mg_share_market_export_rows($rows, ['public_id','snapshot_type','payload_hash','created_at']);
    }
}

if (!function_exists('mg_share_market_evidence_export')) {
    function mg_share_market_evidence_export(PDO $pdo, string $attemptId, array $reviewer = []): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) throw new RuntimeException('Buy-In audit schema is not installed.');
        $detail = mg_share_market_audit_review_detail($pdo, $attemptId);
        $ready = mg_share_market_evidence_package($pdo, $attemptId);
        $attempt = $detail['attempt'] ?? [];
        $out = [
            'export_version' => 'phase_15_evidence_export_v1',
            'exported_at' => gmdate('c'),
            'reviewer_user_id' => (int)($reviewer['id'] ?? 0),
            'attempt' => mg_share_market_export_rows([$attempt], ['public_id','request_public_id','status','run_mode','target_type','target_public_id','idempotency_key','release_gate_status','simulator_status','target_snapshot_hash','preflight_payload_hash','created_at','updated_at'])[0] ?? [],
            'approval_request' => $detail['approval_request'] ?? null,
            'readiness' => [
                'complete' => (bool)($ready['complete'] ?? false),
                'score' => (int)($ready['score'] ?? 0),
                'checks' => $ready['checks'] ?? [],
                'blockers' => $ready['blockers'] ?? [],
                'summary' => $ready['summary'] ?? [],
            ],
            'snapshot_hashes' => mg_share_market_export_snapshot_hashes($detail['snapshots'] ?? []),
            'signoffs' => [
                'counts' => mg_share_market_export_counts($detail['operator_signoffs'] ?? [], 'status'),
                'records' => mg_share_market_export_rows($detail['operator_signoffs'] ?? [], ['public_id','signoff_type','status','evidence_ref','evidence_hash','signed_at','created_at','updated_at']),
            ],
            'legal_evidence' => [
                'counts' => mg_share_market_export_counts($detail['legal_evidence'] ?? [], 'status'),
                'records' => mg_share_market_export_rows($detail['legal_evidence'] ?? [], ['public_id','evidence_type','status','evidence_ref','summary','evidence_hash','created_at','updated_at']),
            ],
            'rollback_evidence' => [
                'counts' => mg_share_market_export_counts($detail['rollback_evidence'] ?? [], 'rollback_status'),
                'records' => mg_share_market_export_rows($detail['rollback_evidence'] ?? [], ['public_id','rollback_status','reason_code','note','evidence_hash','created_at']),
            ],
            'reservations' => [
                'counts' => mg_share_market_export_counts($detail['idempotency_reservations'] ?? [], 'status'),
                'records' => mg_share_market_export_rows($detail['idempotency_reservations'] ?? [], ['public_id','idempotency_key','status','reserved_at','expires_at','released_at','used_at','payload_hash']),
            ],
            'gate_hash' => mg_share_market_export_hash(is_array($attempt['release_gate_json'] ?? null) ? $attempt['release_gate_json'] : []),
            'simulator_hash' => mg_share_market_export_hash(is_array($attempt['simulator_json'] ?? null) ? $attempt['simulator_json'] : []),
            'domain_mutations_performed' => false,
        ];
        $out['package_hash'] = mg_share_market_export_hash($out);
        return $out;
    }
}
