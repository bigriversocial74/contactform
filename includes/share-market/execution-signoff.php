<?php
declare(strict_types=1);

require_once __DIR__ . '/execution-audit-review.php';

if (!function_exists('mg_share_market_signoff_types')) {
    function mg_share_market_signoff_types(): array
    {
        return ['engineering','security','legal','operations','database_backup','product_owner'];
    }
}

if (!function_exists('mg_share_market_legal_evidence_types')) {
    function mg_share_market_legal_evidence_types(): array
    {
        return ['legal_note','policy_reference','contract_reference','board_approval','operator_release','external_review'];
    }
}

if (!function_exists('mg_share_market_rollback_statuses')) {
    function mg_share_market_rollback_statuses(): array
    {
        return ['not_required','plan_recorded','rollback_ready','rollback_tested','rollback_invoked','rollback_failed','rollback_completed'];
    }
}

if (!function_exists('mg_share_market_signoff_text')) {
    function mg_share_market_signoff_text($value, int $max = 255, bool $required = false): string
    {
        $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
        if ($required && $text === '') throw new InvalidArgumentException('Required signoff field is missing.');
        if (mb_strlen($text) > $max) throw new InvalidArgumentException('Signoff field is too long.');
        return $text;
    }
}

if (!function_exists('mg_share_market_signoff_note')) {
    function mg_share_market_signoff_note($value, int $max = 2000): string
    {
        $text = trim((string)$value);
        if (mb_strlen($text) > $max) throw new InvalidArgumentException('Signoff note is too long.');
        return $text;
    }
}

if (!function_exists('mg_share_market_signoff_fetch_attempt')) {
    function mg_share_market_signoff_fetch_attempt(PDO $pdo, string $attemptId): array
    {
        $attempt = mg_share_market_audit_review_fetch_attempt($pdo, $attemptId);
        if (!$attempt) throw new InvalidArgumentException('Audit attempt not found.');
        return $attempt;
    }
}

if (!function_exists('mg_share_market_signoff_payload_hash')) {
    function mg_share_market_signoff_payload_hash(array $payload): string
    {
        return mg_share_market_execution_audit_hash($payload);
    }
}

if (!function_exists('mg_share_market_signoff_decision')) {
    function mg_share_market_signoff_decision(PDO $pdo, string $attemptId, array $actor, array $input): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) throw new RuntimeException('Buy-In audit schema is not installed.');
        $attempt = mg_share_market_signoff_fetch_attempt($pdo, $attemptId);
        $type = mg_share_market_signoff_text($input['signoff_type'] ?? '', 80, true);
        if (!in_array($type, mg_share_market_signoff_types(), true)) throw new InvalidArgumentException('Invalid signoff type.');
        $decision = mg_share_market_signoff_text($input['decision'] ?? 'approved', 20, true);
        if (!in_array($decision, ['approved','rejected','revoked'], true)) throw new InvalidArgumentException('Invalid signoff decision.');
        $note = mg_share_market_signoff_note($input['note'] ?? '');
        $evidenceRef = mg_share_market_signoff_text($input['evidence_ref'] ?? '', 255, false);
        $payload = [
            'attempt_public_id' => (string)$attempt['public_id'],
            'approval_request_id' => (int)$attempt['approval_request_id'],
            'operator_user_id' => (int)($actor['id'] ?? 0),
            'signoff_type' => $type,
            'decision' => $decision,
            'note' => $note,
            'evidence_ref' => $evidenceRef,
            'recorded_at' => gmdate('c'),
        ];
        $hash = mg_share_market_signoff_payload_hash($payload);
        $signedAt = $decision === 'approved' ? 'NOW()' : 'NULL';
        $sql = "INSERT INTO share_market_execution_operator_signoffs (public_id,execution_attempt_id,approval_request_id,operator_user_id,signoff_type,status,note,evidence_ref,evidence_hash,signed_at) VALUES (?,?,?,?,?,?,?,?,?,{$signedAt}) ON DUPLICATE KEY UPDATE operator_user_id=VALUES(operator_user_id),status=VALUES(status),note=VALUES(note),evidence_ref=VALUES(evidence_ref),evidence_hash=VALUES(evidence_hash),signed_at={$signedAt},updated_at=NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            mg_share_market_execution_audit_public_id(),
            (int)$attempt['id'],
            (int)$attempt['approval_request_id'],
            (int)($actor['id'] ?? 0),
            $type,
            $decision,
            $note !== '' ? $note : null,
            $evidenceRef !== '' ? $evidenceRef : null,
            $hash,
        ]);
        return [
            'record_type' => 'operator_signoff',
            'attempt_id' => (string)$attempt['public_id'],
            'signoff_type' => $type,
            'status' => $decision,
            'evidence_hash' => $hash,
            'domain_mutations_performed' => false,
        ];
    }
}

if (!function_exists('mg_share_market_legal_evidence_record')) {
    function mg_share_market_legal_evidence_record(PDO $pdo, string $attemptId, array $actor, array $input): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) throw new RuntimeException('Buy-In audit schema is not installed.');
        $attempt = mg_share_market_signoff_fetch_attempt($pdo, $attemptId);
        $type = mg_share_market_signoff_text($input['evidence_type'] ?? 'legal_note', 80, true);
        if (!in_array($type, mg_share_market_legal_evidence_types(), true)) throw new InvalidArgumentException('Invalid legal evidence type.');
        $status = mg_share_market_signoff_text($input['status'] ?? 'submitted', 40, true);
        if (!in_array($status, ['draft','submitted','approved','rejected','superseded'], true)) throw new InvalidArgumentException('Invalid legal evidence status.');
        $summary = mg_share_market_signoff_text($input['summary'] ?? '', 255, true);
        $evidenceRef = mg_share_market_signoff_text($input['evidence_ref'] ?? '', 255, false);
        $note = mg_share_market_signoff_note($input['note'] ?? '');
        $payload = [
            'attempt_public_id' => (string)$attempt['public_id'],
            'approval_request_id' => (int)$attempt['approval_request_id'],
            'recorded_by_user_id' => (int)($actor['id'] ?? 0),
            'evidence_type' => $type,
            'status' => $status,
            'summary' => $summary,
            'evidence_ref' => $evidenceRef,
            'note' => $note,
            'recorded_at' => gmdate('c'),
        ];
        $hash = mg_share_market_signoff_payload_hash($payload);
        $stmt = $pdo->prepare('INSERT INTO share_market_legal_release_evidence (public_id,execution_attempt_id,approval_request_id,recorded_by_user_id,evidence_type,status,evidence_ref,summary,evidence_hash,evidence_json) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            mg_share_market_execution_audit_public_id(),
            (int)$attempt['id'],
            (int)$attempt['approval_request_id'],
            (int)($actor['id'] ?? 0),
            $type,
            $status,
            $evidenceRef !== '' ? $evidenceRef : null,
            $summary,
            $hash,
            mg_share_market_sql_json($payload),
        ]);
        return ['record_type' => 'legal_evidence', 'attempt_id' => (string)$attempt['public_id'], 'status' => $status, 'evidence_hash' => $hash, 'domain_mutations_performed' => false];
    }
}

if (!function_exists('mg_share_market_rollback_evidence_record')) {
    function mg_share_market_rollback_evidence_record(PDO $pdo, string $attemptId, array $actor, array $input): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) throw new RuntimeException('Buy-In audit schema is not installed.');
        $attempt = mg_share_market_signoff_fetch_attempt($pdo, $attemptId);
        $rollbackStatus = mg_share_market_signoff_text($input['rollback_status'] ?? 'plan_recorded', 80, true);
        if (!in_array($rollbackStatus, mg_share_market_rollback_statuses(), true)) throw new InvalidArgumentException('Invalid rollback status.');
        $reasonCode = mg_share_market_signoff_text($input['reason_code'] ?? 'operator_review', 120, true);
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,119}$/', $reasonCode) !== 1) throw new InvalidArgumentException('Invalid rollback reason code.');
        $note = mg_share_market_signoff_note($input['note'] ?? '');
        $payload = [
            'attempt_public_id' => (string)$attempt['public_id'],
            'approval_request_id' => (int)$attempt['approval_request_id'],
            'recorded_by_user_id' => (int)($actor['id'] ?? 0),
            'rollback_status' => $rollbackStatus,
            'reason_code' => $reasonCode,
            'note' => $note,
            'recorded_at' => gmdate('c'),
        ];
        $hash = mg_share_market_signoff_payload_hash($payload);
        $stmt = $pdo->prepare('INSERT INTO share_market_execution_rollback_evidence (public_id,execution_attempt_id,approval_request_id,recorded_by_user_id,rollback_status,reason_code,note,evidence_hash,evidence_json) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            mg_share_market_execution_audit_public_id(),
            (int)$attempt['id'],
            (int)$attempt['approval_request_id'],
            (int)($actor['id'] ?? 0),
            $rollbackStatus,
            $reasonCode,
            $note !== '' ? $note : null,
            $hash,
            mg_share_market_sql_json($payload),
        ]);
        return ['record_type' => 'rollback_evidence', 'attempt_id' => (string)$attempt['public_id'], 'status' => $rollbackStatus, 'evidence_hash' => $hash, 'domain_mutations_performed' => false];
    }
}
