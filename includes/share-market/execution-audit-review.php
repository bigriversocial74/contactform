<?php
declare(strict_types=1);

require_once __DIR__ . '/execution-audit.php';

if (!function_exists('mg_share_market_audit_review_statuses')) {
    function mg_share_market_audit_review_statuses(): array
    {
        return ['created','preflight_ready','blocked_by_gate','reconciliation_mismatch','reserved','cancelled','expired','failed','void'];
    }
}

if (!function_exists('mg_share_market_audit_review_run_modes')) {
    function mg_share_market_audit_review_run_modes(): array
    {
        return ['dry_run','live_requested'];
    }
}

if (!function_exists('mg_share_market_audit_review_limit')) {
    function mg_share_market_audit_review_limit($value): int
    {
        $limit = (int)$value;
        if ($limit < 1) return 25;
        return min($limit, 100);
    }
}

if (!function_exists('mg_share_market_audit_review_decode_row')) {
    function mg_share_market_audit_review_decode_row(array $row): array
    {
        foreach (['release_gate_json','simulator_json','metadata_json','snapshot_json','evidence_json'] as $key) {
            if (array_key_exists($key, $row)) $row[$key] = mg_share_market_sql_decode($row[$key] ?? null);
        }
        return $row;
    }
}

if (!function_exists('mg_share_market_audit_review_summary')) {
    function mg_share_market_audit_review_summary(PDO $pdo): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) return [];
        $summary = array_fill_keys(mg_share_market_audit_review_statuses(), 0);
        $stmt = $pdo->query('SELECT status,COUNT(*) AS total FROM share_market_execution_attempts GROUP BY status');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $summary[(string)$row['status']] = (int)$row['total'];
        $summary['total'] = array_sum($summary);
        return $summary;
    }
}

if (!function_exists('mg_share_market_audit_review_attempt_metric')) {
    function mg_share_market_audit_review_attempt_metric(PDO $pdo, string $table, int $attemptId, string $statusColumn = ''): array
    {
        $allowed = [
            'share_market_execution_preflight_snapshots' => ['column' => 'snapshot_type'],
            'share_market_execution_operator_signoffs' => ['column' => 'status'],
            'share_market_legal_release_evidence' => ['column' => 'status'],
            'share_market_execution_rollback_evidence' => ['column' => 'rollback_status'],
            'share_market_idempotency_reservations' => ['column' => 'status'],
        ];
        if (!isset($allowed[$table])) return ['total' => 0, 'by_status' => []];
        $column = $statusColumn !== '' ? $statusColumn : $allowed[$table]['column'];
        $stmt = $pdo->prepare("SELECT {$column} AS status,COUNT(*) AS total FROM {$table} WHERE execution_attempt_id=? GROUP BY {$column}");
        $stmt->execute([$attemptId]);
        $by = [];
        $total = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string)$row['status'];
            $count = (int)$row['total'];
            $by[$key] = $count;
            $total += $count;
        }
        return ['total' => $total, 'by_status' => $by];
    }
}

if (!function_exists('mg_share_market_audit_review_enrich_attempt')) {
    function mg_share_market_audit_review_enrich_attempt(PDO $pdo, array $row): array
    {
        $row = mg_share_market_audit_review_decode_row($row);
        $attemptId = (int)$row['id'];
        $row['metrics'] = [
            'snapshots' => mg_share_market_audit_review_attempt_metric($pdo, 'share_market_execution_preflight_snapshots', $attemptId),
            'operator_signoffs' => mg_share_market_audit_review_attempt_metric($pdo, 'share_market_execution_operator_signoffs', $attemptId),
            'legal_evidence' => mg_share_market_audit_review_attempt_metric($pdo, 'share_market_legal_release_evidence', $attemptId),
            'rollback_evidence' => mg_share_market_audit_review_attempt_metric($pdo, 'share_market_execution_rollback_evidence', $attemptId),
            'idempotency_reservations' => mg_share_market_audit_review_attempt_metric($pdo, 'share_market_idempotency_reservations', $attemptId),
        ];
        return $row;
    }
}

if (!function_exists('mg_share_market_audit_review_list')) {
    function mg_share_market_audit_review_list(PDO $pdo, array $filters = []): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) throw new RuntimeException('Buy-In execution audit scaffolding schema is not installed.');
        $where = [];
        $params = [];
        $status = (string)($filters['status'] ?? '');
        if ($status !== '' && $status !== 'all' && in_array($status, mg_share_market_audit_review_statuses(), true)) {
            $where[] = 'a.status=?';
            $params[] = $status;
        }
        $runMode = (string)($filters['run_mode'] ?? '');
        if ($runMode !== '' && $runMode !== 'all' && in_array($runMode, mg_share_market_audit_review_run_modes(), true)) {
            $where[] = 'a.run_mode=?';
            $params[] = $runMode;
        }
        foreach (['target_type','target_public_id','request_public_id'] as $field) {
            $value = trim((string)($filters[$field] ?? ''));
            if ($value !== '') {
                $where[] = "a.{$field}=?";
                $params[] = $value;
            }
        }
        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(a.public_id LIKE ? OR a.request_public_id LIKE ? OR a.idempotency_key LIKE ? OR a.target_public_id LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $limit = mg_share_market_audit_review_limit($filters['limit'] ?? 25);
        $sql = 'SELECT a.*,u.email AS actor_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS actor_name FROM share_market_execution_attempts a LEFT JOIN users u ON u.id=a.actor_user_id';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY a.created_at DESC,a.id DESC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $items[] = mg_share_market_audit_review_enrich_attempt($pdo, $row);
        return ['items' => $items, 'summary' => mg_share_market_audit_review_summary($pdo), 'filters' => $filters, 'limit' => $limit];
    }
}

if (!function_exists('mg_share_market_audit_review_fetch_attempt')) {
    function mg_share_market_audit_review_fetch_attempt(PDO $pdo, string $attemptId): ?array
    {
        $stmt = $pdo->prepare('SELECT a.*,u.email AS actor_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS actor_name FROM share_market_execution_attempts a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.public_id=? LIMIT 1');
        $stmt->execute([$attemptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? mg_share_market_audit_review_enrich_attempt($pdo, $row) : null;
    }
}

if (!function_exists('mg_share_market_audit_review_rows')) {
    function mg_share_market_audit_review_rows(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('mg_share_market_audit_review_decode_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

if (!function_exists('mg_share_market_audit_review_detail')) {
    function mg_share_market_audit_review_detail(PDO $pdo, string $attemptId): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) throw new RuntimeException('Buy-In execution audit scaffolding schema is not installed.');
        $attempt = mg_share_market_audit_review_fetch_attempt($pdo, $attemptId);
        if (!$attempt) throw new InvalidArgumentException('Execution audit attempt not found.');
        $id = (int)$attempt['id'];
        $requestId = (int)$attempt['approval_request_id'];
        return [
            'attempt' => $attempt,
            'approval_request' => mg_share_market_audit_review_rows($pdo, 'SELECT public_id,request_type,action_key,event_type,target_type,target_public_id,requester_user_id,status,risk_level,required_approvals,approval_count,expires_at,payload_hash,execution_enabled,executed_at,created_at,updated_at FROM share_market_approval_requests WHERE id=? LIMIT 1', [$requestId])[0] ?? null,
            'snapshots' => mg_share_market_audit_review_rows($pdo, 'SELECT public_id,snapshot_type,target_type,target_public_id,snapshot_json,payload_hash,created_at FROM share_market_execution_preflight_snapshots WHERE execution_attempt_id=? ORDER BY created_at ASC,id ASC', [$id]),
            'operator_signoffs' => mg_share_market_audit_review_rows($pdo, 'SELECT s.public_id,s.signoff_type,s.status,s.note,s.evidence_ref,s.evidence_hash,s.signed_at,s.created_at,s.updated_at,u.email AS operator_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS operator_name FROM share_market_execution_operator_signoffs s LEFT JOIN users u ON u.id=s.operator_user_id WHERE s.execution_attempt_id=? ORDER BY s.created_at ASC,s.id ASC', [$id]),
            'legal_evidence' => mg_share_market_audit_review_rows($pdo, 'SELECT e.public_id,e.evidence_type,e.status,e.evidence_ref,e.summary,e.evidence_hash,e.evidence_json,e.created_at,e.updated_at,u.email AS recorded_by_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS recorded_by_name FROM share_market_legal_release_evidence e LEFT JOIN users u ON u.id=e.recorded_by_user_id WHERE e.approval_request_id=? AND (e.execution_attempt_id IS NULL OR e.execution_attempt_id=?) ORDER BY e.created_at ASC,e.id ASC', [$requestId, $id]),
            'rollback_evidence' => mg_share_market_audit_review_rows($pdo, 'SELECT r.public_id,r.rollback_status,r.reason_code,r.note,r.evidence_hash,r.evidence_json,r.created_at,u.email AS recorded_by_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS recorded_by_name FROM share_market_execution_rollback_evidence r LEFT JOIN users u ON u.id=r.recorded_by_user_id WHERE r.execution_attempt_id=? ORDER BY r.created_at ASC,r.id ASC', [$id]),
            'idempotency_reservations' => mg_share_market_audit_review_rows($pdo, 'SELECT public_id,idempotency_key,status,reserved_at,expires_at,released_at,used_at,payload_hash,metadata_json FROM share_market_idempotency_reservations WHERE execution_attempt_id=? ORDER BY reserved_at DESC,id DESC', [$id]),
        ];
    }
}
