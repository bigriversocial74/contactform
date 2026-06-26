<?php
declare(strict_types=1);

require_once __DIR__ . '/execution-gate.php';

if (!function_exists('mg_share_market_simulator_source_table')) {
    function mg_share_market_simulator_source_table(string $targetType): string
    {
        return match ($targetType) {
            'platform_pool' => 'share_market_platform_pools',
            'merchant_treasury' => 'share_market_credit_treasuries',
            'market_series' => 'share_market_series',
            'participant' => 'share_market_enrollments',
            'holder_position' => 'share_market_holder_positions',
            'dave_profile', 'dave_score' => 'dave_scores',
            'ledger_checkpoint' => 'share_market_hash_chain_events',
            default => 'share_market_admin_events',
        };
    }
}

if (!function_exists('mg_share_market_simulator_expected_status')) {
    function mg_share_market_simulator_expected_status(string $action): ?string
    {
        return match ($action) {
            'pause_platform_pool', 'pause_participant', 'pause_series' => 'paused',
            'resume_platform_pool' => 'active',
            'freeze_platform_pool', 'freeze_merchant_treasury', 'freeze_holder_shares' => 'frozen',
            'approve_participant', 'approve_series' => 'approved',
            'lock_dave_score' => 'locked',
            default => null,
        };
    }
}

if (!function_exists('mg_share_market_simulator_balance_field')) {
    function mg_share_market_simulator_balance_field(array $manifest): ?string
    {
        return match ((string)($manifest['action'] ?? '')) {
            'create_master_pool', 'mint_platform_shares', 'burn_platform_shares' => 'total_minted',
            'allocate_merchant_credits' => 'credits_allocated',
            default => null,
        };
    }
}

if (!function_exists('mg_share_market_simulator_fetch_target_snapshot')) {
    function mg_share_market_simulator_fetch_target_snapshot(PDO $pdo, array $manifest): array
    {
        $targetType = (string)($manifest['target_type'] ?? '');
        $targetId = (string)($manifest['target_id'] ?? '');
        if ($targetType === '' || $targetId === '') return ['exists' => false, 'target_type' => $targetType, 'target_id' => $targetId, 'row' => null];

        $row = null;
        if ($targetType === 'platform_pool') {
            $stmt = $pdo->prepare('SELECT id,public_id,name,pool_type,status,total_authorized,total_minted,total_burned,total_allocated,total_available,proof_hash,updated_at FROM share_market_platform_pools WHERE public_id=? LIMIT 1');
            $stmt->execute([$targetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($targetType === 'merchant_treasury') {
            $stmt = $pdo->prepare('SELECT id,public_id,enrollment_id,participant_user_id,status,credits_allocated,credits_available,credits_assigned,credits_circulating,credits_redeemed,credits_burned,credits_frozen,updated_at FROM share_market_credit_treasuries WHERE public_id=? LIMIT 1');
            $stmt->execute([$targetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($targetType === 'market_series') {
            $stmt = $pdo->prepare('SELECT id,public_id,enrollment_id,participant_user_id,treasury_id,name,state,supply,launch_price_cents,currency,max_per_buyer,redemption_enabled,resale_enabled,approved_at,live_at,updated_at FROM share_market_series WHERE public_id=? LIMIT 1');
            $stmt->execute([$targetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($targetType === 'participant') {
            $stmt = $pdo->prepare('SELECT id,public_id,participant_user_id,participant_type,legal_name,public_name,status,updated_at FROM share_market_enrollments WHERE public_id=? LIMIT 1');
            $stmt->execute([$targetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($targetType === 'holder_position') {
            $stmt = $pdo->prepare('SELECT id,public_id,series_id,holder_user_id,status,shares_total,shares_available,shares_listed,shares_redeemed,shares_frozen,last_activity_at,updated_at FROM share_market_holder_positions WHERE public_id=? LIMIT 1');
            $stmt->execute([$targetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif (in_array($targetType, ['dave_profile','dave_score'], true)) {
            $stmt = $pdo->prepare('SELECT id,public_id,target_type,target_public_id,formula_version,status,dave_score,confidence_score,locked_at,updated_at FROM dave_scores WHERE public_id=? OR target_public_id=? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$targetId, $targetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($targetType === 'ledger_checkpoint') {
            $stmt = $pdo->prepare('SELECT id,public_id,chain_key,source_table,source_public_id,payload_hash,checkpoint_status,published_at,created_at FROM share_market_hash_chain_events WHERE public_id=? OR source_public_id=? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$targetId, $targetId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return [
            'exists' => is_array($row),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'source_table' => mg_share_market_simulator_source_table($targetType),
            'row' => $row,
        ];
    }
}

if (!function_exists('mg_share_market_simulator_actual_balance')) {
    function mg_share_market_simulator_actual_balance(array $snapshot, ?string $field): ?int
    {
        if (!$field || empty($snapshot['row']) || !array_key_exists($field, $snapshot['row'])) return null;
        return (int)$snapshot['row'][$field];
    }
}

if (!function_exists('mg_share_market_simulator_project_effects')) {
    function mg_share_market_simulator_project_effects(array $manifest, array $projection, array $snapshot): array
    {
        $action = (string)($manifest['action'] ?? '');
        $amount = abs((int)($manifest['amount'] ?? $projection['delta'] ?? 0));
        $field = mg_share_market_simulator_balance_field($manifest);
        $actualBalance = mg_share_market_simulator_actual_balance($snapshot, $field);
        $expectedStatus = mg_share_market_simulator_expected_status($action);
        $effects = [];

        if ($field !== null) {
            $projected = ($projection['type'] ?? '') === 'balance' ? (int)($projection['projected_balance'] ?? 0) : null;
            $effects[] = [
                'type' => 'balance_projection',
                'field' => $field,
                'amount' => $amount,
                'actual_now' => $actualBalance,
                'approval_current_balance' => ($projection['type'] ?? '') === 'balance' ? (int)($projection['current_balance'] ?? 0) : null,
                'approval_projected_balance' => $projected,
                'simulated_after' => $projected,
                'actual_equals_approval_current' => $actualBalance === null || ($projection['type'] ?? '') !== 'balance' ? null : $actualBalance === (int)$projection['current_balance'],
            ];
        }

        if ($expectedStatus !== null) {
            $stateField = array_key_exists('state', $snapshot['row'] ?? []) ? 'state' : 'status';
            $effects[] = [
                'type' => 'state_projection',
                'field' => $stateField,
                'actual_now' => isset($snapshot['row'][$stateField]) ? (string)$snapshot['row'][$stateField] : null,
                'simulated_after' => $expectedStatus,
                'actual_already_matches' => isset($snapshot['row'][$stateField]) ? (string)$snapshot['row'][$stateField] === $expectedStatus : false,
            ];
        }

        if (!$effects) {
            $effects[] = [
                'type' => 'audit_only_projection',
                'field' => null,
                'actual_now' => null,
                'simulated_after' => 'append audit/hash-chain event only',
            ];
        }

        return $effects;
    }
}

if (!function_exists('mg_share_market_simulator_reconcile')) {
    function mg_share_market_simulator_reconcile(array $effects, array $snapshot, array $gate): array
    {
        $mismatches = [];
        foreach ($effects as $effect) {
            if (($effect['type'] ?? '') === 'balance_projection' && ($effect['actual_equals_approval_current'] ?? null) === false) {
                $mismatches[] = [
                    'key' => 'approval_current_balance_mismatch',
                    'message' => 'The approval projection current balance does not match the current database snapshot.',
                    'field' => $effect['field'] ?? null,
                    'actual_now' => $effect['actual_now'] ?? null,
                    'approval_current_balance' => $effect['approval_current_balance'] ?? null,
                ];
            }
        }

        return [
            'status' => $mismatches ? 'mismatch' : 'reconciled_dry_run',
            'mutations_performed' => false,
            'target_exists' => (bool)($snapshot['exists'] ?? false),
            'gate_blocked' => (bool)($gate['blocked'] ?? true),
            'mismatches' => $mismatches,
            'assertions' => [
                'database_writes' => 'none',
                'ledger_entries_inserted' => 0,
                'balances_changed' => false,
                'market_state_changed' => false,
            ],
        ];
    }
}

if (!function_exists('mg_share_market_ledger_simulation')) {
    function mg_share_market_ledger_simulation(PDO $pdo, string $requestId, array $viewer, string $runMode = 'dry_run'): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $row = mg_share_market_execution_fetch_request_row($pdo, $requestId);
        if (!$row) throw new InvalidArgumentException('Approval request not found.');
        $preview = mg_share_market_execution_preview_with_gate($pdo, $requestId, $viewer, $runMode);
        $manifest = mg_share_market_sql_decode($row['manifest_json'] ?? null);
        $projection = mg_share_market_sql_decode($row['projection_json'] ?? null);
        $snapshot = mg_share_market_simulator_fetch_target_snapshot($pdo, $manifest);
        $effects = mg_share_market_simulator_project_effects($manifest, $projection, $snapshot);
        $dryRunEntries = mg_share_market_execution_dry_run_entries($row, $manifest, $projection);
        $reconciliation = mg_share_market_simulator_reconcile($effects, $snapshot, $preview['release_gate'] ?? []);

        return [
            'simulator_version' => 'phase_9_ledger_simulator_v1',
            'run_mode' => $runMode === 'live' ? 'live_requested_but_blocked' : 'dry_run',
            'request_id' => $requestId,
            'executed' => false,
            'mutations_performed' => false,
            'approval_status' => (string)($row['status'] ?? ''),
            'idempotency_key' => (string)$preview['idempotency_key'],
            'target_snapshot' => $snapshot,
            'simulated_effects' => $effects,
            'dry_run_ledger_entries' => $dryRunEntries,
            'reconciliation' => $reconciliation,
            'release_gate' => $preview['release_gate'] ?? null,
            'preview' => $preview,
        ];
    }
}
