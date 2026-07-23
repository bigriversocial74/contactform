<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app.php';
require_once dirname(__DIR__) . '/mcp-automations.php';
require_once dirname(__DIR__) . '/mcp-drafts.php';
require_once dirname(__DIR__) . '/mcp-creator-campaign-actions.php';
require_once __DIR__ . '/runtime.php';

final class MgCreatorCampaignPilotException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly string $pilotCode = 'CREATOR_CAMPAIGN_PILOT_INVALID'
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int { return $this->httpStatus; }
    public function pilotCode(): string { return $this->pilotCode; }
}

const MG_CREATOR_CAMPAIGN_PILOT_STATUSES = ['setup','ready','active','paused','completed','disabled'];
const MG_CREATOR_CAMPAIGN_PILOT_MANUAL_CHECKS = [
    'deployment_verified' => 'Latest integration code is deployed',
    'sql_verified' => 'Phase 14 SQL is imported',
    'emergency_tested' => 'Emergency-stop procedure has been tested',
    'support_ready' => 'A pilot support contact is available',
];

function mg_creator_campaign_pilot_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    try {
        $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
    return is_array($decoded) ? $decoded : [];
}

function mg_creator_campaign_pilot_text(
    mixed $value,
    int $max,
    string $label,
    bool $required = false,
    int $min = 0
): string {
    $text = trim((string)$value);
    if (($required && $text === '') || mb_strlen($text) > $max || ($text !== '' && mb_strlen($text) < $min)) {
        throw new MgCreatorCampaignPilotException('Invalid ' . $label . '.', 422, 'CREATOR_CAMPAIGN_PILOT_VALIDATION_FAILED');
    }
    return $text;
}

function mg_creator_campaign_pilot_public_id(mixed $value, string $label): string
{
    $id = mg_creator_campaign_pilot_text($value, 190, $label, true, 8);
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,189}$/', $id) !== 1
        && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id) !== 1) {
        throw new MgCreatorCampaignPilotException('Invalid ' . $label . '.', 422, 'CREATOR_CAMPAIGN_PILOT_VALIDATION_FAILED');
    }
    return $id;
}

function mg_creator_campaign_pilot_playbook_catalog(): array
{
    return [
        'creator_campaign_campaign_preparation' => [
            'label' => 'Campaign preparation',
            'summary' => 'Validate campaign structure, products, eligibility, deliverables, and compensation before launch.',
            'scope' => 'creator_campaign_playbooks:campaign_preparation',
            'actions' => [
                'microgifter.creator_campaigns.publish',
                'microgifter.creator_campaigns.schedule',
            ],
        ],
        'creator_campaign_application_review' => [
            'label' => 'Application review',
            'summary' => 'Review a pending Creator application and prepare a merchant recommendation.',
            'scope' => 'creator_campaign_playbooks:application_review',
            'actions' => [
                'microgifter.creator_campaigns.application.approve',
                'microgifter.creator_campaigns.application.decline',
            ],
        ],
        'creator_campaign_content_review' => [
            'label' => 'Content review',
            'summary' => 'Evaluate a submission, disclosure, links, claims, and required revisions.',
            'scope' => 'creator_campaign_playbooks:content_review',
            'actions' => [
                'microgifter.creator_campaigns.submission.approve',
                'microgifter.creator_campaigns.submission.request_revision',
                'microgifter.creator_campaigns.submission.reject',
            ],
        ],
        'creator_campaign_health' => [
            'label' => 'Campaign health',
            'summary' => 'Aggregate campaign validation, activity, risk flags, disputes, earnings, and performance.',
            'scope' => 'creator_campaign_playbooks:campaign_health',
            'actions' => [
                'microgifter.creator_campaigns.pause',
                'microgifter.creator_campaigns.resume',
                'microgifter.creator_campaigns.complete',
                'microgifter.creator_campaigns.cancel',
            ],
        ],
        'creator_campaign_earnings_review' => [
            'label' => 'Earnings review',
            'summary' => 'Review earning evidence, attribution, agreements, budgets, refunds, fraud, and disputes.',
            'scope' => 'creator_campaign_playbooks:earnings_review',
            'actions' => [
                'microgifter.creator_campaigns.earning.approve',
                'microgifter.creator_campaigns.earning.hold',
                'microgifter.creator_campaigns.earning.reject',
                'microgifter.creator_campaigns.earning.reverse',
            ],
        ],
        'creator_campaign_creator_outreach' => [
            'label' => 'Creator outreach',
            'summary' => 'Rank eligible approved Creators and prepare invitation copy without sending it.',
            'scope' => 'creator_campaign_playbooks:creator_outreach',
            'actions' => [
                'microgifter.creator_campaigns.invitation.send',
            ],
        ],
    ];
}

function mg_creator_campaign_pilot_action_label(string $tool): string
{
    return [
        'microgifter.creator_campaigns.publish' => 'Publish campaign',
        'microgifter.creator_campaigns.schedule' => 'Schedule campaign',
        'microgifter.creator_campaigns.pause' => 'Pause campaign',
        'microgifter.creator_campaigns.resume' => 'Resume campaign',
        'microgifter.creator_campaigns.complete' => 'Complete campaign',
        'microgifter.creator_campaigns.cancel' => 'Cancel campaign',
        'microgifter.creator_campaigns.application.approve' => 'Approve application',
        'microgifter.creator_campaigns.application.decline' => 'Decline application',
        'microgifter.creator_campaigns.invitation.send' => 'Send invitation',
        'microgifter.creator_campaigns.submission.approve' => 'Approve submission',
        'microgifter.creator_campaigns.submission.request_revision' => 'Request revision',
        'microgifter.creator_campaigns.submission.reject' => 'Reject submission',
        'microgifter.creator_campaigns.earning.approve' => 'Approve earning',
        'microgifter.creator_campaigns.earning.hold' => 'Hold earning',
        'microgifter.creator_campaigns.earning.reject' => 'Reject earning',
        'microgifter.creator_campaigns.earning.reverse' => 'Reverse earning',
    ][$tool] ?? ucwords(str_replace(['microgifter.creator_campaigns.', '.', '_'], ['', ' ', ' '], $tool));
}
