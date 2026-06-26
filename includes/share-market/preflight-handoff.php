<?php
declare(strict_types=1);

require_once __DIR__ . '/evidence-acknowledgements.php';

if (!function_exists('mg_share_market_handoff_latest_ack')) {
    function mg_share_market_handoff_latest_ack(array $acks): ?array
    {
        foreach ($acks as $ack) {
            if ((string)($ack['acknowledgement_status'] ?? '') === 'acknowledged') return $ack;
        }
        return $acks[0] ?? null;
    }
}

if (!function_exists('mg_share_market_handoff_check')) {
    function mg_share_market_handoff_check(string $key, string $label, bool $passed, string $message, array $extra = []): array
    {
        return array_merge(['key'=>$key,'label'=>$label,'passed'=>$passed,'message'=>$message], $extra);
    }
}

if (!function_exists('mg_share_market_handoff_signoff_status')) {
    function mg_share_market_handoff_signoff_status(array $readiness): array
    {
        $summary = $readiness['summary'] ?? [];
        $required = $summary['required_signoffs'] ?? [];
        $completed = $summary['completed_signoffs'] ?? [];
        $missing = array_values(array_diff($required, $completed));
        return [
            'required' => array_values($required),
            'completed' => array_values($completed),
            'missing' => $missing,
            'complete' => count($missing) === 0,
        ];
    }
}

if (!function_exists('mg_share_market_handoff_evidence_status')) {
    function mg_share_market_handoff_evidence_status(array $readiness): array
    {
        $summary = $readiness['summary'] ?? [];
        return [
            'legal_evidence_count' => (int)($summary['legal_evidence_count'] ?? 0),
            'rollback_evidence_count' => (int)($summary['rollback_evidence_count'] ?? 0),
            'idempotency_reservation_count' => (int)($summary['idempotency_reservation_count'] ?? 0),
        ];
    }
}

if (!function_exists('mg_share_market_handoff_checks')) {
    function mg_share_market_handoff_checks(?array $ack, array $readiness, array $signoffs, array $evidence): array
    {
        $drift = $ack['drift'] ?? [];
        $blockers = $readiness['blockers'] ?? [];
        return [
            mg_share_market_handoff_check('acknowledgement_present', 'Final acknowledgement present', $ack !== null, $ack ? 'Reviewer acknowledgement is recorded.' : 'Reviewer acknowledgement is missing.'),
            mg_share_market_handoff_check('acknowledgement_current', 'Acknowledgement matches current evidence', $ack !== null && (bool)($drift['matches_current'] ?? false), ($drift['drift_status'] ?? 'missing') === 'matching' ? 'Acknowledged hash matches current evidence hash.' : 'Acknowledged hash does not match the current evidence hash.', ['drift_status'=>$drift['drift_status'] ?? 'missing']),
            mg_share_market_handoff_check('readiness_complete', 'Readiness complete', (bool)($readiness['complete'] ?? false), (bool)($readiness['complete'] ?? false) ? 'Readiness checklist is complete.' : 'Readiness checklist has open items.', ['score'=>(int)($readiness['score'] ?? 0)]),
            mg_share_market_handoff_check('no_blockers', 'No remaining blockers', count($blockers) === 0, count($blockers) === 0 ? 'No blockers remain.' : 'One or more blockers remain.', ['blocker_count'=>count($blockers)]),
            mg_share_market_handoff_check('required_signoffs_complete', 'Required signoffs complete', (bool)$signoffs['complete'], $signoffs['complete'] ? 'Required signoffs are complete.' : 'Required signoffs are missing.', ['missing'=>$signoffs['missing']]),
            mg_share_market_handoff_check('legal_evidence_present', 'Legal evidence present', $evidence['legal_evidence_count'] > 0, $evidence['legal_evidence_count'] > 0 ? 'Legal evidence is present.' : 'Legal evidence is missing.', ['count'=>$evidence['legal_evidence_count']]),
            mg_share_market_handoff_check('rollback_evidence_present', 'Rollback evidence present', $evidence['rollback_evidence_count'] > 0, $evidence['rollback_evidence_count'] > 0 ? 'Rollback evidence is present.' : 'Rollback evidence is missing.', ['count'=>$evidence['rollback_evidence_count']]),
        ];
    }
}

if (!function_exists('mg_share_market_preflight_handoff')) {
    function mg_share_market_preflight_handoff(PDO $pdo, string $attemptId, array $actor = []): array
    {
        $readiness = mg_share_market_evidence_package($pdo, $attemptId);
        $acks = mg_share_market_ack_list($pdo, $attemptId, $actor);
        $latestAck = mg_share_market_handoff_latest_ack($acks['items'] ?? []);
        $currentExport = mg_share_market_evidence_export($pdo, $attemptId, $actor);
        $signoffs = mg_share_market_handoff_signoff_status($readiness);
        $evidence = mg_share_market_handoff_evidence_status($readiness);
        $checks = mg_share_market_handoff_checks($latestAck, $readiness, $signoffs, $evidence);
        $openChecks = array_values(array_filter($checks, static fn(array $check): bool => !(bool)$check['passed']));
        return [
            'handoff_version' => 'phase_20_preflight_handoff_v1',
            'generated_at' => gmdate('c'),
            'attempt_id' => $attemptId,
            'acknowledgement' => $latestAck ? [
                'public_id' => (string)($latestAck['public_id'] ?? ''),
                'candidate_public_id' => (string)($latestAck['candidate_public_id'] ?? ''),
                'reviewer_role' => (string)($latestAck['reviewer_role'] ?? ''),
                'reviewer_note' => (string)($latestAck['reviewer_note'] ?? ''),
                'package_hash' => (string)($latestAck['package_hash'] ?? ''),
                'created_at' => (string)($latestAck['created_at'] ?? ''),
                'drift' => $latestAck['drift'] ?? [],
            ] : null,
            'hashes' => [
                'acknowledged_package_hash' => (string)($latestAck['package_hash'] ?? ''),
                'current_package_hash' => (string)($currentExport['package_hash'] ?? ''),
                'drift_status' => (string)($latestAck['drift']['drift_status'] ?? 'missing'),
            ],
            'readiness' => [
                'score' => (int)($readiness['score'] ?? 0),
                'complete' => (bool)($readiness['complete'] ?? false),
                'blockers' => $readiness['blockers'] ?? [],
                'summary' => $readiness['summary'] ?? [],
            ],
            'signoffs' => $signoffs,
            'evidence' => $evidence,
            'checks' => $checks,
            'open_checks' => $openChecks,
            'handoff_ready' => count($openChecks) === 0,
            'domain_mutations_performed' => false,
        ];
    }
}
