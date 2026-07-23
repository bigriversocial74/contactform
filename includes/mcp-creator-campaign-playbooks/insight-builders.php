<?php
declare(strict_types=1);

function mg_mcp_creator_campaign_playbook_campaign_health(
    PDO $pdo,
    array $context,
    array $input
): array {
    $campaignId = mg_mcp_creator_campaign_playbook_public_id($input['campaign_id'] ?? '', 'campaign_id');
    $days = max(1, min((int)($input['days'] ?? 30), 366));
    $campaign = mg_mcp_creator_campaign_playbook_campaign($pdo, $context, $campaignId);
    $validation = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.validate', ['campaign_id' => $campaignId]);
    $analytics = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.analytics.get', ['campaign_id' => $campaignId, 'days' => $days]);
    $applications = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.applications.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $participants = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.participants.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $deliverables = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.deliverables.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $submissions = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.submissions.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $earnings = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.earnings.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $payouts = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.payouts.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $disputes = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.disputes.list', ['campaign_id' => $campaignId, 'limit' => 100]);

    $riskFlags = [];
    $campaignStatus = (string)($campaign['campaign']['status'] ?? '');
    $endsAt = (string)($campaign['campaign']['ends_at'] ?? '');
    if ($campaignStatus === 'active' && $endsAt !== '' && strtotime($endsAt) < time()) {
        $riskFlags[] = ['severity' => 'high', 'code' => 'campaign_end_passed', 'message' => 'Campaign is active after its configured end time.'];
    }
    $pendingApplications = array_values(array_filter(
        (array)($applications['items'] ?? []),
        static fn(array $item): bool => in_array((string)($item['status'] ?? ''), ['submitted', 'under_review', 'information_requested'], true)
    ));
    if (count($pendingApplications) > 0) {
        $riskFlags[] = ['severity' => 'medium', 'code' => 'pending_applications', 'message' => count($pendingApplications) . ' Creator applications require review.'];
    }
    $pendingSubmissions = array_values(array_filter(
        (array)($submissions['items'] ?? []),
        static fn(array $item): bool => in_array((string)($item['status'] ?? ''), ['submitted', 'under_review', 'revision_requested'], true)
    ));
    if (count($pendingSubmissions) > 0) {
        $riskFlags[] = ['severity' => 'medium', 'code' => 'pending_submissions', 'message' => count($pendingSubmissions) . ' submissions require attention.'];
    }
    $openDisputes = array_values(array_filter(
        (array)($disputes['items'] ?? []),
        static fn(array $item): bool => empty($item['resolved_at'])
    ));
    if (count($openDisputes) > 0) {
        $riskFlags[] = ['severity' => 'high', 'code' => 'open_disputes', 'message' => count($openDisputes) . ' unresolved dispute records require owner review.'];
    }
    $summary = is_array($analytics['summary'] ?? null) ? $analytics['summary'] : [];
    if ((int)($summary['unique_clicks'] ?? 0) > 0 && (int)($summary['conversion_rate_bps'] ?? 0) < 100) {
        $riskFlags[] = ['severity' => 'low', 'code' => 'low_conversion_rate', 'message' => 'Conversion rate is below 1% for the selected range.'];
    }

    return [
        'campaign' => $campaign,
        'range' => ['days' => $days],
        'validation' => $validation,
        'analytics' => $analytics,
        'counts' => [
            'applications' => count((array)($applications['items'] ?? [])),
            'pending_applications' => count($pendingApplications),
            'participants' => count((array)($participants['items'] ?? [])),
            'deliverables' => count((array)($deliverables['items'] ?? [])),
            'submissions' => count((array)($submissions['items'] ?? [])),
            'pending_submissions' => count($pendingSubmissions),
            'earnings' => count((array)($earnings['items'] ?? [])),
            'payouts' => count((array)($payouts['items'] ?? [])),
            'disputes' => count((array)($disputes['items'] ?? [])),
            'open_disputes' => count($openDisputes),
        ],
        'risk_flags' => $riskFlags,
        'agent_notes' => mg_mcp_creator_campaign_playbook_text(
            $input['assessment_notes'] ?? '',
            0,
            10000,
            'Assessment notes',
            false
        ),
        'recommended_actions' => mg_mcp_creator_campaign_playbook_json(
            is_array($input['recommended_actions'] ?? null) ? $input['recommended_actions'] : [],
            'recommended actions',
            30000
        ),
        'boundaries' => [
            'read_only_analysis' => true,
            'canonical_action_request_created' => false,
            'canonical_mutation_enabled' => false,
        ],
    ];
}

function mg_mcp_creator_campaign_playbook_earnings_review(
    PDO $pdo,
    array $context,
    array $input
): array {
    $campaignId = mg_mcp_creator_campaign_playbook_public_id($input['campaign_id'] ?? '', 'campaign_id');
    $earningId = mg_mcp_creator_campaign_playbook_public_id($input['earning_id'] ?? '', 'earning_id');
    $campaign = mg_mcp_creator_campaign_playbook_campaign($pdo, $context, $campaignId);
    $earning = mg_mcp_creator_campaign_playbook_list_item(
        $pdo,
        $context,
        'creator_campaigns.earnings.list',
        $campaignId,
        $earningId,
        'Creator earning'
    );
    $attributions = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.attributions.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $payouts = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.payouts.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $disputes = mg_mcp_creator_campaign_playbook_read($pdo, $context, 'creator_campaigns.disputes.list', ['campaign_id' => $campaignId, 'limit' => 100]);
    $openDisputes = array_values(array_filter(
        (array)($disputes['items'] ?? []),
        static fn(array $item): bool => empty($item['resolved_at'])
    ));

    $recommendation = strtolower(trim((string)($input['recommendation'] ?? 'hold')));
    if (!in_array($recommendation, ['approve', 'hold', 'reject', 'reverse'], true)) {
        throw new MgMcpCreatorCampaignPlaybookException('Invalid earnings recommendation.');
    }
    $checks = [
        'agreement_verified' => mg_mcp_creator_campaign_playbook_bool($input['agreement_verified'] ?? false),
        'attribution_verified' => mg_mcp_creator_campaign_playbook_bool($input['attribution_verified'] ?? false),
        'compensation_rule_verified' => mg_mcp_creator_campaign_playbook_bool($input['compensation_rule_verified'] ?? false),
        'budget_verified' => mg_mcp_creator_campaign_playbook_bool($input['budget_verified'] ?? false),
        'refund_clear' => mg_mcp_creator_campaign_playbook_bool($input['refund_clear'] ?? false),
        'fraud_clear' => mg_mcp_creator_campaign_playbook_bool($input['fraud_clear'] ?? false),
        'duplicate_clear' => mg_mcp_creator_campaign_playbook_bool($input['duplicate_clear'] ?? false),
    ];
    $failedChecks = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
    $serverRecommendation = $failedChecks === [] ? $recommendation : 'hold';
    if (count($openDisputes) > 0 && $serverRecommendation === 'approve') {
        $serverRecommendation = 'hold';
        $failedChecks[] = 'open_dispute_review';
    }

    return [
        'campaign' => $campaign,
        'earning' => $earning,
        'evidence' => [
            'attributions' => $attributions['items'] ?? [],
            'payouts' => $payouts['items'] ?? [],
            'disputes' => $disputes['items'] ?? [],
            'open_disputes' => $openDisputes,
        ],
        'assessment' => [
            'agent_recommendation' => $recommendation,
            'server_recommendation' => $serverRecommendation,
            'checks' => $checks,
            'failed_checks' => array_values(array_unique($failedChecks)),
            'rationale' => mg_mcp_creator_campaign_playbook_text($input['rationale'] ?? '', 1, 10000, 'Earnings rationale'),
        ],
        'boundaries' => [
            'earning_decision_enabled' => false,
            'payout_record_enabled' => false,
            'payment_provider_enabled' => false,
            'explicit_owner_approval_required' => true,
        ],
    ];
}

function mg_mcp_creator_campaign_playbook_creator_outreach(
    PDO $pdo,
    array $context,
    array $input
): array {
    $campaignId = mg_mcp_creator_campaign_playbook_public_id($input['campaign_id'] ?? '', 'campaign_id');
    $campaign = mg_mcp_creator_campaign_playbook_campaign($pdo, $context, $campaignId);
    $candidates = mg_mcp_creator_campaign_playbook_creator_candidates(
        $pdo,
        $context,
        mg_mcp_creator_campaign_playbook_json($input['candidates'] ?? [], 'Creator candidates', 60000)
    );
    $campaignRow = mg_creator_campaign_repository_by_public_id($pdo, $campaignId, (int)$context['workspace_id']);
    $eligible = [];
    $blocked = [];
    foreach ($candidates as $candidate) {
        $relationship = $pdo->prepare(
            "SELECT
                (SELECT status FROM creator_campaign_applications WHERE campaign_id=? AND creator_profile_id=cp.id ORDER BY id DESC LIMIT 1) application_status,
                (SELECT status FROM creator_campaign_invitations WHERE campaign_id=? AND creator_profile_id=cp.id ORDER BY id DESC LIMIT 1) invitation_status,
                (SELECT status FROM creator_campaign_participants WHERE campaign_id=? AND creator_profile_id=cp.id ORDER BY id DESC LIMIT 1) participant_status
             FROM creator_profiles cp WHERE cp.public_id=? LIMIT 1"
        );
        $relationship->execute([
            (int)$campaignRow['id'],
            (int)$campaignRow['id'],
            (int)$campaignRow['id'],
            (string)$candidate['creator_profile_id'],
        ]);
        $status = $relationship->fetch(PDO::FETCH_ASSOC) ?: [];
        $candidate['campaign_relationship'] = $status;
        $hasRelationship = !empty($status['participant_status'])
            || in_array((string)($status['application_status'] ?? ''), ['submitted', 'under_review', 'information_requested', 'approved'], true)
            || in_array((string)($status['invitation_status'] ?? ''), ['pending', 'sent', 'accepted'], true);
        if ($hasRelationship) {
            $candidate['blocked_reason'] = 'Creator already has an active campaign relationship or pending invitation/application.';
            $blocked[] = $candidate;
        } else {
            $eligible[] = $candidate;
        }
    }

    return [
        'campaign' => $campaign,
        'ranked_candidates' => $candidates,
        'eligible_invitation_drafts' => $eligible,
        'blocked_candidates' => $blocked,
        'boundaries' => [
            'existing_approved_creators_only' => true,
            'invitation_send_enabled' => false,
            'participant_creation_enabled' => false,
            'owner_approval_required' => true,
        ],
    ];
}
