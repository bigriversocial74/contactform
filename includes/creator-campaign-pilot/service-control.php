<?php
declare(strict_types=1);

function mg_creator_campaign_pilot_emergency_disable(
    PDO $pdo,
    array $user,
    array $workspace,
    string $reason
): array {
    $reason = mg_creator_campaign_pilot_text($reason, 1000, 'emergency-stop reason', true, 8);
    $pilot = mg_creator_campaign_pilot_ensure($pdo, $user, $workspace);
    $ownerId = (int)$user['id'];
    $workspaceId = (int)$workspace['id'];

    $pdo->beginTransaction();
    try {
        $locked = mg_creator_campaign_pilot_row($pdo, $ownerId, $workspaceId, true);
        if (!$locked) throw new MgCreatorCampaignPilotException('Pilot record is unavailable.', 404);
        $pdo->prepare(
            "UPDATE creator_campaign_operator_pilots
             SET status='disabled',emergency_disabled=1,emergency_reason=?,
                 emergency_disabled_at=NOW(),updated_at=NOW()
             WHERE id=?"
        )->execute([$reason,(int)$locked['id']]);
        $pdo->prepare(
            "UPDATE mcp_automation_runs r
             INNER JOIN mcp_automations a ON a.id=r.automation_id
             SET r.cancellation_requested_at=COALESCE(r.cancellation_requested_at,NOW()),r.updated_at=NOW()
             WHERE a.owner_user_id=? AND a.workspace_id=?
               AND JSON_UNQUOTE(JSON_EXTRACT(a.configuration_json,'$.mode'))='manual_bounded_playbook'
               AND r.status IN ('queued','evaluating','waiting_for_approval','approved','executing')"
        )->execute([$ownerId,$workspaceId]);
        $pdo->prepare(
            "UPDATE mcp_automation_triggers t
             INNER JOIN mcp_automations a ON a.id=t.automation_id
             SET t.status='paused',t.updated_at=NOW()
             WHERE a.owner_user_id=? AND a.workspace_id=?
               AND JSON_UNQUOTE(JSON_EXTRACT(a.configuration_json,'$.mode'))='manual_bounded_playbook'
               AND t.status='active'"
        )->execute([$ownerId,$workspaceId]);
        $triggerCount = $pdo->query('SELECT ROW_COUNT()')->fetchColumn();
        $pdo->prepare(
            "UPDATE mcp_automations
             SET status='paused',updated_at=NOW()
             WHERE owner_user_id=? AND workspace_id=?
               AND JSON_UNQUOTE(JSON_EXTRACT(configuration_json,'$.mode'))='manual_bounded_playbook'
               AND status='active'"
        )->execute([$ownerId,$workspaceId]);
        $definitionCount = $pdo->query('SELECT ROW_COUNT()')->fetchColumn();
        $pdo->prepare(
            "UPDATE mcp_automation_grants g
             INNER JOIN (
               SELECT DISTINCT grant_id FROM mcp_automations
               WHERE owner_user_id=? AND workspace_id=?
                 AND JSON_UNQUOTE(JSON_EXTRACT(configuration_json,'$.mode'))='manual_bounded_playbook'
             ) bounded ON bounded.grant_id=g.id
             SET g.status='paused',g.revocation_version=g.revocation_version+1,g.updated_at=NOW()
             WHERE g.status='active'"
        )->execute([$ownerId,$workspaceId]);
        $grantCount = $pdo->query('SELECT ROW_COUNT()')->fetchColumn();
        mg_creator_campaign_pilot_event(
            $pdo,
            (int)$locked['id'],
            $ownerId,
            'creator_campaign.pilot.emergency_disabled',
            'critical',
            'merchant_workspace',
            (string)$workspace['public_id'],
            $reason,
            [
                'definitions_paused'=>(int)$definitionCount,
                'triggers_paused'=>(int)$triggerCount,
                'grants_paused'=>(int)$grantCount,
                'automatic_reactivation'=>false,
            ]
        );
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $metadata = [
        'workspace_id'=>(string)$workspace['public_id'],
        'emergency_disabled'=>true,
        'automatic_reactivation'=>false,
        'reason'=>$reason,
    ];
    mg_audit('creator_campaign_pilot_emergency_disabled', 'merchant_workspace', $metadata, $ownerId);
    mg_event('creator_campaign.pilot.emergency_disabled', $metadata, $ownerId);
    mg_security_log('critical', 'creator_campaign.pilot.emergency_disabled', 'Owner activated the Creator Campaign pilot emergency stop.', $metadata, $ownerId);
    return mg_creator_campaign_pilot_row($pdo, $ownerId, $workspaceId) ?? $pilot;
}

function mg_creator_campaign_pilot_emergency_clear(
    PDO $pdo,
    array $user,
    array $workspace,
    string $reason
): array {
    $reason = mg_creator_campaign_pilot_text($reason, 1000, 'emergency-clear reason', true, 8);
    $pilot = mg_creator_campaign_pilot_ensure($pdo, $user, $workspace);
    if (empty($pilot['emergency_disabled'])) return $pilot;
    $pdo->prepare(
        "UPDATE creator_campaign_operator_pilots
         SET status='paused',emergency_disabled=0,emergency_reason=NULL,
             emergency_cleared_at=NOW(),updated_at=NOW()
         WHERE id=? AND owner_user_id=?"
    )->execute([(int)$pilot['id'],(int)$user['id']]);
    mg_creator_campaign_pilot_event(
        $pdo,
        (int)$pilot['id'],
        (int)$user['id'],
        'creator_campaign.pilot.emergency_cleared',
        'high',
        'merchant_workspace',
        (string)$workspace['public_id'],
        $reason,
        ['automatic_reactivation'=>false]
    );
    $metadata = [
        'workspace_id'=>(string)$workspace['public_id'],
        'emergency_disabled'=>false,
        'automatic_reactivation'=>false,
        'reason'=>$reason,
    ];
    mg_audit('creator_campaign_pilot_emergency_cleared', 'merchant_workspace', $metadata, (int)$user['id']);
    mg_security_log('warning', 'creator_campaign.pilot.emergency_cleared', 'Owner cleared the Creator Campaign pilot emergency stop. Grants and definitions remain paused.', $metadata, (int)$user['id']);
    return mg_creator_campaign_pilot_row($pdo, (int)$user['id'], (int)$workspace['id']) ?? $pilot;
}

function mg_creator_campaign_pilot_pause_definition(
    PDO $pdo,
    array $user,
    array $workspace,
    string $automationPublicId,
    string $reason
): void {
    $automationPublicId = mg_creator_campaign_pilot_public_id($automationPublicId, 'automation');
    $reason = mg_creator_campaign_pilot_text($reason, 1000, 'pause reason', true, 5);
    $stmt = $pdo->prepare(
        "SELECT a.id,a.public_id,a.name,a.status,t.id trigger_id,t.status trigger_status
         FROM mcp_automations a
         LEFT JOIN mcp_automation_triggers t ON t.automation_id=a.id AND t.trigger_type='manual'
         WHERE a.public_id=? AND a.owner_user_id=? AND a.workspace_id=?
           AND JSON_UNQUOTE(JSON_EXTRACT(a.configuration_json,'$.mode'))='manual_bounded_playbook'
         LIMIT 1"
    );
    $stmt->execute([$automationPublicId,(int)$user['id'],(int)$workspace['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new MgCreatorCampaignPilotException('Playbook definition was not found.', 404);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE mcp_automations SET status='paused',updated_at=NOW() WHERE id=? AND status='active'")
            ->execute([(int)$row['id']]);
        if ((int)($row['trigger_id'] ?? 0) > 0) {
            $pdo->prepare("UPDATE mcp_automation_triggers SET status='paused',updated_at=NOW() WHERE id=? AND status='active'")
                ->execute([(int)$row['trigger_id']]);
        }
        $pdo->prepare(
            "UPDATE mcp_automation_runs SET cancellation_requested_at=COALESCE(cancellation_requested_at,NOW()),updated_at=NOW()
             WHERE automation_id=? AND status IN ('queued','evaluating','waiting_for_approval','approved','executing')"
        )->execute([(int)$row['id']]);
        $pilot = mg_creator_campaign_pilot_ensure($pdo, $user, $workspace);
        mg_creator_campaign_pilot_event(
            $pdo,
            (int)$pilot['id'],
            (int)$user['id'],
            'creator_campaign.pilot.definition_paused',
            'high',
            'mcp_automation',
            $automationPublicId,
            $reason,
            ['automation_name'=>(string)$row['name']]
        );
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_creator_campaign_pilot_acknowledge_run(
    PDO $pdo,
    array $user,
    array $workspace,
    string $runPublicId,
    string $resolution,
    string $note
): void {
    $runPublicId = mg_creator_campaign_pilot_public_id($runPublicId, 'run');
    $resolution = strtolower(trim($resolution));
    if (!in_array($resolution, ['retry_external','review_configuration','pause_definition','no_retry','resolved'], true)) {
        throw new MgCreatorCampaignPilotException('Select a supported run-recovery resolution.');
    }
    $note = mg_creator_campaign_pilot_text($note, 2000, 'recovery note', true, 5);
    $run = mg_creator_campaign_pilot_run_row($pdo, (int)$user['id'], (int)$workspace['id'], $runPublicId);
    if ($resolution === 'pause_definition') {
        mg_creator_campaign_pilot_pause_definition(
            $pdo,
            $user,
            $workspace,
            (string)$run['automation_public_id'],
            'Run recovery: ' . $note
        );
    }
    $pilot = mg_creator_campaign_pilot_ensure($pdo, $user, $workspace);
    mg_creator_campaign_pilot_event(
        $pdo,
        (int)$pilot['id'],
        (int)$user['id'],
        'creator_campaign.pilot.run_recovery_recorded',
        in_array((string)$run['status'], ['failed','dead_lettered'], true) ? 'high' : 'medium',
        'mcp_automation_run',
        $runPublicId,
        $note,
        [
            'resolution'=>$resolution,
            'run_status'=>(string)$run['status'],
            'automation_id'=>(string)$run['automation_public_id'],
            'automatic_retry_started'=>false,
        ]
    );
}
