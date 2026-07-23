<?php
declare(strict_types=1);

function mg_creator_campaign_pilot_health_issues(
    array $pilot,
    array $connections,
    array $grants,
    array $definitions,
    array $runs,
    array $artifacts
): array {
    $issues = [];
    if (!empty($pilot['emergency_disabled'])) {
        $issues[] = ['severity'=>'critical','code'=>'emergency_disabled','message'=>'Emergency stop is active. New Phase 13D runs are blocked.','href'=>'#pilot-emergency'];
    }
    foreach ($connections as $connection) {
        if ((string)$connection['status'] !== 'active' || (string)$connection['client_status'] !== 'active') {
            $issues[] = ['severity'=>'high','code'=>'connection_inactive','message'=>'AI connection "' . (string)$connection['display_name'] . '" or its client is not active.','href'=>'/account-ai-connections.php'];
        } elseif ($connection['expires_at'] !== null && strtotime((string)$connection['expires_at']) < time() + 86400 * 7) {
            $issues[] = ['severity'=>'medium','code'=>'connection_expiring','message'=>'AI connection "' . (string)$connection['display_name'] . '" expires within seven days.','href'=>'/account-ai-connections.php'];
        }
    }
    foreach ($grants as $grant) {
        if (!mg_creator_campaign_pilot_has_playbook($grant)) continue;
        if ((string)$grant['status'] !== 'active') {
            $issues[] = ['severity'=>'high','code'=>'grant_inactive','message'=>'Creator Campaign playbook grant is ' . (string)$grant['status'] . '.','href'=>'/account-agent-automations.php'];
        } elseif ($grant['expires_at'] !== null && strtotime((string)$grant['expires_at']) < time() + 86400 * 7) {
            $issues[] = ['severity'=>'medium','code'=>'grant_expiring','message'=>'A Creator Campaign playbook grant expires within seven days.','href'=>'/account-agent-automations.php'];
        }
    }
    foreach ($definitions as $definition) {
        if ((string)$definition['status'] !== 'active' || (string)$definition['trigger_status'] !== 'active') {
            $issues[] = ['severity'=>'medium','code'=>'definition_inactive','message'=>'Playbook definition "' . (string)$definition['name'] . '" is not fully active.','href'=>'/account-agent-automation-definitions.php'];
        }
        if ((int)($definition['failed_count'] ?? 0) > 0) {
            $issues[] = ['severity'=>'high','code'=>'definition_failed_runs','message'=>'Playbook definition "' . (string)$definition['name'] . '" has failed runs requiring review.','href'=>'#pilot-runs'];
        }
    }
    foreach ($runs as $run) {
        if (in_array((string)$run['status'], ['failed','dead_lettered','partially_succeeded'], true)) {
            $issues[] = ['severity'=>'high','code'=>'run_failed','message'=>'Run ' . (string)$run['public_id'] . ' ended as ' . (string)$run['status'] . '.','href'=>'#run-' . (string)$run['public_id']];
        }
    }
    foreach ($artifacts as $artifact) {
        if ((string)$artifact['status'] === 'pending_review'
            && isset($artifact['created_at'])
            && strtotime((string)$artifact['created_at']) < time() - 86400 * 3) {
            $issues[] = ['severity'=>'medium','code'=>'review_stale','message'=>'Review artifact "' . (string)$artifact['title'] . '" has waited more than three days.','href'=>'/account-agent-drafts.php#draft-' . (string)$artifact['id']];
        }
    }
    return $issues;
}

function mg_creator_campaign_pilot_refresh_snapshot(
    PDO $pdo,
    array $user,
    array $workspace,
    array $pilot,
    array $connections,
    array $grants,
    array $definitions,
    array $runs,
    array $artifacts,
    array $actionGrants
): array {
    $readiness = mg_creator_campaign_pilot_readiness($pilot, $connections, $grants, $definitions, $runs, $artifacts, $actionGrants);
    $issues = mg_creator_campaign_pilot_health_issues($pilot, $connections, $grants, $definitions, $runs, $artifacts);
    $snapshot = [
        'generated_at' => gmdate(DATE_ATOM),
        'score' => $readiness['score'],
        'start_ready' => $readiness['start_ready'],
        'pilot_validated' => $readiness['pilot_validated'],
        'counts' => $readiness['counts'],
        'issue_counts' => array_count_values(array_map(static fn(array $issue): string => (string)$issue['severity'], $issues)),
    ];
    $status = (string)$pilot['status'];
    if ($status === 'setup' && $readiness['start_ready']) $status = 'ready';
    if ($status === 'ready' && !$readiness['start_ready']) $status = 'setup';
    $pdo->prepare(
        'UPDATE creator_campaign_operator_pilots
         SET status=?,readiness_snapshot_json=?,last_health_check_at=NOW(),updated_at=NOW()
         WHERE id=? AND owner_user_id=?'
    )->execute([
        $status,
        json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        (int)$pilot['id'],
        (int)$user['id'],
    ]);
    $pilot = mg_creator_campaign_pilot_row($pdo, (int)$user['id'], (int)$workspace['id']) ?? $pilot;
    return ['pilot'=>$pilot,'readiness'=>$readiness,'issues'=>$issues,'snapshot'=>$snapshot];
}

function mg_creator_campaign_pilot_save_profile(
    PDO $pdo,
    array $user,
    array $workspace,
    array $input
): array {
    $pilot = mg_creator_campaign_pilot_ensure($pdo, $user, $workspace);
    $supportContact = mg_creator_campaign_pilot_text($input['support_contact'] ?? '', 255, 'support contact');
    $checklist = [];
    foreach (MG_CREATOR_CAMPAIGN_PILOT_MANUAL_CHECKS as $key => $_label) {
        $checklist[$key] = !empty($input[$key]);
    }
    if ($supportContact === '') $checklist['support_ready'] = false;
    $pdo->prepare(
        'UPDATE creator_campaign_operator_pilots
         SET support_contact=?,checklist_json=?,updated_at=NOW()
         WHERE id=? AND owner_user_id=?'
    )->execute([
        $supportContact !== '' ? $supportContact : null,
        json_encode($checklist, JSON_THROW_ON_ERROR),
        (int)$pilot['id'],
        (int)$user['id'],
    ]);
    mg_creator_campaign_pilot_event(
        $pdo,
        (int)$pilot['id'],
        (int)$user['id'],
        'creator_campaign.pilot.checklist_updated',
        'info',
        'merchant_workspace',
        (string)$workspace['public_id'],
        'Owner updated the pilot checklist and support coverage.',
        ['checklist'=>$checklist,'support_contact_recorded'=>$supportContact !== '']
    );
    return mg_creator_campaign_pilot_row($pdo, (int)$user['id'], (int)$workspace['id']) ?? $pilot;
}

function mg_creator_campaign_pilot_transition(
    PDO $pdo,
    array $user,
    array $workspace,
    string $transition,
    array $readiness
): array {
    $pilot = mg_creator_campaign_pilot_ensure($pdo, $user, $workspace);
    $transition = strtolower(trim($transition));
    $current = (string)$pilot['status'];
    $target = match ($transition) {
        'start' => 'active',
        'pause' => 'paused',
        'resume' => 'active',
        'complete' => 'completed',
        default => throw new MgCreatorCampaignPilotException('Unsupported pilot transition.'),
    };
    $allowed = [
        'setup' => ['active'],
        'ready' => ['active'],
        'active' => ['paused','completed'],
        'paused' => ['active','completed'],
        'completed' => [],
        'disabled' => [],
    ];
    if (!in_array($target, $allowed[$current] ?? [], true)) {
        throw new MgCreatorCampaignPilotException('The pilot cannot move from ' . $current . ' to ' . $target . '.', 409, 'CREATOR_CAMPAIGN_PILOT_TRANSITION_DENIED');
    }
    if ($target === 'active' && empty($readiness['start_ready'])) {
        throw new MgCreatorCampaignPilotException('Complete the required readiness steps before starting or resuming the pilot.', 409, 'CREATOR_CAMPAIGN_PILOT_NOT_READY');
    }
    if ($target === 'active' && !empty($pilot['emergency_disabled'])) {
        throw new MgCreatorCampaignPilotException('Clear the emergency stop before starting or resuming the pilot.', 423, 'CREATOR_CAMPAIGN_PILOT_EMERGENCY_DISABLED');
    }
    if ($target === 'completed' && empty($readiness['pilot_validated'])) {
        throw new MgCreatorCampaignPilotException('A successful run and approved review are required before completing the pilot.', 409, 'CREATOR_CAMPAIGN_PILOT_VALIDATION_INCOMPLETE');
    }
    $pdo->prepare(
        "UPDATE creator_campaign_operator_pilots
         SET status=?,
             started_at=IF(?='active',COALESCE(started_at,NOW()),started_at),
             completed_at=IF(?='completed',NOW(),completed_at),
             updated_at=NOW()
         WHERE id=? AND owner_user_id=?"
    )->execute([$target,$target,$target,(int)$pilot['id'],(int)$user['id']]);
    mg_creator_campaign_pilot_event(
        $pdo,
        (int)$pilot['id'],
        (int)$user['id'],
        'creator_campaign.pilot.' . $target,
        $target === 'paused' ? 'medium' : 'info',
        'merchant_workspace',
        (string)$workspace['public_id'],
        'Owner transitioned the Creator Campaign pilot from ' . $current . ' to ' . $target . '.'
    );
    return mg_creator_campaign_pilot_row($pdo, (int)$user['id'], (int)$workspace['id']) ?? $pilot;
}
