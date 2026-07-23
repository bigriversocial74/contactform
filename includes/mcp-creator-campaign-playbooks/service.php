<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_playbook_build(
    PDO $pdo,
    array $context,
    string $playbookKey,
    array $input
): array {
    return match ($playbookKey) {
        'creator_campaign_campaign_preparation' => mg_mcp_creator_campaign_playbook_campaign_preparation($pdo, $context, $input),
        'creator_campaign_application_review' => mg_mcp_creator_campaign_playbook_application_review($pdo, $context, $input),
        'creator_campaign_content_review' => mg_mcp_creator_campaign_playbook_content_review($pdo, $context, $input),
        'creator_campaign_health' => mg_mcp_creator_campaign_playbook_campaign_health($pdo, $context, $input),
        'creator_campaign_earnings_review' => mg_mcp_creator_campaign_playbook_earnings_review($pdo, $context, $input),
        'creator_campaign_creator_outreach' => mg_mcp_creator_campaign_playbook_creator_outreach($pdo, $context, $input),
        default => throw new MgMcpCreatorCampaignPlaybookException(
            'The configured Creator Campaign playbook is unavailable.',
            404,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_UNAVAILABLE'
        ),
    };
}

function mg_mcp_creator_campaign_playbook_artifact(PDO $pdo, int $draftId, bool $duplicate = false): array
{
    $row = mg_mcp_draft_row_by_id($pdo, $draftId);
    $projection = mg_mcp_draft_projection($row, $duplicate);
    $projection['conversion'] = [
        'enabled' => false,
        'status' => 'creator_campaign_playbook_review_only',
    ];
    return $projection;
}

function mg_mcp_creator_campaign_playbook_duplicate(
    PDO $pdo,
    array $context,
    array $run,
    string $inputFingerprint
): array {
    if (!hash_equals((string)$run['input_fingerprint'], $inputFingerprint)) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'The idempotency key was already used for different playbook input.',
            409,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_IDEMPOTENCY_CONFLICT'
        );
    }
    $summary = mg_mcp_automation_json_object($run['output_summary_json'] ?? null);
    $artifactPublicId = (string)($summary['artifact_id'] ?? '');
    $artifact = null;
    if ($artifactPublicId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM mcp_agent_drafts WHERE public_id=? AND connection_id=? LIMIT 1');
        $stmt->execute([$artifactPublicId, (int)$context['connection_db_id']]);
        $draftId = (int)($stmt->fetchColumn() ?: 0);
        if ($draftId > 0) {
            $artifact = mg_mcp_creator_campaign_playbook_artifact($pdo, $draftId, true);
        }
    }
    return [
        'id' => (string)$run['public_id'],
        'status' => (string)$run['status'],
        'duplicate' => true,
        'playbook_key' => (string)($summary['playbook_key'] ?? ''),
        'artifact' => $artifact,
        'output' => $summary['output'] ?? null,
        'execution' => [
            'performed' => false,
            'canonical_mutation' => false,
            'external_effect' => false,
        ],
    ];
}

function mg_mcp_creator_campaign_playbook_run(
    PDO $pdo,
    array $context,
    string $toolName,
    array $input
): array {
    $contract = mg_mcp_creator_campaign_playbook_contract($toolName);
    mg_mcp_creator_campaign_playbook_require_scope($context, (string)$contract['scope']);
    $automationPublicId = mg_mcp_draft_uuid($input['automation_id'] ?? '', 'automation');
    $sourceRequestId = mg_mcp_draft_uuid($input['source_request_id'] ?? '', 'source request');
    $idempotencyKey = mg_mcp_creator_campaign_playbook_idempotency($input['idempotency_key'] ?? '');
    $requestedReason = mg_mcp_creator_campaign_playbook_text(
        $input['requested_reason'] ?? 'External agent ran an owner-configured Creator Campaign playbook.',
        1,
        1000,
        'Requested reason'
    );
    $campaignPublicId = mg_mcp_creator_campaign_playbook_public_id(
        $input['campaign_id'] ?? '',
        'campaign_id',
        (string)$contract['playbook_key'] !== 'creator_campaign_campaign_preparation'
    );
    $sanitizedInput = $input;
    unset($sanitizedInput['source_request_id']);
    $inputFingerprint = hash('sha256', json_encode(
        $sanitizedInput,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ));
    $runKey = 'v13d:' . hash('sha256', $automationPublicId . '|' . $idempotencyKey);
    $draftKey = 'v13d-draft:' . hash('sha256', (string)$context['connection_public_id'] . '|' . $automationPublicId . '|' . $idempotencyKey);

    $pdo->beginTransaction();
    try {
        $automation = mg_mcp_automation_lock_owner_definition(
            $pdo,
            (int)$context['user_id'],
            $automationPublicId
        );
        if ((string)$automation['connection_public_id'] !== (string)$context['connection_public_id']) {
            throw new MgMcpCreatorCampaignPlaybookException(
                'The automation definition belongs to a different MCP connection.',
                403,
                'MCP_CREATOR_CAMPAIGN_PLAYBOOK_CONNECTION_MISMATCH'
            );
        }
        if ((string)$automation['status'] !== 'active' || (string)$automation['grant_status'] !== 'active') {
            throw new MgMcpCreatorCampaignPlaybookException(
                'The automation definition and parent grant must both be active.',
                409,
                'MCP_CREATOR_CAMPAIGN_PLAYBOOK_AUTOMATION_INACTIVE'
            );
        }
        if ((string)$automation['playbook_key'] !== (string)$contract['playbook_key']) {
            throw new MgMcpCreatorCampaignPlaybookException(
                'The selected automation definition does not match this fixed playbook.',
                409,
                'MCP_CREATOR_CAMPAIGN_PLAYBOOK_DEFINITION_MISMATCH'
            );
        }
        if (!in_array((string)($automation['grant_workspace_type'] ?? ''), ['merchant', 'merchant_workspace'], true)
            || (int)($automation['grant_workspace_id'] ?? 0) !== (int)($context['workspace_id'] ?? 0)) {
            throw new MgMcpCreatorCampaignPlaybookException(
                'A matching merchant-workspace automation definition is required.',
                403,
                'MCP_CREATOR_CAMPAIGN_PLAYBOOK_WORKSPACE_MISMATCH'
            );
        }
        $configuration = mg_mcp_automation_json_object($automation['configuration_json'] ?? null);
        if (($configuration['review_artifact_only'] ?? null) !== true
            || ($configuration['execution_requested'] ?? true) !== false) {
            throw new MgMcpCreatorCampaignPlaybookException(
                'The automation definition is outside the Phase 13D review-artifact boundary.',
                409,
                'MCP_CREATOR_CAMPAIGN_PLAYBOOK_DEFINITION_BOUNDARY'
            );
        }

        $triggerStmt = $pdo->prepare(
            "SELECT id,public_id,status FROM mcp_automation_triggers
             WHERE automation_id=? AND trigger_type='manual' LIMIT 1 FOR UPDATE"
        );
        $triggerStmt->execute([(int)$automation['id']]);
        $trigger = $triggerStmt->fetch(PDO::FETCH_ASSOC);
        if (!$trigger || (string)$trigger['status'] !== 'active') {
            throw new MgMcpCreatorCampaignPlaybookException(
                'The owner-controlled manual playbook trigger is not active.',
                409,
                'MCP_CREATOR_CAMPAIGN_PLAYBOOK_TRIGGER_INACTIVE'
            );
        }

        $existingStmt = $pdo->prepare(
            'SELECT public_id,status,input_fingerprint,output_summary_json FROM mcp_automation_runs WHERE automation_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE'
        );
        $existingStmt->execute([(int)$automation['id'], $runKey]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $duplicate = mg_mcp_creator_campaign_playbook_duplicate($pdo, $context, $existing, $inputFingerprint);
            $pdo->commit();
            return $duplicate;
        }

        $grant = mg_mcp_automation_definition_grant_projection($automation);
        mg_mcp_automation_assert_grant_activatable($pdo, $grant);
        mg_mcp_automation_definition_playbook($grant, (string)$contract['playbook_key']);
        mg_mcp_automation_authorize_grant_action(
            $pdo,
            (string)$automation['connection_public_id'],
            (string)$automation['grant_public_id'],
            $toolName,
            'draft',
            (string)$contract['risk_level'],
            0,
            1,
            $campaignPublicId !== '' ? ['campaign_id' => $campaignPublicId] : []
        );

        $output = mg_mcp_creator_campaign_playbook_build(
            $pdo,
            $context,
            (string)$contract['playbook_key'],
            $input
        );
        $artifactTitle = mg_mcp_creator_campaign_playbook_text(
            $input['artifact_title'] ?? ((string)$contract['label'] . ' review'),
            1,
            190,
            'Artifact title'
        );
        $artifactSummary = mg_mcp_creator_campaign_playbook_text(
            $input['artifact_summary'] ?? ('Review the bounded output from ' . (string)$contract['label'] . '.'),
            1,
            500,
            'Artifact summary'
        );
        $payload = [
            'creator_campaign_proposal' => true,
            'creator_campaign_playbook_output' => true,
            'proposal_version' => 1,
            'proposal_kind' => 'playbook.' . (string)$contract['playbook_key'],
            'proposed_action' => 'creator_campaign_playbook.review',
            'campaign_id' => $campaignPublicId !== '' ? $campaignPublicId : null,
            'playbook' => [
                'key' => (string)$contract['playbook_key'],
                'tool' => $toolName,
                'automation_id' => $automationPublicId,
                'grant_id' => (string)$automation['grant_public_id'],
            ],
            'output' => $output,
            'authority' => [
                'connection_id' => (string)$context['connection_public_id'],
                'client_id' => (string)$context['client_public_id'],
                'requesting_user_id' => (string)$context['user_id'],
                'merchant_workspace_id' => (string)($context['workspace']['id'] ?? ''),
                'required_scope' => (string)$contract['scope'],
            ],
            'approval' => [
                'required' => true,
                'type' => 'merchant_owner_review',
                'native_conversion_enabled' => false,
            ],
            'boundaries' => [
                'manual_run_only' => true,
                'scheduler_enabled' => false,
                'canonical_action_request_created' => false,
                'canonical_mutation_enabled' => false,
                'external_effects' => false,
                'payment_provider_enabled' => false,
            ],
        ];
        $payloadFingerprint = mg_mcp_draft_fingerprint($payload);
        $draftPublicId = mg_public_uuid();
        $pdo->prepare(
            "INSERT INTO mcp_agent_drafts
             (public_id,connection_id,client_id,owner_user_id,workspace_type,workspace_id,draft_type,status,title,summary,
              payload_json,payload_fingerprint,risk_level,idempotency_key,source_request_id,requested_reason,approval_expires_at,
              created_at,updated_at)
             VALUES (?,?,?,?,?,?,'campaign','pending_review',?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY),NOW(),NOW())"
        )->execute([
            $draftPublicId,
            (int)$context['connection_db_id'],
            (int)$context['client_db_id'],
            (int)$context['user_id'],
            (string)$context['workspace_type'],
            (int)$context['workspace_id'],
            $artifactTitle,
            $artifactSummary,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $payloadFingerprint,
            (string)$contract['risk_level'],
            $draftKey,
            $sourceRequestId,
            $requestedReason,
        ]);
        $draftId = (int)$pdo->lastInsertId();
        mg_mcp_draft_event($pdo, $draftId, 'created', (int)$context['user_id'], (int)$context['connection_db_id'], [
            'phase' => '13d',
            'playbook_key' => (string)$contract['playbook_key'],
            'automation_id' => $automationPublicId,
            'grant_id' => (string)$automation['grant_public_id'],
            'required_scope' => (string)$contract['scope'],
            'payload_fingerprint' => $payloadFingerprint,
            'native_conversion_enabled' => false,
            'canonical_mutation_enabled' => false,
        ]);

        $runPublicId = mg_public_uuid();
        $now = gmdate('Y-m-d H:i:s');
        $summary = [
            'phase' => '13d',
            'mode' => 'manual_bounded_playbook',
            'playbook_key' => (string)$contract['playbook_key'],
            'tool_name' => $toolName,
            'artifact_id' => $draftPublicId,
            'output' => $output,
            'owner_review_required' => true,
            'scheduler_enabled' => false,
            'canonical_action_request_created' => false,
            'canonical_mutation_enabled' => false,
            'external_effect' => false,
        ];
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
            $now,
            $now,
            $now,
            $now,
            json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $runId = (int)$pdo->lastInsertId();
        $actionPublicId = mg_public_uuid();
        $stateToken = hash('sha256', json_encode([
            'automation_version' => (int)$automation['current_version'],
            'grant_revocation_version' => (int)$automation['revocation_version'],
            'campaign_id' => $campaignPublicId,
            'input_fingerprint' => $inputFingerprint,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $pdo->prepare(
            "INSERT INTO mcp_automation_actions
             (public_id,run_id,sequence_number,tool_name,tool_version,operation_class,risk_level,status,approval_required,
              idempotency_key,input_fingerprint,sanitized_input_json,fresh_state_token,proposed_amount_cents,proposed_quantity,
              started_at,completed_at,created_at,updated_at)
             VALUES (?,?,1,?,'1.0','draft',?,'succeeded',1,?,?,?,?,NULL,1,NOW(),NOW(),NOW(),NOW())"
        )->execute([
            $actionPublicId,
            $runId,
            $toolName,
            (string)$contract['risk_level'],
            $runKey . ':action:1',
            $inputFingerprint,
            json_encode($sanitizedInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $stateToken,
        ]);
        $actionId = (int)$pdo->lastInsertId();
        $receiptPublicId = mg_public_uuid();
        $afterStateToken = hash('sha256', $payloadFingerprint . '|' . $draftPublicId);
        $receiptMetadata = [
            'phase' => '13d',
            'playbook_key' => (string)$contract['playbook_key'],
            'automation_id' => $automationPublicId,
            'artifact_id' => $draftPublicId,
            'owner_review_required' => true,
            'canonical_mutation_enabled' => false,
            'external_effect' => false,
        ];
        $pdo->prepare(
            "INSERT INTO mcp_action_receipts
             (public_id,action_id,run_id,grant_id,status,canonical_service,canonical_action,idempotency_key,
              before_state_token,after_state_token,result_reference_type,result_reference_public_id,amount_cents,quantity,
              metadata_json,attempted_at,completed_at,created_at)
             VALUES (?,?,?,?,'succeeded','creator_campaign_playbook_service',?,?,?,?, 'mcp_agent_draft',?,0,1,?,NOW(),NOW(),NOW())"
        )->execute([
            $receiptPublicId,
            $actionId,
            $runId,
            (int)$automation['grant_id'],
            (string)$contract['playbook_key'],
            $runKey,
            $stateToken,
            $afterStateToken,
            $draftPublicId,
            json_encode($receiptMetadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $pdo->prepare('UPDATE mcp_automations SET last_run_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([(int)$automation['id']]);
        $pdo->prepare('UPDATE mcp_automation_grants SET last_used_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([(int)$automation['grant_id']]);
        $connection = [
            'id' => (int)$automation['connection_id'],
            'client_id' => (int)$automation['client_id'],
            'workspace_type' => $automation['grant_workspace_type'],
            'workspace_id' => $automation['grant_workspace_id'],
        ];
        mg_mcp_automation_insert_security_event(
            $pdo,
            $connection,
            (int)$context['user_id'],
            'mcp.creator_campaign.playbook.completed',
            'External agent completed an owner-configured bounded Creator Campaign playbook.',
            $receiptMetadata + [
                'run_id' => $runPublicId,
                'tool_name' => $toolName,
                'scheduler_enabled' => false,
                'canonical_action_request_created' => false,
            ],
            (string)$contract['risk_level'] === 'critical' ? 'warning' : 'info'
        );
        $artifact = mg_mcp_creator_campaign_playbook_artifact($pdo, $draftId);
        $pdo->commit();

        $metadata = $receiptMetadata + [
            'run_id' => $runPublicId,
            'tool_name' => $toolName,
            'receipt_id' => $receiptPublicId,
        ];
        mg_audit('mcp_creator_campaign_playbook_completed', 'mcp_automation_run', $metadata, (int)$context['user_id']);
        mg_event('mcp.creator_campaign.playbook.completed', $metadata, (int)$context['user_id']);
        mg_security_log(
            (string)$contract['risk_level'] === 'critical' ? 'warning' : 'info',
            'mcp.creator_campaign.playbook.completed',
            'External agent completed an owner-configured bounded Creator Campaign playbook.',
            $metadata,
            (int)$context['user_id']
        );
        return [
            'id' => $runPublicId,
            'status' => 'succeeded',
            'duplicate' => false,
            'playbook_key' => (string)$contract['playbook_key'],
            'artifact' => $artifact,
            'output' => $output,
            'receipt_id' => $receiptPublicId,
            'execution' => [
                'performed' => false,
                'canonical_action_request_created' => false,
                'canonical_mutation' => false,
                'external_effect' => false,
            ],
        ];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
