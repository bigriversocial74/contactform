<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_proposal_projection(array $row, bool $duplicate = false): array
{
    $projection = mg_mcp_draft_projection($row, $duplicate);
    $payload = is_array($projection['payload'] ?? null) ? $projection['payload'] : [];
    $projection['proposal'] = [
        'domain' => 'creator_campaign',
        'kind' => (string)($payload['proposal_kind'] ?? ''),
        'operation' => (string)($payload['proposed_action'] ?? ''),
        'campaign_id' => $payload['campaign_id'] ?? null,
        'required_approval' => 'merchant_owner_review',
        'native_conversion_enabled' => false,
    ];
    $projection['execution'] = [
        'enabled' => false,
        'status' => 'creator_campaign_proposal_only',
        'next_step' => (string)$row['status'] === 'approved' ? 'await_phase_13c_canonical_action' : 'owner_review',
        'conversion_enabled' => false,
    ];
    return $projection;
}

function mg_mcp_creator_campaign_proposal_create(PDO $pdo, array $context, array $input): array
{
    if (strtolower(trim((string)($input['type'] ?? ''))) !== 'campaign') {
        throw new MgMcpDraftException('Creator Campaign proposals must use the campaign draft ledger.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_TYPE_INVALID');
    }
    $payloadInput = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    if (empty($payloadInput['creator_campaign_proposal'])) {
        throw new MgMcpDraftException('Creator Campaign proposal marker is required.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_MARKER_REQUIRED');
    }

    $kind = strtolower(trim((string)($payloadInput['proposal_kind'] ?? '')));
    $scope = mg_mcp_creator_campaign_proposal_require_context($context, $kind);
    $campaignRequired = !in_array($kind, ['draft.create', 'submission_feedback.draft'], true);
    $campaign = mg_mcp_creator_campaign_proposal_campaign($pdo, $context, $payloadInput['campaign_id'] ?? '', $campaignRequired);

    $values = is_array($payloadInput['proposed_values'] ?? null) ? $payloadInput['proposed_values'] : [];
    if ($kind === 'submission_feedback.draft') {
        $submission = mg_mcp_creator_campaign_proposal_submission($pdo, $context, $values['submission_id'] ?? '');
        $campaign = mg_creator_campaign_repository_campaign($pdo, (int)$submission['campaign_id'], (int)$context['workspace_id']);
        $payloadInput['campaign_id'] = (string)$submission['campaign_public_id'];
    }
    $cleanValues = mg_mcp_creator_campaign_proposal_values($pdo, $context, $kind, $values, $campaign);

    $title = mg_mcp_draft_text($input['title'] ?? '', 190, 'proposal title');
    $summary = mg_mcp_draft_text($input['summary'] ?? '', 500, 'proposal summary');
    $idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
    if (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 190 || preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) !== 1) {
        throw new MgMcpDraftException('A valid idempotency key is required.', 422, 'MCP_DRAFT_IDEMPOTENCY_INVALID');
    }
    $sourceRequestId = mg_mcp_draft_uuid($input['source_request_id'] ?? '', 'source request');
    $risk = strtolower(trim((string)($input['risk_level'] ?? 'medium')));
    if (!in_array($risk, ['low','medium','high','critical'], true)) throw new MgMcpDraftException('Invalid risk level.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_VALIDATION_FAILED');
    $floor = MG_MCP_CREATOR_CAMPAIGN_PROPOSAL_RISK_FLOOR[$kind] ?? 'medium';
    if (mg_mcp_creator_campaign_proposal_rank($risk) < mg_mcp_creator_campaign_proposal_rank($floor)) {
        throw new MgMcpDraftException('Risk level must be at least ' . $floor . ' for this proposal.', 422, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_RISK_TOO_LOW');
    }
    $reason = mg_mcp_draft_multiline($input['requested_reason'] ?? 'External agent prepared a Creator Campaign proposal for merchant review.', 1000, 'requested reason', true);

    $payload = [
        'creator_campaign_proposal' => true,
        'proposal_version' => 1,
        'proposal_kind' => $kind,
        'proposed_action' => 'creator_campaigns.' . $kind,
        'campaign_id' => $campaign !== null ? (string)$campaign['public_id'] : null,
        'proposed_values' => $cleanValues,
        'authority' => [
            'connection_id' => (string)$context['connection_public_id'],
            'client_id' => (string)$context['client_public_id'],
            'requesting_user_id' => (string)$context['user_id'],
            'merchant_workspace_id' => (string)($context['workspace']['id'] ?? ''),
            'grant_id' => null,
            'automation_definition_id' => null,
            'required_scope' => $scope,
        ],
        'approval' => [
            'required' => true,
            'type' => 'merchant_owner_review',
            'native_conversion_enabled' => false,
        ],
        'boundaries' => [
            'publish' => false,
            'approve' => false,
            'send' => false,
            'pay' => false,
            'schedule' => false,
            'external_effects' => false,
        ],
    ];
    $fingerprint = mg_mcp_draft_fingerprint($payload);
    $connectionId = (int)$context['connection_db_id'];
    $clientId = (int)$context['client_db_id'];
    $ownerUserId = (int)$context['user_id'];

    $pdo->beginTransaction();
    try {
        mg_mcp_draft_expire($pdo, $ownerUserId);
        $existingStmt = $pdo->prepare('SELECT id,draft_type,payload_fingerprint FROM mcp_agent_drafts WHERE connection_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
        $existingStmt->execute([$connectionId, $idempotencyKey]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ((string)$existing['draft_type'] !== 'campaign' || !hash_equals((string)$existing['payload_fingerprint'], $fingerprint)) {
                throw new MgMcpDraftException('The idempotency key was already used for different proposal content.', 409, 'MCP_DRAFT_IDEMPOTENCY_CONFLICT');
            }
            mg_mcp_draft_event($pdo, (int)$existing['id'], 'duplicate_returned', $ownerUserId, $connectionId, [
                'proposal_kind' => $kind,
                'idempotency_key_hash' => hash('sha256', $idempotencyKey),
                'native_conversion_enabled' => false,
            ]);
            $row = mg_mcp_draft_row_by_id($pdo, (int)$existing['id']);
            $pdo->commit();
            return mg_mcp_creator_campaign_proposal_projection($row, true);
        }

        $publicId = mg_public_uuid();
        $stmt = $pdo->prepare("INSERT INTO mcp_agent_drafts
             (public_id,connection_id,client_id,owner_user_id,workspace_type,workspace_id,draft_type,status,title,summary,
              payload_json,payload_fingerprint,risk_level,idempotency_key,source_request_id,requested_reason,approval_expires_at,
              created_at,updated_at)
             VALUES (?,?,?,?,?,?,'campaign','pending_review',?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY),NOW(),NOW())");
        $stmt->execute([
            $publicId,$connectionId,$clientId,$ownerUserId,(string)$context['workspace_type'],(int)$context['workspace_id'],
            $title,$summary,json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $fingerprint,$risk,$idempotencyKey,$sourceRequestId,$reason,
        ]);
        $draftId = (int)$pdo->lastInsertId();
        mg_mcp_draft_event($pdo, $draftId, 'created', $ownerUserId, $connectionId, [
            'proposal_kind' => $kind,
            'proposed_action' => 'creator_campaigns.' . $kind,
            'campaign_id' => $payload['campaign_id'],
            'required_scope' => $scope,
            'risk_level' => $risk,
            'required_approval' => 'merchant_owner_review',
            'payload_fingerprint' => $fingerprint,
            'native_conversion_enabled' => false,
            'external_effects_enabled' => false,
        ]);
        $row = mg_mcp_draft_row_by_id($pdo, $draftId);
        $pdo->commit();

        $metadata = [
            'draft_id' => $publicId,
            'proposal_kind' => $kind,
            'campaign_id' => $payload['campaign_id'],
            'connection_id' => (string)$context['connection_public_id'],
            'required_scope' => $scope,
            'native_conversion_enabled' => false,
        ];
        mg_audit('mcp_creator_campaign_proposal_created', 'mcp_agent_draft', $metadata, $ownerUserId);
        mg_event('mcp.creator_campaign.proposal.created', $metadata, $ownerUserId);
        mg_security_log('info', 'mcp.creator_campaign.proposal.created', 'External agent created a review-only Creator Campaign proposal.', $metadata, $ownerUserId);
        return mg_mcp_creator_campaign_proposal_projection($row);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && (string)$error->getCode() === '23000') {
            throw new MgMcpDraftException('The proposal conflicts with an existing record.', 409, 'MCP_CREATOR_CAMPAIGN_PROPOSAL_CONFLICT');
        }
        throw $error;
    }
}
