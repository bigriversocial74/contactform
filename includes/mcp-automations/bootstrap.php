<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app.php';

final class MgMcpAutomationGrantException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly string $errorCode = 'MCP_AUTOMATION_GRANT_INVALID'
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

const MG_MCP_AUTOMATION_GRANT_STATUSES = ['draft', 'active', 'paused', 'expired', 'revoked'];
const MG_MCP_AUTOMATION_GRANT_OPERATION_CLASSES = ['read', 'monitor', 'recommend', 'task', 'draft', 'approval_gated'];
const MG_MCP_AUTOMATION_GRANT_RISK_LEVELS = ['low', 'medium', 'high', 'critical'];

function mg_mcp_automation_operation_rank(string $operationClass): int
{
    return match ($operationClass) {
        'read' => 10,
        'monitor' => 20,
        'recommend' => 30,
        'task' => 40,
        'draft' => 50,
        'approval_gated' => 60,
        'bounded_auto' => 70,
        'prohibited' => 1000,
        default => 1000,
    };
}

function mg_mcp_automation_playbook_catalog(): array
{
    return [
        'catalog_research' => [
            'label' => 'Catalog research',
            'description' => 'Read account context and published catalog items for owner-directed research.',
            'operation_class' => 'read',
            'workspace_required' => false,
            'tools' => [
                'microgifter.account.get_connection_context' => 'profile:read',
                'microgifter.catalog.search' => 'catalog:read',
                'microgifter.catalog.get_item' => 'catalog:read',
            ],
        ],
        'gift_draft_preparation' => [
            'label' => 'Gift draft preparation',
            'description' => 'Prepare reviewable gift drafts. No purchase, issuance, delivery, or charge.',
            'operation_class' => 'draft',
            'workspace_required' => false,
            'tools' => [
                'microgifter.catalog.search' => 'catalog:read',
                'microgifter.catalog.get_item' => 'catalog:read',
                'microgifter.gift.create_draft' => 'gift:draft',
            ],
        ],
        'campaign_draft_preparation' => [
            'label' => 'Campaign draft preparation',
            'description' => 'Prepare reviewable merchant campaign drafts without publication, scheduling, or spend.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'tools' => [
                'microgifter.campaign.create_draft' => 'campaign:draft',
            ],
        ],
        'reward_draft_preparation' => [
            'label' => 'Reward draft preparation',
            'description' => 'Prepare reviewable reward drafts without activation, issuance, or fulfillment.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'tools' => [
                'microgifter.reward.create_draft' => 'reward:draft',
            ],
        ],
        'message_draft_preparation' => [
            'label' => 'Message draft preparation',
            'description' => 'Prepare reviewable message drafts without sending or scheduling.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'tools' => [
                'microgifter.message.create_draft' => 'message:draft',
            ],
        ],
        'creator_campaign_campaign_preparation' => [
            'label' => 'Creator Campaign preparation assistant',
            'description' => 'Combine catalog research and Creator Campaign proposal tools into one non-convertible owner-review artifact.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'execution_mode' => 'bounded_review_artifact',
            'tools' => [
                'microgifter.creator_campaigns.playbooks.campaign_preparation.run' => 'creator_campaign_playbooks:campaign_preparation',
                'microgifter.catalog.search' => 'catalog:read',
                'microgifter.catalog.get_item' => 'catalog:read',
                'microgifter.creator_campaigns.get' => 'creator_campaigns:read',
                'microgifter.creator_campaigns.validate' => 'creator_campaigns:read',
                'microgifter.creator_campaigns.draft.create' => 'creator_campaigns:draft',
                'microgifter.creator_campaigns.draft.update' => 'creator_campaigns:draft',
                'microgifter.creator_campaigns.products.propose' => 'creator_campaign_products:draft',
                'microgifter.creator_campaigns.eligibility.propose' => 'creator_campaign_eligibility:draft',
                'microgifter.creator_campaigns.deliverables.propose' => 'creator_campaign_deliverables:draft',
                'microgifter.creator_campaigns.compensation.propose' => 'creator_campaign_compensation:draft',
            ],
        ],
        'creator_campaign_application_review' => [
            'label' => 'Creator application review assistant',
            'description' => 'Read one pending application and prepare a bounded recommendation or information-request draft without deciding it.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'execution_mode' => 'bounded_review_artifact',
            'tools' => [
                'microgifter.creator_campaigns.playbooks.application_review.run' => 'creator_campaign_playbooks:application_review',
                'microgifter.creator_campaigns.get' => 'creator_campaigns:read',
                'microgifter.creator_campaigns.applications.list' => 'creator_campaign_applications:read',
                'microgifter.creator_campaigns.message.draft' => 'creator_campaign_messages:draft',
            ],
        ],
        'creator_campaign_content_review' => [
            'label' => 'Creator content review assistant',
            'description' => 'Read a deliverable and submission and prepare review feedback without approving, rejecting, or requesting revision.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'execution_mode' => 'bounded_review_artifact',
            'tools' => [
                'microgifter.creator_campaigns.playbooks.content_review.run' => 'creator_campaign_playbooks:content_review',
                'microgifter.creator_campaigns.get' => 'creator_campaigns:read',
                'microgifter.creator_campaigns.deliverables.list' => 'creator_campaign_deliverables:read',
                'microgifter.creator_campaigns.submissions.list' => 'creator_campaign_submissions:read',
                'microgifter.creator_campaigns.submission_feedback.draft' => 'creator_campaign_submission_feedback:draft',
            ],
        ],
        'creator_campaign_health' => [
            'label' => 'Creator Campaign health assistant',
            'description' => 'Aggregate campaign validation, engagement, participation, submission, earning, payout, and dispute evidence into a reviewable report.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'execution_mode' => 'bounded_review_artifact',
            'tools' => [
                'microgifter.creator_campaigns.playbooks.campaign_health.run' => 'creator_campaign_playbooks:campaign_health',
                'microgifter.creator_campaigns.get' => 'creator_campaigns:read',
                'microgifter.creator_campaigns.validate' => 'creator_campaigns:read',
                'microgifter.creator_campaigns.analytics.get' => 'creator_campaigns_analytics:read',
                'microgifter.creator_campaigns.applications.list' => 'creator_campaign_applications:read',
                'microgifter.creator_campaigns.participants.list' => 'creator_campaign_participants:read',
                'microgifter.creator_campaigns.deliverables.list' => 'creator_campaign_deliverables:read',
                'microgifter.creator_campaigns.submissions.list' => 'creator_campaign_submissions:read',
                'microgifter.creator_campaigns.earnings.list' => 'creator_campaign_earnings:read',
                'microgifter.creator_campaigns.payouts.list' => 'creator_campaign_payouts:read',
                'microgifter.creator_campaigns.disputes.list' => 'creator_campaign_disputes:read',
            ],
        ],
        'creator_campaign_earnings_review' => [
            'label' => 'Creator earnings review assistant',
            'description' => 'Verify bounded earning evidence and prepare an approve, hold, reject, or reverse recommendation without changing financial records.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'execution_mode' => 'bounded_review_artifact',
            'tools' => [
                'microgifter.creator_campaigns.playbooks.earnings_review.run' => 'creator_campaign_playbooks:earnings_review',
                'microgifter.creator_campaigns.get' => 'creator_campaigns:read',
                'microgifter.creator_campaigns.earnings.list' => 'creator_campaign_earnings:read',
                'microgifter.creator_campaigns.attributions.list' => 'creator_campaign_attributions:read',
                'microgifter.creator_campaigns.payouts.list' => 'creator_campaign_payouts:read',
                'microgifter.creator_campaigns.disputes.list' => 'creator_campaign_disputes:read',
            ],
        ],
        'creator_campaign_creator_outreach' => [
            'label' => 'Creator outreach assistant',
            'description' => 'Verify approved Microgifter Creator candidates and prepare a ranked invitation list and messages without sending them.',
            'operation_class' => 'draft',
            'workspace_required' => true,
            'execution_mode' => 'bounded_review_artifact',
            'tools' => [
                'microgifter.creator_campaigns.playbooks.creator_outreach.run' => 'creator_campaign_playbooks:creator_outreach',
                'microgifter.creator_campaigns.get' => 'creator_campaigns:read',
                'microgifter.creator_campaigns.applications.list' => 'creator_campaign_applications:read',
                'microgifter.creator_campaigns.participants.list' => 'creator_campaign_participants:read',
                'microgifter.creator_campaigns.invitation.draft' => 'creator_campaign_invitations:draft',
            ],
        ],
        'creator_campaign_lifecycle_actions' => [
            'label' => 'Creator Campaign lifecycle actions',
            'description' => 'Request publication, scheduling, pause, resume, completion, or cancellation. Every request requires separate owner approval and execution.',
            'operation_class' => 'approval_gated',
            'workspace_required' => true,
            'tools' => [
                'microgifter.creator_campaigns.publish' => 'creator_campaigns:publish',
                'microgifter.creator_campaigns.schedule' => 'creator_campaigns:publish',
                'microgifter.creator_campaigns.pause' => 'creator_campaigns:publish',
                'microgifter.creator_campaigns.resume' => 'creator_campaigns:publish',
                'microgifter.creator_campaigns.complete' => 'creator_campaigns:publish',
                'microgifter.creator_campaigns.cancel' => 'creator_campaigns:publish',
            ],
        ],
        'creator_campaign_participant_actions' => [
            'label' => 'Creator Campaign participant actions',
            'description' => 'Request application decisions, invitations, agreement offers, and participant controls with per-action owner approval.',
            'operation_class' => 'approval_gated',
            'workspace_required' => true,
            'tools' => [
                'microgifter.creator_campaigns.application.approve' => 'creator_campaign_participants:manage',
                'microgifter.creator_campaigns.application.decline' => 'creator_campaign_participants:manage',
                'microgifter.creator_campaigns.invitation.send' => 'creator_campaign_participants:manage',
                'microgifter.creator_campaigns.agreement.offer' => 'creator_campaign_agreements:manage',
                'microgifter.creator_campaigns.participant.suspend' => 'creator_campaign_participants:manage',
                'microgifter.creator_campaigns.participant.remove' => 'creator_campaign_participants:manage',
            ],
        ],
        'creator_campaign_review_actions' => [
            'label' => 'Creator Campaign review actions',
            'description' => 'Request submission decisions and attribution overrides with per-action owner approval.',
            'operation_class' => 'approval_gated',
            'workspace_required' => true,
            'tools' => [
                'microgifter.creator_campaigns.submission.approve' => 'creator_campaign_submissions:review',
                'microgifter.creator_campaigns.submission.request_revision' => 'creator_campaign_submissions:review',
                'microgifter.creator_campaigns.submission.reject' => 'creator_campaign_submissions:review',
                'microgifter.creator_campaigns.attribution.override' => 'creator_campaign_attribution:manage',
            ],
        ],
        'creator_campaign_financial_actions' => [
            'label' => 'Creator Campaign financial actions',
            'description' => 'Request earning decisions, internal payout records, and dispute resolutions. No payment provider is called.',
            'operation_class' => 'approval_gated',
            'workspace_required' => true,
            'tools' => [
                'microgifter.creator_campaigns.earning.approve' => 'creator_campaign_earnings:manage',
                'microgifter.creator_campaigns.earning.hold' => 'creator_campaign_earnings:manage',
                'microgifter.creator_campaigns.earning.reject' => 'creator_campaign_earnings:manage',
                'microgifter.creator_campaigns.earning.reverse' => 'creator_campaign_earnings:manage',
                'microgifter.creator_campaigns.payout.record' => 'creator_campaign_payouts:manage',
                'microgifter.creator_campaigns.dispute.resolve' => 'creator_campaign_disputes:manage',
            ],
        ],
    ];
}

function mg_mcp_automation_schema_ready(PDO $pdo): bool
{
    foreach ([
        'mcp_connections',
        'mcp_connection_scopes',
        'mcp_automation_grants',
        'mcp_automations',
        'mcp_automation_triggers',
        'mcp_automation_runs',
        'mcp_automation_actions',
        'mcp_action_receipts',
        'mcp_security_events',
    ] as $table) {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
        $stmt->execute([$table]);
        if (!$stmt->fetchColumn()) {
            return false;
        }
    }
    return true;
}

function mg_mcp_automation_json_list(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_unique(array_filter(array_map('strval', $value), static fn(string $item): bool => $item !== '')));
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    try {
        $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return [];
    }
    return is_array($decoded)
        ? array_values(array_unique(array_filter(array_map('strval', $decoded), static fn(string $item): bool => $item !== '')))
        : [];
}

function mg_mcp_automation_json_object(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    try {
        $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return [];
    }
    return is_array($decoded) ? $decoded : [];
}
