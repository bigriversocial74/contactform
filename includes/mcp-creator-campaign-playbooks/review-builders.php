<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_playbook_application_review(
    PDO $pdo,
    array $context,
    array $input
): array {
    $campaignId = mg_mcp_creator_campaign_playbook_public_id($input['campaign_id'] ?? '', 'campaign_id');
    $applicationId = mg_mcp_creator_campaign_playbook_public_id($input['application_id'] ?? '', 'application_id');
    $campaign = mg_mcp_creator_campaign_playbook_campaign($pdo, $context, $campaignId);
    $application = mg_mcp_creator_campaign_playbook_list_item(
        $pdo,
        $context,
        'creator_campaigns.applications.list',
        $campaignId,
        $applicationId,
        'Creator application'
    );
    if (!in_array((string)($application['status'] ?? ''), ['submitted', 'under_review', 'information_requested'], true)) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'Only pending Creator applications may be reviewed by this playbook.',
            409,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_APPLICATION_STATE_INVALID'
        );
    }

    $recommendation = strtolower(trim((string)($input['recommendation'] ?? 'needs_review')));
    if (!in_array($recommendation, ['approve', 'decline', 'request_information', 'needs_review'], true)) {
        throw new MgMcpCreatorCampaignPlaybookException('Invalid application recommendation.');
    }
    $fitScore = (int)($input['fit_score'] ?? 0);
    if ($fitScore < 0 || $fitScore > 100) {
        throw new MgMcpCreatorCampaignPlaybookException('Application fit score must be between 0 and 100.');
    }
    $missingInformation = mg_mcp_creator_campaign_playbook_string_list(
        $input['missing_information'] ?? [],
        'missing information'
    );
    if (trim((string)($application['cover_note'] ?? '')) === '') {
        $missingInformation[] = 'Cover note';
    }
    if (trim((string)($application['portfolio_url'] ?? '')) === '') {
        $missingInformation[] = 'Portfolio URL';
    }
    $missingInformation = array_values(array_unique($missingInformation));
    $serverRecommendation = $missingInformation !== [] ? 'request_information' : $recommendation;

    return [
        'campaign' => $campaign,
        'application' => $application,
        'assessment' => [
            'agent_recommendation' => $recommendation,
            'server_recommendation' => $serverRecommendation,
            'fit_score' => $fitScore,
            'eligibility_matches' => mg_mcp_creator_campaign_playbook_string_list(
                $input['eligibility_matches'] ?? [],
                'eligibility matches'
            ),
            'eligibility_gaps' => mg_mcp_creator_campaign_playbook_string_list(
                $input['eligibility_gaps'] ?? [],
                'eligibility gaps'
            ),
            'missing_information' => $missingInformation,
            'rationale' => mg_mcp_creator_campaign_playbook_text(
                $input['rationale'] ?? '',
                1,
                8000,
                'Application rationale'
            ),
            'draft_message' => mg_mcp_creator_campaign_playbook_text(
                $input['draft_message'] ?? '',
                0,
                8000,
                'Draft message',
                false
            ),
        ],
        'boundaries' => [
            'application_decision_enabled' => false,
            'message_send_enabled' => false,
            'owner_approval_required' => true,
        ],
    ];
}

function mg_mcp_creator_campaign_playbook_content_review(
    PDO $pdo,
    array $context,
    array $input
): array {
    $campaignId = mg_mcp_creator_campaign_playbook_public_id($input['campaign_id'] ?? '', 'campaign_id');
    $submissionId = mg_mcp_creator_campaign_playbook_public_id($input['submission_id'] ?? '', 'submission_id');
    $campaign = mg_mcp_creator_campaign_playbook_campaign($pdo, $context, $campaignId);
    $submission = mg_mcp_creator_campaign_playbook_list_item(
        $pdo,
        $context,
        'creator_campaigns.submissions.list',
        $campaignId,
        $submissionId,
        'Creator submission'
    );
    $deliverableId = (string)($submission['deliverable_id'] ?? '');
    $deliverable = $deliverableId !== ''
        ? mg_mcp_creator_campaign_playbook_list_item(
            $pdo,
            $context,
            'creator_campaigns.deliverables.list',
            $campaignId,
            $deliverableId,
            'Campaign deliverable'
        )
        : null;

    $recommendation = strtolower(trim((string)($input['recommendation'] ?? 'request_information')));
    if (!in_array($recommendation, ['approve', 'request_revision', 'reject', 'request_information'], true)) {
        throw new MgMcpCreatorCampaignPlaybookException('Invalid content review recommendation.');
    }
    $checklist = [
        'talking_points_met' => mg_mcp_creator_campaign_playbook_bool($input['talking_points_met'] ?? false),
        'disclosure_present' => mg_mcp_creator_campaign_playbook_bool($input['disclosure_present'] ?? false),
        'links_valid' => mg_mcp_creator_campaign_playbook_bool($input['links_valid'] ?? false),
        'prohibited_claims_found' => mg_mcp_creator_campaign_playbook_bool($input['prohibited_claims_found'] ?? false),
    ];
    $requiredChanges = mg_mcp_creator_campaign_playbook_string_list(
        $input['required_changes'] ?? [],
        'required changes'
    );
    if (!$checklist['disclosure_present']) {
        $requiredChanges[] = 'Add the required disclosure.';
    }
    if (!$checklist['links_valid']) {
        $requiredChanges[] = 'Correct or verify campaign links.';
    }
    if ($checklist['prohibited_claims_found']) {
        $requiredChanges[] = 'Remove prohibited or unsupported claims.';
    }
    $requiredChanges = array_values(array_unique($requiredChanges));
    $serverRecommendation = $recommendation;
    if ($checklist['prohibited_claims_found']) {
        $serverRecommendation = 'request_revision';
    } elseif ($requiredChanges !== [] && $recommendation === 'approve') {
        $serverRecommendation = 'request_revision';
    }

    $feedback = mg_mcp_creator_campaign_playbook_text($input['feedback'] ?? '', 1, 10000, 'Submission feedback');
    try {
        $feedbackDraft = mg_mcp_creator_campaign_proposal_values(
            $pdo,
            $context,
            'submission_feedback.draft',
            [
                'submission_id' => $submissionId,
                'recommendation' => $serverRecommendation,
                'feedback' => $feedback,
                'required_changes' => $requiredChanges,
            ],
            null
        );
    } catch (MgMcpDraftException $error) {
        throw new MgMcpCreatorCampaignPlaybookException(
            $error->getMessage(),
            $error->httpStatus(),
            $error->draftCode()
        );
    } catch (MgMcpBridgeException $error) {
        throw new MgMcpCreatorCampaignPlaybookException(
            $error->getMessage(),
            $error->httpStatus(),
            $error->bridgeCode()
        );
    }

    return [
        'campaign' => $campaign,
        'deliverable' => $deliverable,
        'submission' => $submission,
        'assessment' => [
            'agent_recommendation' => $recommendation,
            'server_recommendation' => $serverRecommendation,
            'checklist' => $checklist,
            'findings' => mg_mcp_creator_campaign_playbook_string_list($input['findings'] ?? [], 'findings'),
            'feedback_draft' => $feedbackDraft,
        ],
        'boundaries' => [
            'submission_decision_enabled' => false,
            'revision_request_enabled' => false,
            'owner_approval_required' => true,
        ],
    ];
}
