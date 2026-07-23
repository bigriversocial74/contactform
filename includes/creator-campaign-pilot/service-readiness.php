<?php
declare(strict_types=1);

function mg_creator_campaign_pilot_event(
    PDO $pdo,
    int $pilotId,
    int $ownerUserId,
    string $eventType,
    string $severity = 'info',
    ?string $subjectType = null,
    ?string $subjectPublicId = null,
    ?string $note = null,
    array $metadata = []
): void {
    if (!in_array($severity, ['info','low','medium','high','critical'], true)) $severity = 'info';
    $pdo->prepare(
        "INSERT INTO creator_campaign_operator_events
         (public_id,pilot_id,owner_user_id,event_type,severity,subject_type,subject_public_id,note,metadata_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW())"
    )->execute([
        mg_public_uuid(),
        $pilotId,
        $ownerUserId,
        mb_substr($eventType, 0, 120),
        $severity,
        $subjectType !== null ? mb_substr($subjectType, 0, 80) : null,
        $subjectPublicId !== null ? mb_substr($subjectPublicId, 0, 190) : null,
        $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null,
        $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
}

function mg_creator_campaign_pilot_ensure(PDO $pdo, array $user, array $workspace): array
{
    if (!mg_creator_campaign_pilot_schema_ready($pdo)) {
        throw new MgCreatorCampaignPilotException(
            'Import the Phase 14 SQL before opening the production pilot workspace.',
            503,
            'CREATOR_CAMPAIGN_PILOT_SCHEMA_MISSING'
        );
    }
    $ownerId = (int)($user['id'] ?? 0);
    $workspaceId = (int)($workspace['id'] ?? 0);
    if ($ownerId < 1 || $workspaceId < 1 || (int)($workspace['merchant_user_id'] ?? 0) !== $ownerId) {
        throw new MgCreatorCampaignPilotException('Merchant owner authority is required.', 403, 'CREATOR_CAMPAIGN_PILOT_OWNER_REQUIRED');
    }
    $existing = mg_creator_campaign_pilot_row($pdo, $ownerId, $workspaceId);
    if ($existing) return $existing;

    $publicId = mg_public_uuid();
    $checklist = array_fill_keys(array_keys(MG_CREATOR_CAMPAIGN_PILOT_MANUAL_CHECKS), false);
    $pdo->prepare(
        "INSERT INTO creator_campaign_operator_pilots
         (public_id,workspace_id,owner_user_id,status,checklist_json,created_at,updated_at)
         VALUES (?,?,?,'setup',?,NOW(),NOW())"
    )->execute([
        $publicId,
        $workspaceId,
        $ownerId,
        json_encode($checklist, JSON_THROW_ON_ERROR),
    ]);
    $pilot = mg_creator_campaign_pilot_row($pdo, $ownerId, $workspaceId);
    if (!$pilot) throw new MgCreatorCampaignPilotException('Pilot record could not be created.', 500);
    mg_creator_campaign_pilot_event(
        $pdo,
        (int)$pilot['id'],
        $ownerId,
        'creator_campaign.pilot.created',
        'info',
        'merchant_workspace',
        (string)$workspace['public_id'],
        'Merchant opened the Creator Campaign production pilot workspace.'
    );
    return $pilot;
}

function mg_creator_campaign_pilot_has_playbook(array $grant): bool
{
    foreach ((array)($grant['allowed_playbooks'] ?? []) as $key) {
        if (str_starts_with((string)$key, 'creator_campaign_')) return true;
    }
    return false;
}

function mg_creator_campaign_pilot_readiness(
    array $pilot,
    array $connections,
    array $grants,
    array $definitions,
    array $runs,
    array $artifacts,
    array $actionGrants
): array {
    $activeDraftConnections = array_values(array_filter($connections, static fn(array $row): bool =>
        (string)$row['status'] === 'active'
        && (string)$row['client_status'] === 'active'
        && (string)$row['maximum_operation_class'] === 'draft'
        && (string)$row['client_maximum_operation_class'] === 'draft'
    ));
    $activePlaybookGrants = array_values(array_filter($grants, static fn(array $row): bool =>
        (string)$row['status'] === 'active'
        && (string)$row['maximum_operation_class'] === 'draft'
        && mg_creator_campaign_pilot_has_playbook($row)
        && (string)$row['connection_status'] === 'active'
        && (string)$row['client_status'] === 'active'
        && ($row['expires_at'] === null || strtotime((string)$row['expires_at']) > time())
    ));
    $activeDefinitions = array_values(array_filter($definitions, static fn(array $row): bool =>
        (string)$row['status'] === 'active'
        && (string)$row['trigger_status'] === 'active'
        && (string)$row['grant_status'] === 'active'
        && (string)$row['connection_status'] === 'active'
        && (string)$row['client_status'] === 'active'
    ));
    $successfulRuns = array_values(array_filter($runs, static fn(array $row): bool =>
        (string)$row['status'] === 'succeeded'
        && (string)($row['receipt_status'] ?? '') === 'succeeded'
        && (string)($row['artifact_public_id'] ?? '') !== ''
    ));
    $approvedArtifacts = array_values(array_filter($artifacts, static fn(array $row): bool =>
        (string)$row['status'] === 'approved'
    ));
    $manual = is_array($pilot['checklist'] ?? null) ? $pilot['checklist'] : [];
    $supportReady = trim((string)($pilot['support_contact'] ?? '')) !== '' && !empty($manual['support_ready']);
    $emergencyClear = empty($pilot['emergency_disabled']);

    $steps = [
        'connection' => [
            'label' => 'Draft-authority AI connection',
            'detail' => 'An active merchant-workspace connection may use the six Phase 13D review tools.',
            'complete' => $activeDraftConnections !== [],
            'href' => '/account-ai-connections.php',
            'required_start' => true,
        ],
        'grant' => [
            'label' => 'Bounded playbook grant',
            'detail' => 'An active draft grant contains at least one fixed Creator Campaign playbook.',
            'complete' => $activePlaybookGrants !== [],
            'href' => '/account-agent-automations.php',
            'required_start' => true,
        ],
        'definition' => [
            'label' => 'Active playbook definition',
            'detail' => 'A matching definition and manual trigger are active.',
            'complete' => $activeDefinitions !== [],
            'href' => '/account-agent-automation-definitions.php',
            'required_start' => true,
        ],
        'action_grant' => [
            'label' => 'Approval-gated action grant',
            'detail' => 'Accepted recommendations can be prepared as separate Phase 13C requests.',
            'complete' => $actionGrants !== [],
            'href' => '/account-agent-automations.php',
            'required_start' => false,
        ],
        'emergency' => [
            'label' => 'Emergency controls verified',
            'detail' => 'The owner has tested the stop procedure and the workspace is currently enabled.',
            'complete' => $emergencyClear && !empty($manual['emergency_tested']),
            'href' => '#pilot-checklist',
            'required_start' => true,
        ],
        'support' => [
            'label' => 'Pilot support coverage',
            'detail' => 'A named support contact is recorded for merchant escalation.',
            'complete' => $supportReady,
            'href' => '#pilot-checklist',
            'required_start' => true,
        ],
        'successful_run' => [
            'label' => 'Successful bounded run',
            'detail' => 'A playbook produced a review artifact and canonical receipt without a native mutation.',
            'complete' => $successfulRuns !== [],
            'href' => '#pilot-runs',
            'required_start' => false,
        ],
        'approved_review' => [
            'label' => 'Owner-reviewed recommendation',
            'detail' => 'At least one Phase 13D review artifact has an explicit owner approval.',
            'complete' => $approvedArtifacts !== [],
            'href' => '#pilot-artifacts',
            'required_start' => false,
        ],
    ];

    $requiredStart = array_values(array_filter($steps, static fn(array $step): bool => !empty($step['required_start'])));
    $startReady = $requiredStart !== [] && count(array_filter($requiredStart, static fn(array $step): bool => !empty($step['complete']))) === count($requiredStart);
    $completed = count(array_filter($steps, static fn(array $step): bool => !empty($step['complete'])));
    $score = (int)round(($completed / max(1, count($steps))) * 100);

    return [
        'score' => $score,
        'completed' => $completed,
        'total' => count($steps),
        'start_ready' => $startReady,
        'pilot_validated' => $startReady && $successfulRuns !== [] && $approvedArtifacts !== [],
        'steps' => $steps,
        'counts' => [
            'active_draft_connections' => count($activeDraftConnections),
            'active_playbook_grants' => count($activePlaybookGrants),
            'active_definitions' => count($activeDefinitions),
            'successful_runs' => count($successfulRuns),
            'approved_artifacts' => count($approvedArtifacts),
            'active_action_grants' => count($actionGrants),
        ],
    ];
}
