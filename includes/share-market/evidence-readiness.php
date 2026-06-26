<?php
declare(strict_types=1);

require_once __DIR__ . '/execution-signoff.php';

if (!function_exists('mg_share_market_evidence_check')) {
    function mg_share_market_evidence_check(string $key, string $label, bool $passed, string $message, string $severity = 'blocker', array $extra = []): array
    {
        return array_merge(['key'=>$key,'label'=>$label,'passed'=>$passed,'severity'=>$severity,'message'=>$message], $extra);
    }
}

if (!function_exists('mg_share_market_evidence_required_signoffs')) {
    function mg_share_market_evidence_required_signoffs(): array
    {
        return ['engineering','security','legal','operations','database_backup','product_owner'];
    }
}

if (!function_exists('mg_share_market_evidence_signoff_map')) {
    function mg_share_market_evidence_signoff_map(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) $map[(string)($row['signoff_type'] ?? '')] = (string)($row['status'] ?? '');
        return $map;
    }
}

if (!function_exists('mg_share_market_evidence_signoff_checks')) {
    function mg_share_market_evidence_signoff_checks(array $detail): array
    {
        $map = mg_share_market_evidence_signoff_map($detail['operator_signoffs'] ?? []);
        $checks = [];
        foreach (mg_share_market_evidence_required_signoffs() as $type) {
            $status = $map[$type] ?? 'missing';
            $checks[] = mg_share_market_evidence_check(
                'signoff_' . $type,
                ucwords(str_replace('_',' ', $type)) . ' signoff',
                $status === 'approved',
                $status === 'approved' ? 'Approved record is present.' : 'Approved record is missing or inactive.',
                'blocker',
                ['status'=>$status]
            );
        }
        return $checks;
    }
}

if (!function_exists('mg_share_market_evidence_legal_check')) {
    function mg_share_market_evidence_legal_check(array $detail): array
    {
        $approved = array_values(array_filter($detail['legal_evidence'] ?? [], static fn(array $row): bool => (string)($row['status'] ?? '') === 'approved'));
        return mg_share_market_evidence_check('legal_evidence_approved', 'Legal evidence approved', count($approved) > 0, $approved ? 'Approved legal evidence is present.' : 'Approved legal evidence is missing.', 'blocker', ['approved_count'=>count($approved)]);
    }
}

if (!function_exists('mg_share_market_evidence_rollback_check')) {
    function mg_share_market_evidence_rollback_check(array $detail): array
    {
        $ok = ['not_required','rollback_ready','rollback_tested','rollback_completed'];
        $rows = array_values(array_filter($detail['rollback_evidence'] ?? [], static fn(array $row): bool => in_array((string)($row['rollback_status'] ?? ''), $ok, true)));
        return mg_share_market_evidence_check('rollback_evidence_present', 'Rollback evidence present', count($rows) > 0, $rows ? 'Rollback evidence is present.' : 'Rollback evidence is missing.', 'blocker', ['present_count'=>count($rows)]);
    }
}

if (!function_exists('mg_share_market_evidence_simulator_check')) {
    function mg_share_market_evidence_simulator_check(array $detail): array
    {
        $status = (string)($detail['attempt']['simulator_status'] ?? '');
        return mg_share_market_evidence_check('simulator_reconciled', 'Simulator reconciliation clean', $status === 'reconciled_dry_run', $status === 'reconciled_dry_run' ? 'Simulator status is clean.' : 'Simulator status is not clean.', 'blocker', ['status'=>$status]);
    }
}

if (!function_exists('mg_share_market_evidence_gate_check')) {
    function mg_share_market_evidence_gate_check(array $detail): array
    {
        $status = (string)($detail['attempt']['release_gate_status'] ?? '');
        $visible = $status !== '' && $status !== 'not_evaluated';
        return mg_share_market_evidence_check('gate_status_visible', 'Gate status visible', $visible, $visible ? 'Gate status is visible for review.' : 'Gate status is missing.', 'warning', ['status'=>$status]);
    }
}

if (!function_exists('mg_share_market_evidence_idempotency_check')) {
    function mg_share_market_evidence_idempotency_check(array $detail): array
    {
        $valid = array_values(array_filter($detail['idempotency_reservations'] ?? [], static function (array $row): bool {
            if ((string)($row['status'] ?? '') !== 'reserved') return false;
            $expires = strtotime((string)($row['expires_at'] ?? ''));
            return $expires === false || $expires >= time();
        }));
        return mg_share_market_evidence_check('idempotency_reserved', 'Idempotency reservation present', count($valid) > 0, $valid ? 'Active reservation is present.' : 'Active reservation is missing.', 'blocker', ['reservation_count'=>count($valid)]);
    }
}

if (!function_exists('mg_share_market_evidence_blockers')) {
    function mg_share_market_evidence_blockers(array $checks): array
    {
        return array_values(array_filter($checks, static fn(array $check): bool => !$check['passed'] && ($check['severity'] ?? 'blocker') === 'blocker'));
    }
}

if (!function_exists('mg_share_market_evidence_score')) {
    function mg_share_market_evidence_score(array $checks): int
    {
        if (!$checks) return 0;
        $passed = count(array_filter($checks, static fn(array $check): bool => (bool)$check['passed']));
        return (int)round(($passed / count($checks)) * 100);
    }
}

if (!function_exists('mg_share_market_evidence_package')) {
    function mg_share_market_evidence_package(PDO $pdo, string $attemptId): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) throw new RuntimeException('Buy-In audit schema is not installed.');
        $detail = mg_share_market_audit_review_detail($pdo, $attemptId);
        $checks = array_merge(
            mg_share_market_evidence_signoff_checks($detail),
            [
                mg_share_market_evidence_legal_check($detail),
                mg_share_market_evidence_rollback_check($detail),
                mg_share_market_evidence_simulator_check($detail),
                mg_share_market_evidence_gate_check($detail),
                mg_share_market_evidence_idempotency_check($detail),
            ]
        );
        $blockers = mg_share_market_evidence_blockers($checks);
        $map = mg_share_market_evidence_signoff_map($detail['operator_signoffs'] ?? []);
        return [
            'readiness_version' => 'phase_14_evidence_package_v1',
            'attempt_id' => $attemptId,
            'complete' => count($blockers) === 0,
            'score' => mg_share_market_evidence_score($checks),
            'checks' => $checks,
            'blockers' => $blockers,
            'summary' => [
                'required_signoffs' => mg_share_market_evidence_required_signoffs(),
                'completed_signoffs' => array_keys(array_filter($map, static fn(string $status): bool => $status === 'approved')),
                'legal_evidence_count' => count($detail['legal_evidence'] ?? []),
                'rollback_evidence_count' => count($detail['rollback_evidence'] ?? []),
                'idempotency_reservation_count' => count($detail['idempotency_reservations'] ?? []),
            ],
            'domain_mutations_performed' => false,
        ];
    }
}
