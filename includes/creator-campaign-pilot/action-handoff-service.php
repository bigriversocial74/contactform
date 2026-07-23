<?php
declare(strict_types=1);

function mg_creator_campaign_pilot_prepare_action_request(
    PDO $pdo,
    array $user,
    array $workspace,
    array $pilot,
    string $draftPublicId,
    string $grantPublicId,
    string $toolName,
    string $inputJson,
    string $requestedReason
): array {
    if ((string)$pilot['status'] !== 'active') {
        throw new MgCreatorCampaignPilotException(
            'Start or resume the production pilot before preparing an action request.',
            409,
            'CREATOR_CAMPAIGN_PILOT_NOT_ACTIVE'
        );
    }
    if (!empty($pilot['emergency_disabled'])) {
        throw new MgCreatorCampaignPilotException(
            'The emergency stop blocks new recommendation handoffs.',
            423,
            'CREATOR_CAMPAIGN_PILOT_EMERGENCY_DISABLED'
        );
    }
    $draftPublicId = mg_creator_campaign_pilot_public_id($draftPublicId, 'review artifact');
    $grantPublicId = mg_mcp_creator_campaign_action_uuid($grantPublicId, 'grant_id');
    $requestedReason = mg_creator_campaign_pilot_text(
        $requestedReason,
        1000,
        'action-request reason',
        true,
        8
    );

    $draftRow = mg_creator_campaign_pilot_draft_row(
        $pdo,
        (int)$user['id'],
        (int)$workspace['id'],
        $draftPublicId
    );
    if ((string)$draftRow['status'] !== 'approved') {
        throw new MgCreatorCampaignPilotException(
            'Only an explicitly approved Phase 13D review artifact may create a handoff.',
            409,
            'CREATOR_CAMPAIGN_PILOT_ARTIFACT_NOT_APPROVED'
        );
    }
    $payload = mg_mcp_draft_json($draftRow['payload_json'] ?? null);
    $playbookKey = mg_creator_campaign_pilot_artifact_playbook($payload);
    $playbook = mg_creator_campaign_pilot_playbook_catalog()[$playbookKey] ?? null;
    if (!is_array($playbook) || !in_array($toolName, (array)$playbook['actions'], true)) {
        throw new MgCreatorCampaignPilotException(
            'The selected canonical action is not a supported follow-up for this playbook.',
            422,
            'CREATOR_CAMPAIGN_PILOT_ACTION_NOT_RECOMMENDED'
        );
    }

    $decodedInput = [];
    if (trim($inputJson) !== '') {
        try {
            $decodedInput = json_decode($inputJson, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MgCreatorCampaignPilotException('Action input must be valid JSON.');
        }
        if (!is_array($decodedInput)) {
            throw new MgCreatorCampaignPilotException('Action input must decode to an object.');
        }
    }
    if (strlen(json_encode($decodedInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) > 60000) {
        throw new MgCreatorCampaignPilotException('Action input is too large.', 413);
    }
    $seed = mg_creator_campaign_pilot_action_seed($payload, $toolName);
    $actionInput = array_replace($seed, $decodedInput);

    $grant = mg_creator_campaign_pilot_action_grant_row(
        $pdo,
        (int)$user['id'],
        (int)$workspace['id'],
        $grantPublicId
    );
    if (!in_array($toolName, (array)$grant['allowed_tools'], true)) {
        throw new MgCreatorCampaignPilotException(
            'The selected approval-gated grant does not allow this action tool.',
            403,
            'CREATOR_CAMPAIGN_PILOT_ACTION_TOOL_DENIED'
        );
    }
    $contract = mg_mcp_creator_campaign_action_contract($toolName);
    if (!in_array((string)$contract['scope'], (array)$grant['scopes'], true)) {
        throw new MgCreatorCampaignPilotException(
            'The action connection does not contain the required scope.',
            403,
            'CREATOR_CAMPAIGN_PILOT_ACTION_SCOPE_DENIED'
        );
    }

    $fingerprint = hash('sha256', json_encode(
        ['draft'=>$draftPublicId,'tool'=>$toolName,'input'=>$actionInput],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ));
    $idempotencyKey = 'pilot14:' . substr(hash('sha256', $draftPublicId . '|' . $fingerprint), 0, 48);
    $requestInput = $actionInput + [
        'grant_id' => $grantPublicId,
        'requested_reason' => $requestedReason,
        'idempotency_key' => $idempotencyKey,
    ];
    $context = mg_creator_campaign_pilot_action_context($user, $workspace, $grant);

    try {
        $action = mg_mcp_creator_campaign_action_request($pdo, $context, $toolName, $requestInput);
    } catch (Throwable $error) {
        $code = $error instanceof MgMcpCreatorCampaignActionException
            ? $error->errorCode()
            : 'CREATOR_CAMPAIGN_PILOT_ACTION_REQUEST_FAILED';
        $pdo->prepare(
            "INSERT INTO creator_campaign_operator_handoffs
             (public_id,pilot_id,source_draft_id,owner_user_id,status,tool_name,grant_public_id,input_json,
              input_fingerprint,requested_reason,error_code,error_message,created_at,updated_at)
             VALUES (?,?,?,?, 'failed',?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE status='failed',error_code=VALUES(error_code),
               error_message=VALUES(error_message),updated_at=NOW()"
        )->execute([
            mg_public_uuid(),
            (int)$pilot['id'],
            (int)$draftRow['id'],
            (int)$user['id'],
            $toolName,
            $grantPublicId,
            json_encode($actionInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $fingerprint,
            $requestedReason,
            $code,
            mb_substr($error->getMessage(), 0, 500),
        ]);
        throw $error;
    }

    $pdo->beginTransaction();
    try {
        $handoffPublicId = mg_public_uuid();
        $pdo->prepare(
            "INSERT INTO creator_campaign_operator_handoffs
             (public_id,pilot_id,source_draft_id,owner_user_id,status,tool_name,grant_public_id,input_json,
              input_fingerprint,requested_reason,action_public_id,error_code,error_message,created_at,updated_at)
             VALUES (?,?,?,?, 'request_created',?,?,?,?,?,?,NULL,NULL,NOW(),NOW())
             ON DUPLICATE KEY UPDATE status='request_created',action_public_id=VALUES(action_public_id),
               error_code=NULL,error_message=NULL,updated_at=NOW()"
        )->execute([
            $handoffPublicId,
            (int)$pilot['id'],
            (int)$draftRow['id'],
            (int)$user['id'],
            $toolName,
            $grantPublicId,
            json_encode($actionInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $fingerprint,
            $requestedReason,
            (string)$action['id'],
        ]);
        mg_creator_campaign_pilot_event(
            $pdo,
            (int)$pilot['id'],
            (int)$user['id'],
            'creator_campaign.pilot.action_request_created',
            (string)$contract['risk'] === 'critical' ? 'critical' : 'high',
            'mcp_automation_action',
            (string)$action['id'],
            $requestedReason,
            [
                'source_draft_id'=>$draftPublicId,
                'playbook_key'=>$playbookKey,
                'tool_name'=>$toolName,
                'grant_id'=>$grantPublicId,
                'approval_status'=>(string)($action['approval']['status'] ?? 'pending'),
                'executed'=>false,
            ]
        );
        mg_mcp_draft_event(
            $pdo,
            (int)$draftRow['id'],
            'action_request_prepared',
            (int)$user['id'],
            (int)$draftRow['connection_id'],
            [
                'phase'=>'14',
                'action_id'=>(string)$action['id'],
                'tool_name'=>$toolName,
                'grant_id'=>$grantPublicId,
                'owner_approval_required'=>true,
                'execution_performed'=>false,
            ]
        );
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log(
            'error',
            'creator_campaign.pilot.handoff_evidence_failed',
            'Phase 14 created a Phase 13C request but could not record all pilot handoff evidence.',
            ['action_id'=>(string)$action['id'],'draft_id'=>$draftPublicId,'exception_message'=>mb_substr($error->getMessage(),0,500)],
            (int)$user['id']
        );
    }

    $metadata = [
        'pilot_id'=>(string)$pilot['public_id'],
        'source_draft_id'=>$draftPublicId,
        'action_id'=>(string)$action['id'],
        'tool_name'=>$toolName,
        'owner_approval_required'=>true,
        'execution_performed'=>false,
    ];
    mg_audit('creator_campaign_pilot_action_request_created', 'mcp_automation_action', $metadata, (int)$user['id']);
    mg_event('creator_campaign.pilot.action_request_created', $metadata, (int)$user['id']);
    mg_security_log(
        (string)$contract['risk'] === 'critical' ? 'critical' : 'warning',
        'creator_campaign.pilot.action_request_created',
        'Owner prepared a separate Phase 13C action request from an approved Phase 13D review artifact.',
        $metadata,
        (int)$user['id']
    );
    return $action;
}
