<?php
declare(strict_types=1);

if (!function_exists('mg_share_market_admin_action_definitions')) {
    function mg_share_market_admin_action_definitions(): array
    {
        return [
            'create_master_pool' => [
                'label' => 'Create master pool',
                'event_type' => 'platform_pool_created',
                'target_type' => 'platform_pool',
                'default_target_id' => 'platform-master',
                'risk' => 'critical',
                'confirmation' => 'CREATE MASTER POOL',
                'amount_required' => true,
                'current_state_required' => false,
                'allowed_from_states' => ['absent'],
                'next_state' => 'draft',
                'required_approvals' => 2,
                'super_admin_required' => true,
                'note_required' => true,
                'description' => 'Initialize the single Microgifter-controlled platform share pool.',
            ],
            'mint_platform_shares' => [
                'label' => 'Add shares / mint credits',
                'event_type' => 'platform_pool_minted',
                'target_type' => 'platform_pool',
                'default_target_id' => 'platform-master',
                'risk' => 'critical',
                'confirmation' => 'MINT SHARES',
                'amount_required' => true,
                'current_state_required' => true,
                'allowed_from_states' => ['draft', 'active', 'paused'],
                'next_state' => null,
                'required_approvals' => 2,
                'super_admin_required' => true,
                'note_required' => true,
                'description' => 'Increase master supply through a new append-only issuance event.',
            ],
            'burn_platform_shares' => [
                'label' => 'Burn platform shares',
                'event_type' => 'platform_pool_burned',
                'target_type' => 'platform_pool',
                'default_target_id' => 'platform-master',
                'risk' => 'critical',
                'confirmation' => 'BURN SHARES',
                'amount_required' => true,
                'current_state_required' => true,
                'allowed_from_states' => ['draft', 'active', 'paused', 'frozen'],
                'next_state' => null,
                'required_approvals' => 2,
                'super_admin_required' => true,
                'note_required' => true,
                'description' => 'Permanently remove eligible unallocated or retired supply.',
            ],
            'pause_platform_pool' => [
                'label' => 'Pause global pool',
                'event_type' => 'platform_pool_paused',
                'target_type' => 'platform_pool',
                'default_target_id' => 'platform-master',
                'risk' => 'high',
                'confirmation' => 'PAUSE POOL',
                'amount_required' => false,
                'current_state_required' => true,
                'allowed_from_states' => ['active'],
                'next_state' => 'paused',
                'required_approvals' => 1,
                'super_admin_required' => false,
                'note_required' => true,
                'description' => 'Temporarily stop new issuance and assignment while preserving ownership.',
            ],
            'resume_platform_pool' => [
                'label' => 'Resume global pool',
                'event_type' => 'platform_pool_resumed',
                'target_type' => 'platform_pool',
                'default_target_id' => 'platform-master',
                'risk' => 'high',
                'confirmation' => 'RESUME POOL',
                'amount_required' => false,
                'current_state_required' => true,
                'allowed_from_states' => ['paused'],
                'next_state' => 'active',
                'required_approvals' => 1,
                'super_admin_required' => false,
                'note_required' => true,
                'description' => 'Restore normal pool operations after a documented review.',
            ],
            'freeze_platform_pool' => [
                'label' => 'Freeze platform pool',
                'event_type' => 'platform_pool_frozen',
                'target_type' => 'platform_pool',
                'default_target_id' => 'platform-master',
                'risk' => 'critical',
                'confirmation' => 'FREEZE POOL',
                'amount_required' => false,
                'current_state_required' => true,
                'allowed_from_states' => ['draft', 'active', 'paused'],
                'next_state' => 'frozen',
                'required_approvals' => 2,
                'super_admin_required' => true,
                'note_required' => true,
                'description' => 'Emergency lock on all pool movement and downstream issuance.',
            ],
            'approve_participant' => [
                'label' => 'Approve participant',
                'event_type' => 'merchant_enrollment_approved',
                'target_type' => 'participant',
                'default_target_id' => '',
                'risk' => 'medium',
                'confirmation' => 'APPROVE PARTICIPANT',
                'amount_required' => false,
                'current_state_required' => true,
                'allowed_from_states' => ['interested', 'under_review'],
                'next_state' => 'approved',
                'required_approvals' => 1,
                'super_admin_required' => false,
                'note_required' => true,
                'description' => 'Approve a merchant or artist to enter the optional Share Market program.',
            ],
            'pause_participant' => [
                'label' => 'Pause participant',
                'event_type' => 'merchant_enrollment_paused',
                'target_type' => 'participant',
                'default_target_id' => '',
                'risk' => 'high',
                'confirmation' => 'PAUSE PARTICIPANT',
                'amount_required' => false,
                'current_state_required' => true,
                'allowed_from_states' => ['approved', 'active'],
                'next_state' => 'paused',
                'required_approvals' => 1,
                'super_admin_required' => false,
                'note_required' => true,
                'description' => 'Temporarily stop a participant from creating or operating series.',
            ],
            'allocate_merchant_credits' => [
                'label' => 'Allocate merchant credits',
                'event_type' => 'share_credits_allocated',
                'target_type' => 'merchant_treasury',
                'default_target_id' => '',
                'risk' => 'critical',
                'confirmation' => 'ALLOCATE CREDITS',
                'amount_required' => true,
                'current_state_required' => true,
                'allowed_from_states' => ['approved', 'active'],
                'next_state' => null,
                'required_approvals' => 2,
                'super_admin_required' => false,
                'note_required' => true,
                'description' => 'Move platform share credits into an approved merchant treasury.',
            ],
            'freeze_merchant_treasury' => [
                'label' => 'Freeze merchant treasury',
                'event_type' => 'merchant_treasury_frozen',
                'target_type' => 'merchant_treasury',
                'default_target_id' => '',
                'risk' => 'critical',
                'confirmation' => 'FREEZE TREASURY',
                'amount_required' => false,
                'current_state_required' => true,
                'allowed_from_states' => ['active', 'paused'],
                'next_state' => 'frozen',
                'required_approvals' => 2,
                'super_admin_required' => true,
                'note_required' => true,
                'description' => 'Emergency lock on assignment, issuance, and treasury movement.',
            ],
            'approve_series' => [
                'label' => 'Approve market series',
                'event_type' => 'series_approved',
                'target_type' => 'market_series',
                'default_target_id' => '',
                'risk' => 'high',
                'confirmation' => 'APPROVE SERIES',
                'amount_required' => false,
                'current_state_required' => true,
                'allowed_from_states' => ['submitted'],
                'next_state' => 'approved',
                'required_approvals' => 1,
                'super_admin_required' => false,
                'note_required' => true,
                'description' => 'Approve a reviewed series without automatically making it live.',
            ],
            'pause_series' => [
                'label' => 'Pause market series',
                'event_type' => 'series_paused',
                'target_type' => 'market_series',
                'default_target_id' => '',
                'risk' => 'high',
                'confirmation' => 'PAUSE SERIES',
                'amount_required' => false,
                'current_state_required' => true,
                'allowed_from_states' => ['approved', 'live'],
                'next_state' => 'paused',
                'required_approvals' => 1,
                'super_admin_required' => false,
                'note_required' => true,
                'description' => 'Temporarily stop buys, transfers, resale, and redemption for one series.',
            ],
            'freeze_holder_shares' => [
                'label' => 'Freeze holder shares',
                'event_type' => 'holder_shares_frozen',
                'target_type' => 'holder_position',
                'default_target_id' => '',
                'risk' => 'critical',
                'confirmation' => 'FREEZE HOLDER SHARES',
                'amount_required' => true,
                'current_state_required' => true,
                'allowed_from_states' => ['active', 'listed', 'pending_transfer', 'pending_redemption'],
                'next_state' => 'frozen',
                'required_approvals' => 2,
                'super_admin_required' => true,
                'note_required' => true,
                'description' => 'Lock a specific holder position while preserving the ownership record.',
            ],
            'recalculate_dave_score' => [
                'label' => 'Recalculate DAVE™ score',
                'event_type' => 'dave_score_recalculated',
                'target_type' => 'dave_profile',
                'default_target_id' => '',
                'risk' => 'medium',
                'confirmation' => 'RECALCULATE DAVE',
                'amount_required' => false,
                'current_state_required' => false,
                'allowed_from_states' => [],
                'next_state' => null,
                'required_approvals' => 1,
                'super_admin_required' => false,
                'note_required' => false,
                'description' => 'Request a fresh DAVE™ calculation from current DRM signals.',
            ],
            'lock_dave_score' => [
                'label' => 'Lock DAVE™ score',
                'event_type' => 'dave_score_locked',
                'target_type' => 'dave_profile',
                'default_target_id' => '',
                'risk' => 'high',
                'confirmation' => 'LOCK DAVE SCORE',
                'amount_required' => false,
                'current_state_required' => false,
                'allowed_from_states' => [],
                'next_state' => 'locked',
                'required_approvals' => 2,
                'super_admin_required' => true,
                'note_required' => true,
                'description' => 'Prevent automated score movement during a documented review window.',
            ],
            'publish_proof_hash' => [
                'label' => 'Publish proof hash',
                'event_type' => 'ledger_proof_hash_prepared',
                'target_type' => 'ledger_checkpoint',
                'default_target_id' => '',
                'risk' => 'high',
                'confirmation' => 'PREPARE PROOF HASH',
                'amount_required' => false,
                'current_state_required' => false,
                'allowed_from_states' => [],
                'next_state' => 'prepared',
                'required_approvals' => 2,
                'super_admin_required' => true,
                'note_required' => true,
                'description' => 'Prepare a future blockchain-compatible ledger checkpoint without publishing customer data.',
            ],
        ];
    }
}

if (!function_exists('mg_share_market_admin_reason_codes')) {
    function mg_share_market_admin_reason_codes(): array
    {
        return [
            'initialization',
            'supply_adjustment',
            'merchant_request',
            'security_review',
            'fraud_prevention',
            'redemption_completion',
            'policy_enforcement',
            'operational_recovery',
            'correction',
            'other',
        ];
    }
}

if (!function_exists('mg_share_market_admin_authorized')) {
    function mg_share_market_admin_authorized(array $user): bool
    {
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
        return in_array('super_admin', $roles, true) || in_array('share_market.admin', $permissions, true);
    }
}

if (!function_exists('mg_share_market_admin_is_super_admin')) {
    function mg_share_market_admin_is_super_admin(array $user): bool
    {
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        return in_array('super_admin', $roles, true);
    }
}

if (!function_exists('mg_share_market_admin_canonicalize')) {
    function mg_share_market_admin_canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map('mg_share_market_admin_canonicalize', $value);
        ksort($value);
        foreach ($value as $key => $child) $value[$key] = mg_share_market_admin_canonicalize($child);
        return $value;
    }
}

if (!function_exists('mg_share_market_admin_manifest_id')) {
    function mg_share_market_admin_manifest_id(): string
    {
        if (function_exists('mg_public_uuid')) return mg_public_uuid();
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}

if (!function_exists('mg_share_market_admin_validate_preview')) {
    function mg_share_market_admin_validate_preview(array $input, array $user): array
    {
        if (!mg_share_market_admin_authorized($user)) {
            throw new DomainException('Share Market Admin permission is required.');
        }

        $action = strtolower(trim((string)($input['action'] ?? '')));
        $definitions = mg_share_market_admin_action_definitions();
        if ($action === '' || !isset($definitions[$action])) {
            throw new InvalidArgumentException('Select a valid Share Market admin action.');
        }
        $definition = $definitions[$action];

        if (!empty($definition['super_admin_required']) && !mg_share_market_admin_is_super_admin($user)) {
            throw new DomainException('This action requires super administrator approval.');
        }

        $targetId = trim((string)($input['target_id'] ?? $definition['default_target_id'] ?? ''));
        if ($targetId === '' || strlen($targetId) > 120 || preg_match('/^[A-Za-z0-9][A-Za-z0-9:_-]{0,119}$/', $targetId) !== 1) {
            throw new InvalidArgumentException('Enter a valid target identifier.');
        }

        $reasonCode = strtolower(trim((string)($input['reason_code'] ?? '')));
        if (!in_array($reasonCode, mg_share_market_admin_reason_codes(), true)) {
            throw new InvalidArgumentException('Select a valid reason code.');
        }

        $adminNote = trim((string)($input['admin_note'] ?? ''));
        if (!empty($definition['note_required']) && $adminNote === '') {
            throw new InvalidArgumentException('An internal admin note is required for this action.');
        }
        if (mb_strlen($adminNote) > 1000) {
            throw new InvalidArgumentException('Admin notes cannot exceed 1,000 characters.');
        }

        $amount = null;
        if (!empty($definition['amount_required'])) {
            $rawAmount = filter_var($input['amount'] ?? null, FILTER_VALIDATE_INT);
            if ($rawAmount === false || $rawAmount < 1 || $rawAmount > 1000000000000) {
                throw new InvalidArgumentException('Enter an amount between 1 and 1,000,000,000,000 shares.');
            }
            $amount = (int)$rawAmount;
        }

        $currentState = strtolower(trim((string)($input['current_state'] ?? '')));
        $allowedStates = is_array($definition['allowed_from_states'] ?? null) ? $definition['allowed_from_states'] : [];
        if (!empty($definition['current_state_required'])) {
            if ($currentState === '' || !in_array($currentState, $allowedStates, true)) {
                throw new InvalidArgumentException('The current state is not valid for this transition.');
            }
        } elseif ($currentState !== '' && $allowedStates && !in_array($currentState, $allowedStates, true)) {
            throw new InvalidArgumentException('The current state is not valid for this action.');
        }

        $confirmation = trim((string)($input['confirmation'] ?? ''));
        if (!hash_equals((string)$definition['confirmation'], $confirmation)) {
            throw new InvalidArgumentException('The typed confirmation phrase does not match.');
        }

        $actorId = (int)($user['id'] ?? 0);
        if ($actorId < 1) throw new DomainException('A valid authenticated administrator is required.');

        $manifest = [
            'manifest_id' => mg_share_market_admin_manifest_id(),
            'mode' => 'dry_run',
            'mutation_enabled' => false,
            'schema_status' => 'pending',
            'action' => $action,
            'event_type' => (string)$definition['event_type'],
            'target_type' => (string)$definition['target_type'],
            'target_id' => $targetId,
            'amount' => $amount,
            'current_state' => $currentState !== '' ? $currentState : null,
            'next_state' => $definition['next_state'],
            'reason_code' => $reasonCode,
            'admin_note' => $adminNote,
            'risk' => (string)$definition['risk'],
            'required_approvals' => (int)$definition['required_approvals'],
            'super_admin_required' => (bool)$definition['super_admin_required'],
            'actor_user_id' => $actorId,
            'actor_roles' => array_values(is_array($user['roles'] ?? null) ? $user['roles'] : []),
            'created_at' => gmdate('c'),
        ];

        $hashPayload = $manifest;
        unset($hashPayload['manifest_id'], $hashPayload['created_at']);
        $canonical = mg_share_market_admin_canonicalize($hashPayload);
        $manifest['payload_hash'] = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $manifest['execution_status'] = 'validated_not_executed';

        return [
            'definition' => $definition,
            'manifest' => $manifest,
            'guardrails' => [
                'append_only_required' => true,
                'direct_balance_edits_allowed' => false,
                'database_mutation_performed' => false,
                'second_approval_required' => (int)$definition['required_approvals'] > 1,
                'typed_confirmation_verified' => true,
            ],
        ];
    }
}
