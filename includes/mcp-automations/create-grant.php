<?php
declare(strict_types=1);

function mg_mcp_automation_create_grant(PDO $pdo, array $user, array $input): array
{
    if (!mg_mcp_automation_schema_ready($pdo)) {
        throw new MgMcpAutomationGrantException('The MCP automation foundation has not been imported.', 503, 'MCP_AUTOMATION_SCHEMA_MISSING');
    }

    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) {
        throw new MgMcpAutomationGrantException('Sign in to create an automation grant.', 401, 'MCP_AUTOMATION_AUTH_REQUIRED');
    }

    $label = mg_mcp_automation_text($input['label'] ?? '', 3, 120, 'Grant name');
    $reason = mg_mcp_automation_text($input['reason'] ?? '', 10, 500, 'Authorization reason');
    $riskCeiling = strtolower(trim((string)($input['risk_ceiling'] ?? 'low')));
    if (!in_array($riskCeiling, MG_MCP_AUTOMATION_GRANT_RISK_LEVELS, true)) {
        throw new MgMcpAutomationGrantException('Select a supported risk ceiling.');
    }
    $expiresDays = (int)($input['expires_days'] ?? 30);
    if (!in_array($expiresDays, [7, 30, 90, 180, 365], true)) {
        throw new MgMcpAutomationGrantException('Select a supported expiration period.');
    }

    $currency = strtoupper(trim((string)($input['currency'] ?? 'USD')));
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        throw new MgMcpAutomationGrantException('Enter a valid three-letter currency code.');
    }

    $limits = [
        'per_run_amount_limit_cents' => mg_mcp_automation_optional_uint($input['per_run_amount_limit_cents'] ?? null, 100000000, 'Per-run amount limit'),
        'daily_amount_limit_cents' => mg_mcp_automation_optional_uint($input['daily_amount_limit_cents'] ?? null, 1000000000, 'Daily amount limit'),
        'lifetime_amount_limit_cents' => mg_mcp_automation_optional_uint($input['lifetime_amount_limit_cents'] ?? null, 10000000000, 'Lifetime amount limit'),
        'per_run_quantity_limit' => mg_mcp_automation_optional_uint($input['per_run_quantity_limit'] ?? null, 1000000, 'Per-run quantity limit'),
        'daily_quantity_limit' => mg_mcp_automation_optional_uint($input['daily_quantity_limit'] ?? null, 10000000, 'Daily quantity limit'),
        'lifetime_quantity_limit' => mg_mcp_automation_optional_uint($input['lifetime_quantity_limit'] ?? null, 100000000, 'Lifetime quantity limit'),
        'minimum_frequency_seconds' => mg_mcp_automation_optional_uint($input['minimum_frequency_seconds'] ?? null, 31536000, 'Minimum frequency'),
    ];
    if ($limits['minimum_frequency_seconds'] !== null && $limits['minimum_frequency_seconds'] < 3600) {
        throw new MgMcpAutomationGrantException('Automation grants cannot authorize a frequency faster than once per hour.');
    }

    $targetPolicy = [
        'owner_label' => $label,
        'owner_reason' => $reason,
        'merchant_workspace_only' => false,
        'allow_all_published_catalog' => !empty($input['allow_all_published_catalog']),
        'allow_existing_contacts_only' => !empty($input['allow_existing_contacts_only']),
        'allowed_product_ids' => mg_mcp_automation_target_ids($input['allowed_product_ids'] ?? '', 'Allowed products'),
        'allowed_campaign_ids' => mg_mcp_automation_target_public_ids($input['allowed_campaign_ids'] ?? '', 'Allowed Creator Campaigns'),
        'allowed_reward_template_ids' => mg_mcp_automation_target_ids($input['allowed_reward_template_ids'] ?? '', 'Allowed reward templates'),
        'external_client_direct_execution' => false,
        'owner_approval_required' => true,
        'owner_execution_required' => true,
    ];

    $pdo->beginTransaction();
    try {
        $connection = mg_mcp_automation_lock_owner_connection(
            $pdo,
            $userId,
            trim((string)($input['connection_id'] ?? ''))
        );
        $scopes = mg_mcp_automation_connection_scopes($pdo, (int)$connection['id']);
        $targetPolicy['merchant_workspace_only'] = $connection['workspace_id'] !== null;
        $authority = mg_mcp_automation_normalize_playbooks($input, $connection, $scopes);
        if ((string)$authority['maximum_operation_class'] === 'approval_gated' && $riskCeiling !== 'critical') {
            throw new MgMcpAutomationGrantException('Creator Campaign approval-gated playbooks require a critical risk ceiling because their fixed catalogs include critical actions.');
        }

        $publicId = mg_public_uuid();
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . $expiresDays . ' days')
            ->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "INSERT INTO mcp_automation_grants
             (public_id,connection_id,authorizing_user_id,workspace_type,workspace_id,status,maximum_operation_class,
              allowed_tools_json,allowed_playbooks_json,allowed_trigger_types_json,approval_policy,risk_ceiling,currency,
              per_run_amount_limit_cents,daily_amount_limit_cents,lifetime_amount_limit_cents,
              per_run_quantity_limit,daily_quantity_limit,lifetime_quantity_limit,minimum_frequency_seconds,
              maximum_concurrent_runs,target_policy_json,starts_at,expires_at,revocation_version,created_at,updated_at)
             VALUES (?,?,?,?,?,'draft',?,?,?,?, 'always',?,?,?,?,?,?,?,?,?,1,?,NOW(),?,1,NOW(),NOW())"
        );
        $stmt->execute([
            $publicId,
            (int)$connection['id'],
            $userId,
            $connection['workspace_type'] !== null ? (string)$connection['workspace_type'] : null,
            $connection['workspace_id'] !== null ? (int)$connection['workspace_id'] : null,
            (string)$authority['maximum_operation_class'],
            json_encode($authority['tools'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            json_encode($authority['playbooks'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            json_encode(['manual'], JSON_THROW_ON_ERROR),
            $riskCeiling,
            $currency,
            $limits['per_run_amount_limit_cents'],
            $limits['daily_amount_limit_cents'],
            $limits['lifetime_amount_limit_cents'],
            $limits['per_run_quantity_limit'],
            $limits['daily_quantity_limit'],
            $limits['lifetime_quantity_limit'],
            $limits['minimum_frequency_seconds'],
            json_encode($targetPolicy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $expiresAt,
        ]);

        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_grant.created', 'Owner created a draft MCP automation grant.', [
            'grant_public_id' => $publicId,
            'maximum_operation_class' => $authority['maximum_operation_class'],
            'playbooks' => $authority['playbooks'],
            'execution_enabled' => false,
            'external_client_direct_execution' => false,
            'owner_approval_required' => true,
            'owner_execution_required' => (string)$authority['maximum_operation_class'] === 'approval_gated',
        ], (string)$authority['maximum_operation_class'] === 'approval_gated' ? 'high' : 'info');
        $pdo->commit();

        $metadata = [
            'grant_public_id' => $publicId,
            'connection_public_id' => (string)$connection['public_id'],
            'playbooks' => $authority['playbooks'],
            'maximum_operation_class' => $authority['maximum_operation_class'],
            'execution_enabled' => false,
            'external_client_direct_execution' => false,
            'owner_approval_required' => true,
            'owner_execution_required' => (string)$authority['maximum_operation_class'] === 'approval_gated',
        ];
        mg_audit('mcp_automation_grant_created', 'mcp_automation_grant', $metadata, $userId);
        mg_event('mcp.automation_grant.created', $metadata, $userId);
        mg_security_log('info', 'mcp.automation_grant.created', 'Owner created a draft MCP automation grant.', $metadata, $userId);

        return ['id' => $publicId, 'status' => 'draft'] + $metadata;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
