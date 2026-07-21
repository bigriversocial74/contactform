<?php
declare(strict_types=1);

function mg_mcp_automation_definition_playbook(array $grant, string $playbookKey): array
{
    $playbookKey = strtolower(trim($playbookKey));
    $allowed = mg_mcp_automation_json_list($grant['allowed_playbooks_json'] ?? []);
    $catalog = mg_mcp_automation_playbook_catalog();
    $playbook = $catalog[$playbookKey] ?? null;
    if (!is_array($playbook) || !in_array($playbookKey, $allowed, true)) {
        throw new MgMcpAutomationGrantException('The selected playbook is not authorized by this grant.', 403, 'MCP_AUTOMATION_PLAYBOOK_DENIED');
    }
    if (mg_mcp_automation_operation_rank((string)$playbook['operation_class']) > mg_mcp_automation_operation_rank((string)$grant['maximum_operation_class'])) {
        throw new MgMcpAutomationGrantException('The selected playbook exceeds the grant operation ceiling.', 403, 'MCP_AUTOMATION_OPERATION_DENIED');
    }
    return $playbook;
}

function mg_mcp_automation_single_target(mixed $value, string $label): ?string
{
    $targets = mg_mcp_automation_target_ids($value, $label);
    if (count($targets) > 1) {
        throw new MgMcpAutomationGrantException($label . ' accepts one UUID in Phase 4B.');
    }
    return $targets[0] ?? null;
}

function mg_mcp_automation_create_definition(PDO $pdo, array $user, array $input): array
{
    if (!mg_mcp_automation_schema_ready($pdo)) {
        throw new MgMcpAutomationGrantException('The MCP automation foundation has not been imported.', 503, 'MCP_AUTOMATION_SCHEMA_MISSING');
    }
    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) {
        throw new MgMcpAutomationGrantException('Sign in to create an automation definition.', 401, 'MCP_AUTOMATION_AUTH_REQUIRED');
    }

    $name = mg_mcp_automation_text($input['name'] ?? '', 3, 180, 'Automation name');
    $description = trim((string)($input['description'] ?? ''));
    if (mb_strlen($description) > 500) {
        throw new MgMcpAutomationGrantException('Automation description may contain at most 500 characters.');
    }
    $objective = mg_mcp_automation_text($input['objective'] ?? '', 10, 1000, 'Simulation objective');
    $riskLevel = strtolower(trim((string)($input['risk_level'] ?? 'low')));
    if (!in_array($riskLevel, MG_MCP_AUTOMATION_GRANT_RISK_LEVELS, true)) {
        throw new MgMcpAutomationGrantException('Phase 4B simulations allow only low or medium risk.');
    }
    $proposedAmount = mg_mcp_automation_optional_uint($input['proposed_amount_cents'] ?? null, 100000000, 'Proposed amount');
    $proposedQuantity = mg_mcp_automation_optional_uint($input['proposed_quantity'] ?? null, 1000000, 'Proposed quantity');
    $targetContext = array_filter([
        'product_id' => mg_mcp_automation_single_target($input['product_id'] ?? '', 'Product target'),
        'campaign_id' => mg_mcp_automation_single_target($input['campaign_id'] ?? '', 'Campaign target'),
        'reward_template_id' => mg_mcp_automation_single_target($input['reward_template_id'] ?? '', 'Reward-template target'),
    ], static fn(mixed $value): bool => $value !== null);
    if (array_key_exists('recipient_is_existing_contact', $input)) {
        $targetContext['recipient_is_existing_contact'] = !empty($input['recipient_is_existing_contact']);
    }

    $pdo->beginTransaction();
    try {
        $grant = mg_mcp_automation_lock_owner_grant($pdo, $userId, trim((string)($input['grant_id'] ?? '')));
        if ((string)$grant['status'] !== 'active') {
            throw new MgMcpAutomationGrantException('Activate the automation grant before creating a definition.', 409, 'MCP_AUTOMATION_GRANT_INACTIVE');
        }
        mg_mcp_automation_assert_grant_activatable($pdo, $grant);
        $playbookKey = strtolower(trim((string)($input['playbook_key'] ?? '')));
        $playbook = mg_mcp_automation_definition_playbook($grant, $playbookKey);

        $riskRank = ['low' => 10, 'medium' => 20, 'high' => 30, 'critical' => 40];
        if (($riskRank[$riskLevel] ?? 1000) > ($riskRank[(string)$grant['risk_ceiling']] ?? 0)) {
            throw new MgMcpAutomationGrantException('The simulation risk exceeds the grant risk ceiling.', 403, 'MCP_AUTOMATION_RISK_DENIED');
        }

        $publicId = mg_public_uuid();
        $configuration = [
            'phase' => 'phase4b',
            'mode' => 'manual_simulation_only',
            'simulation_only' => true,
            'execution_requested' => false,
            'objective' => $objective,
            'risk_level' => $riskLevel,
            'proposed_amount_cents' => $proposedAmount ?? 0,
            'proposed_quantity' => $proposedQuantity ?? 0,
            'target_context' => $targetContext,
            'grant_revocation_version' => (int)$grant['revocation_version'],
            'playbook_operation_class' => (string)$playbook['operation_class'],
        ];
        $stmt = $pdo->prepare(
            "INSERT INTO mcp_automations
             (public_id,grant_id,owner_user_id,workspace_type,workspace_id,name,playbook_key,description,status,configuration_json,timezone,current_version,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,'draft',?,'UTC',1,NOW(),NOW())"
        );
        $stmt->execute([
            $publicId,
            (int)$grant['id'],
            $userId,
            $grant['workspace_type'] !== null ? (string)$grant['workspace_type'] : null,
            $grant['workspace_id'] !== null ? (int)$grant['workspace_id'] : null,
            $name,
            $playbookKey,
            $description !== '' ? $description : null,
            json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
        $automationId = (int)$pdo->lastInsertId();
        $triggerPublicId = mg_public_uuid();
        $pdo->prepare(
            "INSERT INTO mcp_automation_triggers
             (public_id,automation_id,trigger_type,status,configuration_json,created_at,updated_at)
             VALUES (?,?,'manual','paused',?,NOW(),NOW())"
        )->execute([
            $triggerPublicId,
            $automationId,
            json_encode([
                'simulation_only' => true,
                'owner_initiated' => true,
                'scheduler_enabled' => false,
            ], JSON_THROW_ON_ERROR),
        ]);

        $connection = [
            'id' => (int)$grant['connection_id'],
            'client_id' => (int)$grant['client_id'],
            'workspace_type' => $grant['workspace_type'],
            'workspace_id' => $grant['workspace_id'],
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_definition.created', 'Owner created a draft MCP automation definition.', [
            'automation_public_id' => $publicId,
            'grant_public_id' => (string)$grant['public_id'],
            'playbook_key' => $playbookKey,
            'simulation_only' => true,
            'execution_enabled' => false,
        ]);
        $pdo->commit();

        $metadata = [
            'automation_public_id' => $publicId,
            'grant_public_id' => (string)$grant['public_id'],
            'playbook_key' => $playbookKey,
            'simulation_only' => true,
            'execution_enabled' => false,
        ];
        mg_audit('mcp_automation_definition_created', 'mcp_automation', $metadata, $userId);
        mg_event('mcp.automation_definition.created', $metadata, $userId);
        mg_security_log('info', 'mcp.automation_definition.created', 'Owner created a draft MCP automation definition.', $metadata, $userId);
        return ['id' => $publicId, 'status' => 'draft'] + $metadata;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
