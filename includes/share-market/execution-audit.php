<?php
declare(strict_types=1);

require_once __DIR__ . '/ledger-simulator.php';

if (!function_exists('mg_share_market_execution_audit_schema_available')) {
    function mg_share_market_execution_audit_schema_available(PDO $pdo): bool
    {
        static $available = null;
        if ($available !== null) return $available;
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'share_market_execution_attempts'");
            $available = (bool)($stmt && $stmt->fetchColumn());
            return $available;
        } catch (Throwable) {
            $available = false;
            return false;
        }
    }
}

if (!function_exists('mg_share_market_execution_audit_status')) {
    function mg_share_market_execution_audit_status(array $simulation): string
    {
        $reconciliation = (string)($simulation['reconciliation']['status'] ?? '');
        $gateBlocked = (bool)($simulation['release_gate']['blocked'] ?? true);
        if ($reconciliation === 'mismatch') return 'reconciliation_mismatch';
        if ($gateBlocked) return 'blocked_by_gate';
        if ($reconciliation === 'reconciled_dry_run') return 'preflight_ready';
        return 'created';
    }
}

if (!function_exists('mg_share_market_execution_audit_public_id')) {
    function mg_share_market_execution_audit_public_id(): string
    {
        return mg_share_market_sql_public_id();
    }
}

if (!function_exists('mg_share_market_execution_audit_hash')) {
    function mg_share_market_execution_audit_hash(array $payload): string
    {
        return mg_share_market_program_canonical_hash($payload);
    }
}

if (!function_exists('mg_share_market_execution_audit_payload')) {
    function mg_share_market_execution_audit_payload(array $row, array $simulation, array $actor, string $runMode): array
    {
        $manifest = mg_share_market_sql_decode($row['manifest_json'] ?? null);
        return [
            'approval_request_id' => (int)$row['id'],
            'request_public_id' => (string)$row['public_id'],
            'actor_user_id' => (int)($actor['id'] ?? 0),
            'run_mode' => $runMode === 'live' ? 'live_requested' : 'dry_run',
            'status' => mg_share_market_execution_audit_status($simulation),
            'idempotency_key' => (string)$simulation['idempotency_key'],
            'release_gate_status' => (bool)($simulation['release_gate']['blocked'] ?? true) ? 'blocked' : 'passed',
            'simulator_status' => (string)($simulation['reconciliation']['status'] ?? 'unknown'),
            'target_type' => (string)($manifest['target_type'] ?? ''),
            'target_public_id' => (string)($manifest['target_id'] ?? ''),
            'target_snapshot_hash' => mg_share_market_execution_audit_hash($simulation['target_snapshot'] ?? []),
            'metadata' => [
                'phase' => 'phase_11_audit_scaffolding',
                'writes_value' => false,
                'moves_balance' => false,
                'launches_market' => false,
                'created_at' => gmdate('c'),
            ],
        ];
    }
}

if (!function_exists('mg_share_market_execution_audit_find_attempt_by_key')) {
    function mg_share_market_execution_audit_find_attempt_by_key(PDO $pdo, string $idempotencyKey): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM share_market_execution_attempts WHERE idempotency_key=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$idempotencyKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mg_share_market_execution_audit_snapshot_payload')) {
    function mg_share_market_execution_audit_snapshot_payload(string $type, array $snapshot, array $attempt): array
    {
        return [
            'snapshot_type' => $type,
            'execution_attempt_public_id' => (string)$attempt['public_id'],
            'approval_request_id' => (int)$attempt['approval_request_id'],
            'target_type' => (string)$attempt['target_type'],
            'target_public_id' => (string)$attempt['target_public_id'],
            'snapshot' => $snapshot,
            'captured_at' => gmdate('c'),
        ];
    }
}

if (!function_exists('mg_share_market_execution_audit_insert_snapshot')) {
    function mg_share_market_execution_audit_insert_snapshot(PDO $pdo, int $attemptId, array $attempt, string $type, array $snapshot): void
    {
        $payload = mg_share_market_execution_audit_snapshot_payload($type, $snapshot, $attempt);
        $hash = mg_share_market_execution_audit_hash($payload);
        $stmt = $pdo->prepare('INSERT IGNORE INTO share_market_execution_preflight_snapshots (public_id,execution_attempt_id,approval_request_id,snapshot_type,target_type,target_public_id,snapshot_json,payload_hash) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([
            mg_share_market_execution_audit_public_id(),
            $attemptId,
            (int)$attempt['approval_request_id'],
            $type,
            (string)$attempt['target_type'],
            (string)$attempt['target_public_id'],
            mg_share_market_sql_json($snapshot),
            $hash,
        ]);
    }
}

if (!function_exists('mg_share_market_execution_audit_reserve_idempotency')) {
    function mg_share_market_execution_audit_reserve_idempotency(PDO $pdo, int $attemptId, array $attempt, array $actor): array
    {
        $payload = [
            'attempt_public_id' => (string)$attempt['public_id'],
            'approval_request_id' => (int)$attempt['approval_request_id'],
            'idempotency_key' => (string)$attempt['idempotency_key'],
            'reserved_by_user_id' => (int)($actor['id'] ?? 0),
            'expires_at' => gmdate('c', time() + 86400),
        ];
        $hash = mg_share_market_execution_audit_hash($payload);
        $stmt = $pdo->prepare("INSERT IGNORE INTO share_market_idempotency_reservations (public_id,idempotency_key,execution_attempt_id,approval_request_id,reserved_by_user_id,status,expires_at,payload_hash,metadata_json) VALUES (?,?,?,?,?,'reserved',DATE_ADD(NOW(),INTERVAL 1 DAY),?,?)");
        $stmt->execute([
            mg_share_market_execution_audit_public_id(),
            (string)$attempt['idempotency_key'],
            $attemptId,
            (int)$attempt['approval_request_id'],
            (int)($actor['id'] ?? 0),
            $hash,
            mg_share_market_sql_json($payload),
        ]);
        return ['idempotency_key' => (string)$attempt['idempotency_key'], 'status' => 'reserved', 'expires_in_seconds' => 86400, 'writes_value' => false];
    }
}

if (!function_exists('mg_share_market_execution_audit_create_bundle')) {
    function mg_share_market_execution_audit_create_bundle(PDO $pdo, string $requestId, array $actor, string $runMode = 'dry_run'): array
    {
        if (!mg_share_market_execution_audit_schema_available($pdo)) throw new RuntimeException('Buy-In execution audit scaffolding schema is not installed.');
        $row = mg_share_market_execution_fetch_request_row($pdo, $requestId);
        if (!$row) throw new InvalidArgumentException('Approval request not found.');
        $simulation = mg_share_market_ledger_simulation($pdo, $requestId, $actor, $runMode === 'live' ? 'live' : 'dry_run');
        $attempt = mg_share_market_execution_audit_payload($row, $simulation, $actor, $runMode);
        $existing = mg_share_market_execution_audit_find_attempt_by_key($pdo, (string)$attempt['idempotency_key']);
        if ($existing) {
            return [
                'attempt' => $existing,
                'simulation' => $simulation,
                'reservation' => ['idempotency_key' => (string)$attempt['idempotency_key'], 'status' => 'already_reserved', 'writes_value' => false],
                'created' => false,
                'mutations_performed' => false,
                'domain_mutations_performed' => false,
            ];
        }

        $preflightPayload = ['attempt' => $attempt, 'simulation_hash' => mg_share_market_execution_audit_hash($simulation)];
        $preflightHash = mg_share_market_execution_audit_hash($preflightPayload);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO share_market_execution_attempts (public_id,approval_request_id,request_public_id,actor_user_id,run_mode,status,idempotency_key,release_gate_status,simulator_status,target_type,target_public_id,target_snapshot_hash,preflight_payload_hash,release_gate_json,simulator_json,metadata_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                mg_share_market_execution_audit_public_id(),
                (int)$attempt['approval_request_id'],
                (string)$attempt['request_public_id'],
                (int)$attempt['actor_user_id'],
                (string)$attempt['run_mode'],
                (string)$attempt['status'],
                (string)$attempt['idempotency_key'],
                (string)$attempt['release_gate_status'],
                (string)$attempt['simulator_status'],
                (string)$attempt['target_type'],
                (string)$attempt['target_public_id'],
                (string)$attempt['target_snapshot_hash'],
                $preflightHash,
                mg_share_market_sql_json($simulation['release_gate'] ?? []),
                mg_share_market_sql_json($simulation),
                mg_share_market_sql_json($attempt['metadata']),
            ]);
            $attemptId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('SELECT * FROM share_market_execution_attempts WHERE id=? LIMIT 1');
            $stmt->execute([$attemptId]);
            $storedAttempt = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            mg_share_market_execution_audit_insert_snapshot($pdo, $attemptId, $storedAttempt, 'release_gate', $simulation['release_gate'] ?? []);
            mg_share_market_execution_audit_insert_snapshot($pdo, $attemptId, $storedAttempt, 'ledger_simulator', $simulation);
            mg_share_market_execution_audit_insert_snapshot($pdo, $attemptId, $storedAttempt, 'target_snapshot', $simulation['target_snapshot'] ?? []);
            mg_share_market_execution_audit_insert_snapshot($pdo, $attemptId, $storedAttempt, 'approval_request', [
                'public_id' => (string)$row['public_id'],
                'status' => (string)$row['status'],
                'payload_hash' => (string)$row['payload_hash'],
                'execution_enabled' => (int)$row['execution_enabled'],
            ]);
            $reservation = mg_share_market_execution_audit_reserve_idempotency($pdo, $attemptId, $storedAttempt, $actor);
            $pdo->commit();
            return [
                'attempt' => $storedAttempt,
                'simulation' => $simulation,
                'reservation' => $reservation,
                'created' => true,
                'mutations_performed' => false,
                'domain_mutations_performed' => false,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
