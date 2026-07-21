<?php
declare(strict_types=1);

function mg_mcp_automation_next_recurring_due(string $dueAt, int $intervalSeconds): string
{
    $intervalSeconds = max(3600, $intervalSeconds);
    $next = new DateTimeImmutable($dueAt, new DateTimeZone('UTC'));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    do {
        $next = $next->modify('+' . $intervalSeconds . ' seconds');
    } while ($next <= $now);
    return $next->format('Y-m-d H:i:s');
}

function mg_mcp_automation_run_simulation_with_trigger(
    PDO $pdo,
    array $user,
    string $automationPublicId,
    string $requestedTriggerType,
    ?string $triggerPublicId = null
): array {
    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) {
        throw new MgMcpAutomationGrantException('Sign in to run an automation simulation.', 401, 'MCP_AUTOMATION_AUTH_REQUIRED');
    }
    $scheduled = in_array($requestedTriggerType, ['fixed_schedule', 'recurring_schedule'], true);
    if (!$scheduled && $requestedTriggerType !== 'manual') {
        throw new MgMcpAutomationGrantException('Unsupported simulation trigger.', 422, 'MCP_AUTOMATION_TRIGGER_TYPE_INVALID');
    }

    $pdo->beginTransaction();
    try {
        $automation = mg_mcp_automation_lock_owner_definition($pdo, $userId, $automationPublicId);
        if ((string)$automation['status'] !== 'active') {
            throw new MgMcpAutomationGrantException('Activate the automation definition before running a simulation.', 409, 'MCP_AUTOMATION_DEFINITION_INACTIVE');
        }
        if ((string)$automation['grant_status'] !== 'active') {
            throw new MgMcpAutomationGrantException('The parent automation grant is not active.', 409, 'MCP_AUTOMATION_GRANT_INACTIVE');
        }

        $grant = mg_mcp_automation_definition_grant_projection($automation);
        mg_mcp_automation_assert_grant_activatable($pdo, $grant);
        $playbook = mg_mcp_automation_definition_playbook($grant, (string)$automation['playbook_key']);
        $configuration = mg_mcp_automation_json_object($automation['configuration_json']);
        if (($configuration['simulation_only'] ?? null) !== true || ($configuration['execution_requested'] ?? true) !== false) {
            throw new MgMcpAutomationGrantException('The automation definition is outside the simulation-only boundary.', 409, 'MCP_AUTOMATION_SIMULATION_BOUNDARY');
        }

        $riskLevel = (string)($configuration['risk_level'] ?? 'low');
        $proposedAmount = max(0, (int)($configuration['proposed_amount_cents'] ?? 0));
        $proposedQuantity = max(0, (int)($configuration['proposed_quantity'] ?? 0));
        $targetContext = is_array($configuration['target_context'] ?? null) ? $configuration['target_context'] : [];
        $authorizedTools = [];
        foreach ((array)$playbook['tools'] as $toolName => $_scope) {
            $authorization = mg_mcp_automation_authorize_grant_action(
                $pdo,
                (string)$automation['connection_public_id'],
                (string)$automation['grant_public_id'],
                (string)$toolName,
                (string)$playbook['operation_class'],
                $riskLevel,
                $proposedAmount,
                $proposedQuantity,
                $targetContext
            );
            if (($authorization['execution_enabled'] ?? true) !== false) {
                throw new MgMcpAutomationGrantException('Simulation requires the grant evaluator to remain execution-disabled.', 409, 'MCP_AUTOMATION_EXECUTION_BOUNDARY');
            }
            $authorizedTools[] = (string)$toolName;
        }

        if ($scheduled) {
            $triggerStmt = $pdo->prepare(
                "SELECT id,public_id,status,trigger_type,configuration_json,next_due_at
                 FROM mcp_automation_triggers
                 WHERE automation_id=? AND public_id=? AND trigger_type=? LIMIT 1 FOR UPDATE"
            );
            $triggerStmt->execute([(int)$automation['id'], (string)$triggerPublicId, $requestedTriggerType]);
        } else {
            $triggerStmt = $pdo->prepare(
                "SELECT id,public_id,status,trigger_type,configuration_json,next_due_at
                 FROM mcp_automation_triggers WHERE automation_id=? AND trigger_type='manual' LIMIT 1 FOR UPDATE"
            );
            $triggerStmt->execute([(int)$automation['id']]);
        }
        $trigger = $triggerStmt->fetch(PDO::FETCH_ASSOC);
        if (!$trigger || (string)$trigger['status'] !== 'active') {
            throw new MgMcpAutomationGrantException('The simulation trigger is not active.', 409, 'MCP_AUTOMATION_TRIGGER_INACTIVE');
        }
        $triggerConfiguration = mg_mcp_automation_json_object($trigger['configuration_json']);
        if ($scheduled && !in_array((string)$trigger['trigger_type'], mg_mcp_automation_json_list($automation['grant_allowed_trigger_types_json'] ?? []), true)) {
            throw new MgMcpAutomationGrantException('The parent grant no longer authorizes this schedule type.', 403, 'MCP_AUTOMATION_TRIGGER_DENIED');
        }
        $dueAt = $scheduled ? (string)($trigger['next_due_at'] ?? '') : gmdate('Y-m-d H:i:s');
        if ($scheduled && ($dueAt === '' || strtotime($dueAt . ' UTC') > time())) {
            throw new MgMcpAutomationGrantException('The scheduled simulation is not due.', 409, 'MCP_AUTOMATION_TRIGGER_NOT_DUE');
        }

        $runPublicId = mg_public_uuid();
        $mode = $scheduled ? 'scheduled_simulation_only' : 'manual_simulation_only';
        $runKey = $scheduled
            ? 'phase4c-sim:' . hash('sha256', (string)$trigger['public_id'] . '|' . $dueAt)
            : 'phase4b-sim:' . $runPublicId;
        $inputFingerprint = hash('sha256', json_encode([
            'automation' => $automationPublicId,
            'version' => (int)$automation['current_version'],
            'grant_revocation_version' => (int)$automation['revocation_version'],
            'configuration' => $configuration,
            'trigger_type' => (string)$trigger['trigger_type'],
            'scheduled_at' => $dueAt,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $now = gmdate('Y-m-d H:i:s');
        $pdo->prepare(
            "INSERT INTO mcp_automation_runs
             (public_id,automation_id,grant_id,trigger_id,status,idempotency_key,input_fingerprint,scheduled_at,queued_at,started_at,completed_at,attempt,maximum_attempts,output_summary_json,created_at,updated_at)
             VALUES (?,?,?,?,'succeeded',?,?,?,?,?,?,1,1,?,NOW(),NOW())"
        )->execute([
            $runPublicId,
            (int)$automation['id'],
            (int)$automation['grant_id'],
            (int)$trigger['id'],
            $runKey,
            $inputFingerprint,
            $dueAt,
            $now,
            $now,
            $now,
            json_encode([
                'mode' => $mode,
                'policy_result' => 'authorized_for_simulation',
                'trigger_type' => (string)$trigger['trigger_type'],
                'trigger_public_id' => (string)$trigger['public_id'],
                'scheduler_enabled' => false,
                'owner_manual_due_evaluation' => $scheduled,
                'execution_attempted' => false,
                'external_effect' => false,
                'action_receipts_created' => 0,
                'authorized_tools' => $authorizedTools,
                'playbook_key' => (string)$automation['playbook_key'],
                'risk_level' => $riskLevel,
                'proposed_amount_cents' => $proposedAmount,
                'proposed_quantity' => $proposedQuantity,
                'target_context' => $targetContext,
                'grant_revocation_version' => (int)$automation['revocation_version'],
                'automation_version' => (int)$automation['current_version'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
        $runId = (int)$pdo->lastInsertId();

        foreach ($authorizedTools as $index => $toolName) {
            $sequence = $index + 1;
            $actionPublicId = mg_public_uuid();
            $actionKey = $runKey . ':action:' . $sequence;
            $freshStateToken = 'sim:' . hash('sha256', implode('|', [
                $automationPublicId,
                (string)$automation['current_version'],
                (string)$automation['revocation_version'],
                $toolName,
                $inputFingerprint,
            ]));
            $sanitized = [
                'mode' => 'simulation_only',
                'objective' => (string)($configuration['objective'] ?? ''),
                'target_context' => $targetContext,
                'proposed_amount_cents' => $proposedAmount,
                'proposed_quantity' => $proposedQuantity,
                'trigger_type' => (string)$trigger['trigger_type'],
                'execution_requested' => false,
            ];
            $pdo->prepare(
                "INSERT INTO mcp_automation_actions
                 (public_id,run_id,sequence_number,tool_name,tool_version,operation_class,risk_level,status,approval_required,idempotency_key,input_fingerprint,sanitized_input_json,fresh_state_token,proposed_amount_cents,proposed_quantity,created_at,updated_at)
                 VALUES (?,?,?,?,'1.0',?,?,'proposed',1,?,?,?,?,?,?,NOW(),NOW())"
            )->execute([
                $actionPublicId,
                $runId,
                $sequence,
                $toolName,
                (string)$playbook['operation_class'],
                $riskLevel,
                $actionKey,
                hash('sha256', json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $freshStateToken,
                $proposedAmount > 0 ? $proposedAmount : null,
                $proposedQuantity > 0 ? $proposedQuantity : null,
            ]);
        }

        $nextDueAt = null;
        $nextTriggerStatus = 'active';
        if ($scheduled && (string)$trigger['trigger_type'] === 'recurring_schedule') {
            $intervalSeconds = (int)($triggerConfiguration['interval_seconds'] ?? 0);
            if (!in_array($intervalSeconds, MG_MCP_AUTOMATION_SCHEDULE_INTERVALS, true)) {
                throw new MgMcpAutomationGrantException('The recurring schedule interval is invalid.', 409, 'MCP_AUTOMATION_INTERVAL_INVALID');
            }
            $nextDueAt = mg_mcp_automation_next_recurring_due($dueAt, $intervalSeconds);
        } elseif ($scheduled) {
            $nextTriggerStatus = 'expired';
        }

        $pdo->prepare(
            'UPDATE mcp_automation_triggers SET status=?,next_due_at=?,last_fired_at=NOW(),fire_count=fire_count+1,updated_at=NOW() WHERE id=?'
        )->execute([$nextTriggerStatus, $nextDueAt, (int)$trigger['id']]);
        if ($scheduled) {
            $pdo->prepare('UPDATE mcp_automations SET last_run_at=NOW(),next_run_at=?,updated_at=NOW() WHERE id=?')
                ->execute([$nextDueAt, (int)$automation['id']]);
        } else {
            $pdo->prepare('UPDATE mcp_automations SET last_run_at=NOW(),updated_at=NOW() WHERE id=?')
                ->execute([(int)$automation['id']]);
        }

        $connection = [
            'id' => (int)$automation['connection_id'],
            'client_id' => (int)$automation['client_id'],
            'workspace_type' => $automation['grant_workspace_type'],
            'workspace_id' => $automation['grant_workspace_id'],
        ];
        mg_mcp_automation_insert_security_event($pdo, $connection, $userId, 'mcp.automation_simulation.completed', 'Owner completed an MCP automation policy simulation.', [
            'automation_public_id' => $automationPublicId,
            'run_public_id' => $runPublicId,
            'trigger_type' => (string)$trigger['trigger_type'],
            'playbook_key' => (string)$automation['playbook_key'],
            'authorized_tools' => $authorizedTools,
            'next_due_at' => $nextDueAt,
            'scheduler_enabled' => false,
            'execution_attempted' => false,
            'action_receipts_created' => 0,
        ]);
        $pdo->commit();

        $metadata = [
            'automation_public_id' => $automationPublicId,
            'run_public_id' => $runPublicId,
            'trigger_type' => (string)$trigger['trigger_type'],
            'playbook_key' => (string)$automation['playbook_key'],
            'authorized_tools' => $authorizedTools,
            'next_due_at' => $nextDueAt,
            'scheduler_enabled' => false,
            'execution_attempted' => false,
            'external_effect' => false,
            'action_receipts_created' => 0,
        ];
        mg_audit('mcp_automation_simulation_completed', 'mcp_automation_run', $metadata, $userId);
        mg_event('mcp.automation_simulation.completed', $metadata, $userId);
        mg_security_log('info', 'mcp.automation_simulation.completed', 'Owner completed an MCP automation policy simulation.', $metadata, $userId);
        return ['id' => $runPublicId, 'status' => 'succeeded'] + $metadata;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function mg_mcp_automation_run_simulation(PDO $pdo, array $user, string $automationPublicId): array
{
    return mg_mcp_automation_run_simulation_with_trigger($pdo, $user, $automationPublicId, 'manual');
}

function mg_mcp_automation_run_scheduled_simulation(PDO $pdo, array $user, string $automationPublicId, string $triggerPublicId): array
{
    $stmt = $pdo->prepare('SELECT trigger_type FROM mcp_automation_triggers WHERE public_id=? LIMIT 1');
    $stmt->execute([$triggerPublicId]);
    $triggerType = (string)($stmt->fetchColumn() ?: '');
    if (!in_array($triggerType, MG_MCP_AUTOMATION_SCHEDULE_TRIGGER_TYPES, true)) {
        throw new MgMcpAutomationGrantException('Scheduled simulation trigger not found.', 404, 'MCP_AUTOMATION_TRIGGER_NOT_FOUND');
    }
    return mg_mcp_automation_run_simulation_with_trigger($pdo, $user, $automationPublicId, $triggerType, $triggerPublicId);
}
