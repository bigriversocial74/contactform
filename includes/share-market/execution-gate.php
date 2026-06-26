<?php
declare(strict_types=1);

require_once __DIR__ . '/execution-prep.php';

if (!function_exists('mg_share_market_gate_env_enabled')) {
    function mg_share_market_gate_env_enabled(string $name): bool
    {
        $value = strtolower(trim((string)getenv($name)));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('mg_share_market_gate_check')) {
    function mg_share_market_gate_check(string $key, bool $passed, string $label, string $message, string $severity = 'blocker', array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'severity' => $severity,
            'message' => $message,
        ], $extra);
    }
}

if (!function_exists('mg_share_market_gate_balance_check')) {
    function mg_share_market_gate_balance_check(array $manifest, array $projection): array
    {
        if (($projection['type'] ?? '') !== 'balance') {
            return mg_share_market_gate_check('balance_invariants', true, 'Balance invariants', 'No balance projection is attached to this action.', 'info');
        }

        $current = (int)($projection['current_balance'] ?? 0);
        $delta = (int)($projection['delta'] ?? 0);
        $projected = (int)($projection['projected_balance'] ?? 0);
        $action = (string)($manifest['action'] ?? '');
        $expected = $current + $delta;
        $validMath = $expected === $projected;
        $nonNegative = $current >= 0 && $projected >= 0;
        $direction = true;
        if (in_array($action, ['burn_platform_shares'], true)) $direction = $delta <= 0;
        if (in_array($action, ['mint_platform_shares', 'create_master_pool', 'allocate_merchant_credits'], true)) $direction = $delta >= 0;

        $passed = $validMath && $nonNegative && $direction;
        return mg_share_market_gate_check(
            'balance_invariants',
            $passed,
            'Balance invariants',
            $passed ? 'Projected balances pass arithmetic and direction checks.' : 'Projected balances failed arithmetic, non-negative, or action-direction checks.',
            'blocker',
            ['current_balance' => $current, 'delta' => $delta, 'projected_balance' => $projected, 'expected_balance' => $expected]
        );
    }
}

if (!function_exists('mg_share_market_gate_idempotency_clear')) {
    function mg_share_market_gate_idempotency_clear(PDO $pdo, string $idempotencyKey): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM share_market_credit_ledger WHERE idempotency_key=?');
        $stmt->execute([$idempotencyKey]);
        return (int)$stmt->fetchColumn() === 0;
    }
}

if (!function_exists('mg_share_market_gate_hash_chain_check')) {
    function mg_share_market_gate_hash_chain_check(PDO $pdo, array $manifest): array
    {
        $targetType = (string)($manifest['target_type'] ?? '');
        $targetId = (string)($manifest['target_id'] ?? '');
        if ($targetType === '' || $targetId === '') {
            return mg_share_market_gate_check('hash_chain_checkpoint', false, 'Hash-chain checkpoint', 'Target metadata is missing, so a hash-chain checkpoint cannot be prepared.');
        }

        $sourceTable = match ($targetType) {
            'platform_pool' => 'share_market_platform_pools',
            'merchant_treasury' => 'share_market_credit_treasuries',
            'market_series' => 'share_market_series',
            'participant' => 'share_market_enrollments',
            'holder_position' => 'share_market_holder_positions',
            'dave_profile', 'dave_score' => 'dave_scores',
            'ledger_checkpoint' => 'share_market_hash_chain_events',
            default => 'share_market_admin_events',
        };

        $stmt = $pdo->prepare('SELECT payload_hash FROM share_market_hash_chain_events WHERE source_table=? AND source_public_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$sourceTable, $targetId]);
        $latest = $stmt->fetchColumn();

        return mg_share_market_gate_check(
            'hash_chain_checkpoint',
            true,
            'Hash-chain checkpoint',
            $latest ? 'Existing checkpoint found; next event can chain from it.' : 'No prior checkpoint found; first checkpoint would be created during a future execution release.',
            'info',
            ['source_table' => $sourceTable, 'source_public_id' => $targetId, 'latest_payload_hash' => $latest ?: null]
        );
    }
}

if (!function_exists('mg_share_market_release_gate_from_row')) {
    function mg_share_market_release_gate_from_row(PDO $pdo, array $row, array $viewer, string $runMode = 'dry_run'): array
    {
        $manifest = mg_share_market_sql_decode($row['manifest_json'] ?? null);
        $projection = mg_share_market_sql_decode($row['projection_json'] ?? null);
        $idempotencyKey = mg_share_market_execution_idempotency_key($row, $manifest);
        $viewerId = (int)($viewer['id'] ?? 0);
        $requesterId = (int)($row['requester_user_id'] ?? 0);
        $featureEnabled = mg_share_market_gate_env_enabled('MICROGIFTER_SHARE_MARKET_EXECUTION_ENABLED');
        $legalReleased = mg_share_market_gate_env_enabled('MICROGIFTER_SHARE_MARKET_LEGAL_RELEASE');
        $isSuperAdmin = mg_share_market_admin_is_super_admin($viewer);
        $mode = $runMode === 'live' ? 'live' : 'dry_run';

        $checks = [];
        $checks[] = mg_share_market_gate_check('approved_request', (string)($row['status'] ?? '') === 'approved', 'Approved request', 'Request must be fully approved before it can be eligible.');
        $checks[] = mg_share_market_gate_check('super_admin_unlock', $isSuperAdmin, 'Super-admin unlock policy', 'Only a super administrator can pass the future live execution gate.');
        $checks[] = mg_share_market_gate_check('requester_separation', $viewerId > 0 && $viewerId !== $requesterId, 'Requester separation', 'The requester cannot execute their own approved request.');
        $checks[] = mg_share_market_gate_check('feature_flag', $featureEnabled, 'Feature flag', 'MICROGIFTER_SHARE_MARKET_EXECUTION_ENABLED must be explicitly enabled.');
        $checks[] = mg_share_market_gate_check('legal_release', $legalReleased, 'Legal release', 'MICROGIFTER_SHARE_MARKET_LEGAL_RELEASE must be explicitly enabled.');
        $checks[] = mg_share_market_gate_check('idempotency_replay', mg_share_market_gate_idempotency_clear($pdo, $idempotencyKey), 'Idempotency replay protection', 'No prior ledger entry may use this execution idempotency key.', 'blocker', ['idempotency_key' => $idempotencyKey]);
        $checks[] = mg_share_market_gate_balance_check($manifest, $projection);
        $checks[] = mg_share_market_gate_hash_chain_check($pdo, $manifest);
        $checks[] = mg_share_market_gate_check('dry_run_live_separation', $mode === 'dry_run', 'Dry-run/live-run separation', 'This build permits dry-run gate evaluation only. Live mode remains unavailable.');

        $blockers = array_values(array_filter($checks, static fn(array $check): bool => !$check['passed'] && ($check['severity'] ?? 'blocker') === 'blocker'));
        return [
            'gate_version' => 'phase_8_release_gate_v1',
            'run_mode' => $mode,
            'eligible_for_live_execution' => false,
            'release_gate_passed' => false,
            'blocked' => true,
            'block_reason' => $blockers ? 'Release gate has blocking checks.' : 'Live execution remains disabled by policy for Phase 8.',
            'checks' => $checks,
            'blockers' => $blockers,
            'required_unlocks' => [
                'execution_feature_flag' => 'MICROGIFTER_SHARE_MARKET_EXECUTION_ENABLED=1',
                'legal_release_flag' => 'MICROGIFTER_SHARE_MARKET_LEGAL_RELEASE=1',
                'future_release' => 'A later PR must replace the locked runner stub with a reviewed live runner.',
            ],
        ];
    }
}

if (!function_exists('mg_share_market_execution_preview_with_gate')) {
    function mg_share_market_execution_preview_with_gate(PDO $pdo, string $requestId, array $viewer, string $runMode = 'dry_run'): array
    {
        $row = mg_share_market_execution_fetch_request_row($pdo, $requestId);
        if (!$row) throw new InvalidArgumentException('Approval request not found.');
        $preview = mg_share_market_execution_preview_from_row($row, $viewer);
        $preview['release_gate'] = mg_share_market_release_gate_from_row($pdo, $row, $viewer, $runMode);
        $preview['ready_to_execute'] = false;
        $preview['can_execute'] = false;
        $preview['execution_enabled'] = false;
        return $preview;
    }
}

if (!function_exists('mg_share_market_execution_runner_stub_with_gate')) {
    function mg_share_market_execution_runner_stub_with_gate(PDO $pdo, string $requestId, array $viewer, string $runMode = 'dry_run'): array
    {
        $preview = mg_share_market_execution_preview_with_gate($pdo, $requestId, $viewer, $runMode);
        return [
            'runner_invoked' => true,
            'runner_state' => 'release_gate_blocked_stub',
            'executed' => false,
            'execution_enabled' => false,
            'can_execute' => false,
            'request_id' => $requestId,
            'idempotency_key' => (string)$preview['idempotency_key'],
            'message' => 'Release gate evaluated and blocked live execution. No Share Market action was executed.',
            'release_gate' => $preview['release_gate'],
            'preview' => $preview,
        ];
    }
}
