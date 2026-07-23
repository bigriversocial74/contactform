<?php
declare(strict_types=1);

function mg_creator_campaign_pilot_path(array $value, array $path): mixed
{
    $cursor = $value;
    foreach ($path as $key) {
        if (!is_array($cursor) || !array_key_exists($key, $cursor)) return null;
        $cursor = $cursor[$key];
    }
    return $cursor;
}

function mg_creator_campaign_pilot_first_text(array $value, array $paths): string
{
    foreach ($paths as $path) {
        $candidate = mg_creator_campaign_pilot_path($value, $path);
        if (is_scalar($candidate) && trim((string)$candidate) !== '') return trim((string)$candidate);
    }
    return '';
}

function mg_creator_campaign_pilot_artifact_playbook(array $payload): string
{
    return strtolower(trim((string)mg_creator_campaign_pilot_path($payload, ['playbook','key'])));
}

function mg_creator_campaign_pilot_action_seed(array $payload, string $tool): array
{
    $output = is_array($payload['output'] ?? null) ? $payload['output'] : [];
    $campaignId = mg_creator_campaign_pilot_first_text($payload, [
        ['campaign_id'],
        ['output','campaign','campaign','public_id'],
        ['output','campaign','campaign','id'],
        ['output','campaign','public_id'],
        ['output','campaign','id'],
    ]);
    $seed = [];
    if ($campaignId !== '') $seed['campaign_id'] = $campaignId;

    if (str_contains($tool, '.application.')) {
        $applicationId = mg_creator_campaign_pilot_first_text($output, [
            ['application','public_id'],['application','id'],
        ]);
        if ($applicationId !== '') $seed['application_id'] = $applicationId;
        $seed['reason'] = mg_creator_campaign_pilot_first_text($output, [
            ['assessment','rationale'],['assessment','draft_message'],
        ]);
    } elseif (str_contains($tool, '.submission.')) {
        $submissionId = mg_creator_campaign_pilot_first_text($output, [
            ['submission','public_id'],['submission','id'],
        ]);
        if ($submissionId !== '') $seed['submission_id'] = $submissionId;
        $seed['feedback'] = mg_creator_campaign_pilot_first_text($output, [
            ['assessment','feedback_draft','feedback'],
            ['assessment','feedback_draft','values','feedback'],
            ['assessment','rationale'],
        ]);
        $seed['reason'] = $seed['feedback'] ?? '';
    } elseif (str_contains($tool, '.earning.')) {
        $earningId = mg_creator_campaign_pilot_first_text($output, [
            ['earning','public_id'],['earning','id'],
        ]);
        if ($earningId !== '') $seed['earning_id'] = $earningId;
        $seed['reason'] = mg_creator_campaign_pilot_first_text($output, [
            ['assessment','rationale'],
        ]);
    } elseif ($tool === 'microgifter.creator_campaigns.invitation.send') {
        $creatorId = mg_creator_campaign_pilot_first_text($output, [
            ['eligible_invitation_drafts',0,'creator_profile_id'],
            ['ranked_candidates',0,'creator_profile_id'],
        ]);
        if ($creatorId !== '') $seed['creator_profile_id'] = $creatorId;
        $seed['invitation_message'] = mg_creator_campaign_pilot_first_text($output, [
            ['eligible_invitation_drafts',0,'draft_message'],
            ['eligible_invitation_drafts',0,'invitation_message'],
            ['ranked_candidates',0,'draft_message'],
        ]);
        $seed['reason'] = 'Owner accepted the Creator outreach recommendation.';
    } else {
        $seed['reason'] = mg_creator_campaign_pilot_first_text($output, [
            ['agent_notes'],['assessment','rationale'],
        ]);
    }

    $lock = mg_creator_campaign_pilot_first_text($output, [
        ['campaign','campaign','lock_version'],
        ['campaign','lock_version'],
        ['application','lock_version'],
        ['submission','lock_version'],
        ['earning','lock_version'],
    ]);
    if ($lock !== '' && (int)$lock > 0) $seed['expected_lock_version'] = (int)$lock;

    foreach ($seed as $key => $value) {
        if ($value === '' || $value === null) unset($seed[$key]);
    }
    return $seed;
}

function mg_creator_campaign_pilot_action_options(array $artifact): array
{
    $payload = is_array($artifact['payload'] ?? null) ? $artifact['payload'] : [];
    $playbookKey = mg_creator_campaign_pilot_artifact_playbook($payload);
    $playbook = mg_creator_campaign_pilot_playbook_catalog()[$playbookKey] ?? null;
    if (!is_array($playbook)) return [];
    $options = [];
    foreach ((array)$playbook['actions'] as $tool) {
        $options[$tool] = [
            'tool' => $tool,
            'label' => mg_creator_campaign_pilot_action_label($tool),
            'seed' => mg_creator_campaign_pilot_action_seed($payload, $tool),
            'contract' => mg_mcp_creator_campaign_action_contract($tool),
        ];
    }
    return $options;
}

function mg_creator_campaign_pilot_action_context(
    array $user,
    array $workspace,
    array $grant
): array {
    if ((string)$grant['status'] !== 'active'
        || (string)$grant['connection_status'] !== 'active'
        || (string)$grant['client_status'] !== 'active'
        || (string)$grant['maximum_operation_class'] !== 'approval_gated'
        || (string)$grant['connection_maximum_operation_class'] !== 'approval_gated'
        || (string)$grant['client_maximum_operation_class'] !== 'approval_gated') {
        throw new MgCreatorCampaignPilotException(
            'The selected action grant, connection, and client must all be active and approval-gated.',
            409,
            'CREATOR_CAMPAIGN_PILOT_ACTION_AUTHORITY_INACTIVE'
        );
    }
    return [
        'user_id' => (int)$user['id'],
        'workspace_type' => 'merchant_workspace',
        'workspace_id' => (int)$workspace['id'],
        'workspace' => [
            'id' => (string)$workspace['public_id'],
            'database_id' => (int)$workspace['id'],
            'name' => (string)$workspace['display_name'],
        ],
        'connection_db_id' => (int)$grant['connection_id'],
        'connection_public_id' => (string)$grant['connection_public_id'],
        'client_db_id' => (int)$grant['client_id'],
        'client_public_id' => (string)$grant['client_public_id'],
        'maximum_operation_class' => (string)$grant['connection_maximum_operation_class'],
        'client_maximum_operation_class' => (string)$grant['client_maximum_operation_class'],
        'scopes' => array_map('strval', (array)$grant['scopes']),
    ];
}
