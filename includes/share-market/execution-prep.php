<?php
declare(strict_types=1);

require_once __DIR__ . '/approval-sql-adapter.php';

if (!function_exists('mg_share_market_execution_public_id')) {
    function mg_share_market_execution_public_id(): string
    {
        return mg_share_market_sql_public_id();
    }
}

if (!function_exists('mg_share_market_execution_fetch_request_row')) {
    function mg_share_market_execution_fetch_request_row(PDO $pdo, string $requestId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM share_market_approval_requests WHERE public_id=? LIMIT 1');
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mg_share_market_execution_idempotency_key')) {
    function mg_share_market_execution_idempotency_key(array $row, array $manifest): string
    {
        return 'sm-exec-' . hash('sha256', implode('|', [
            (string)$row['public_id'],
            (string)$row['payload_hash'],
            (string)($manifest['manifest_id'] ?? ''),
            (string)($manifest['action'] ?? ''),
            (string)($manifest['target_type'] ?? ''),
            (string)($manifest['target_id'] ?? ''),
        ]));
    }
}

if (!function_exists('mg_share_market_execution_action_kind')) {
    function mg_share_market_execution_action_kind(string $action): string
    {
        return match ($action) {
            'create_master_pool' => 'pool_create',
            'mint_platform_shares' => 'ledger_mint',
            'burn_platform_shares' => 'ledger_burn',
            'pause_platform_pool', 'resume_platform_pool', 'freeze_platform_pool' => 'pool_state_change',
            'approve_participant', 'pause_participant' => 'participant_state_change',
            'allocate_merchant_credits' => 'treasury_allocation',
            'freeze_merchant_treasury' => 'treasury_state_change',
            'approve_series', 'pause_series' => 'series_state_change',
            'freeze_holder_shares' => 'holder_position_lock',
            'recalculate_dave_score', 'lock_dave_score' => 'dave_score_control',
            'publish_proof_hash' => 'hash_checkpoint',
            default => 'admin_state_change',
        };
    }
}

if (!function_exists('mg_share_market_execution_target_tables')) {
    function mg_share_market_execution_target_tables(array $manifest): array
    {
        return match ((string)($manifest['target_type'] ?? '')) {
            'platform_pool' => ['share_market_platform_pools', 'share_market_credit_ledger', 'share_market_admin_events'],
            'merchant_treasury' => ['share_market_credit_treasuries', 'share_market_credit_ledger', 'share_market_admin_events'],
            'market_series' => ['share_market_series', 'share_market_series_redemptions', 'share_market_admin_events'],
            'participant' => ['share_market_enrollments', 'share_market_admin_events'],
            'holder_position' => ['share_market_holder_positions', 'share_market_admin_events'],
            'dave_profile', 'dave_score' => ['dave_scores', 'dave_score_events', 'share_market_admin_events'],
            'ledger_checkpoint' => ['share_market_hash_chain_events', 'share_market_admin_events'],
            default => ['share_market_admin_events'],
        };
    }
}

if (!function_exists('mg_share_market_execution_dry_run_entries')) {
    function mg_share_market_execution_dry_run_entries(array $row, array $manifest, array $projection): array
    {
        $action = (string)($manifest['action'] ?? '');
        $amount = (int)($manifest['amount'] ?? $projection['delta'] ?? 0);
        $targetId = (string)($manifest['target_id'] ?? '');
        $entry = [
            'idempotency_key' => mg_share_market_execution_idempotency_key($row, $manifest),
            'action' => $action,
            'target_type' => (string)($manifest['target_type'] ?? ''),
            'target_id' => $targetId,
            'amount' => abs($amount),
            'entry_type' => 'adjust',
            'reason_code' => 'approval_execution_preview',
            'would_write_tables' => mg_share_market_execution_target_tables($manifest),
            'would_mutate_balance' => false,
            'execution_enabled' => false,
        ];
        if ($action === 'mint_platform_shares' || $action === 'create_master_pool') $entry['entry_type'] = 'mint';
        elseif ($action === 'burn_platform_shares') $entry['entry_type'] = 'burn';
        elseif ($action === 'allocate_merchant_credits') $entry['entry_type'] = 'allocate';
        elseif (str_contains($action, 'freeze')) $entry['entry_type'] = 'freeze';
        elseif (str_contains($action, 'pause')) $entry['entry_type'] = 'adjust';
        return [$entry];
    }
}

if (!function_exists('mg_share_market_execution_transaction_plan')) {
    function mg_share_market_execution_transaction_plan(array $manifest): array
    {
        $tables = mg_share_market_execution_target_tables($manifest);
        return [
            ['step' => 1, 'name' => 'begin_transaction', 'description' => 'Open a database transaction.'],
            ['step' => 2, 'name' => 'lock_approval_request', 'description' => 'Lock the approved approval request by public ID.'],
            ['step' => 3, 'name' => 'verify_approval_state', 'description' => 'Confirm request status is approved and execution_enabled is explicitly false until the runner is unlocked.'],
            ['step' => 4, 'name' => 'lock_targets', 'description' => 'Lock affected target rows: ' . implode(', ', $tables) . '.'],
            ['step' => 5, 'name' => 'apply_domain_mutation', 'description' => 'Future step only. No mutation is available in this PR.'],
            ['step' => 6, 'name' => 'append_ledger_event', 'description' => 'Future step only. Would append idempotent ledger/admin/hash events.'],
            ['step' => 7, 'name' => 'commit_or_rollback', 'description' => 'Future step only. Commit only after all invariants pass.'],
        ];
    }
}

if (!function_exists('mg_share_market_execution_preview_from_row')) {
    function mg_share_market_execution_preview_from_row(array $row, array $viewer): array
    {
        $manifest = mg_share_market_sql_decode($row['manifest_json'] ?? null);
        $projection = mg_share_market_sql_decode($row['projection_json'] ?? null);
        $status = (string)$row['status'];
        $approved = $status === 'approved';
        $idempotencyKey = mg_share_market_execution_idempotency_key($row, $manifest);
        $readyMessage = $approved
            ? 'Approved request is ready for a future execution runner, but execution is still locked.'
            : 'Request must reach approved status before execution can be prepared.';
        return [
            'request_id' => (string)$row['public_id'],
            'approval_status' => $status,
            'handoff_status' => $approved ? 'approved_waiting_for_execution_layer' : 'not_ready',
            'ready_to_execute' => $approved,
            'runner_state' => 'locked_stub',
            'execution_enabled' => false,
            'can_execute' => false,
            'lock_reason' => 'Buy-In execution runner is intentionally disabled. This endpoint prepares execution data only.',
            'message' => $readyMessage,
            'idempotency_key' => $idempotencyKey,
            'action_kind' => mg_share_market_execution_action_kind((string)($manifest['action'] ?? '')),
            'manifest' => $manifest,
            'projection' => $projection,
            'target_locks' => mg_share_market_execution_target_tables($manifest),
            'dry_run_ledger_entries' => mg_share_market_execution_dry_run_entries($row, $manifest, $projection),
            'transaction_plan' => mg_share_market_execution_transaction_plan($manifest),
            'required_checks' => [
                'fresh_admin_authentication' => true,
                'approved_request_required' => true,
                'requester_cannot_self_execute' => true,
                'idempotency_required' => true,
                'balance_invariants_required' => true,
                'hash_chain_required' => true,
                'execution_feature_flag_required' => true,
                'legal_release_required' => true,
            ],
            'viewer_user_id' => (int)($viewer['id'] ?? 0),
        ];
    }
}

if (!function_exists('mg_share_market_execution_preview')) {
    function mg_share_market_execution_preview(PDO $pdo, string $requestId, array $viewer): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $row = mg_share_market_execution_fetch_request_row($pdo, $requestId);
        if (!$row) throw new InvalidArgumentException('Approval request not found.');
        return mg_share_market_execution_preview_from_row($row, $viewer);
    }
}

if (!function_exists('mg_share_market_execution_runner_stub')) {
    function mg_share_market_execution_runner_stub(PDO $pdo, string $requestId, array $viewer): array
    {
        $preview = mg_share_market_execution_preview($pdo, $requestId, $viewer);
        return [
            'runner_invoked' => true,
            'runner_state' => 'locked_stub',
            'executed' => false,
            'execution_enabled' => false,
            'can_execute' => false,
            'request_id' => $requestId,
            'idempotency_key' => (string)$preview['idempotency_key'],
            'message' => 'Execution runner stub is installed and locked. No Share Market action was executed.',
            'preview' => $preview,
        ];
    }
}
