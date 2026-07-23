<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mcp-automations.php';
require_once dirname(__DIR__) . '/mcp-drafts.php';

final class MgMcpCreatorCampaignPlaybookException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly string $errorCode = 'MCP_CREATOR_CAMPAIGN_PLAYBOOK_INVALID'
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

const MG_MCP_CREATOR_CAMPAIGN_PLAYBOOKS = [
    'microgifter.creator_campaigns.playbooks.campaign_preparation.run' => [
        'playbook_key' => 'creator_campaign_campaign_preparation',
        'scope' => 'creator_campaign_playbooks:campaign_preparation',
        'risk_level' => 'high',
        'label' => 'Campaign preparation assistant',
    ],
    'microgifter.creator_campaigns.playbooks.application_review.run' => [
        'playbook_key' => 'creator_campaign_application_review',
        'scope' => 'creator_campaign_playbooks:application_review',
        'risk_level' => 'high',
        'label' => 'Creator application review assistant',
    ],
    'microgifter.creator_campaigns.playbooks.content_review.run' => [
        'playbook_key' => 'creator_campaign_content_review',
        'scope' => 'creator_campaign_playbooks:content_review',
        'risk_level' => 'high',
        'label' => 'Content review assistant',
    ],
    'microgifter.creator_campaigns.playbooks.campaign_health.run' => [
        'playbook_key' => 'creator_campaign_health',
        'scope' => 'creator_campaign_playbooks:campaign_health',
        'risk_level' => 'medium',
        'label' => 'Campaign health assistant',
    ],
    'microgifter.creator_campaigns.playbooks.earnings_review.run' => [
        'playbook_key' => 'creator_campaign_earnings_review',
        'scope' => 'creator_campaign_playbooks:earnings_review',
        'risk_level' => 'critical',
        'label' => 'Earnings review assistant',
    ],
    'microgifter.creator_campaigns.playbooks.creator_outreach.run' => [
        'playbook_key' => 'creator_campaign_creator_outreach',
        'scope' => 'creator_campaign_playbooks:creator_outreach',
        'risk_level' => 'high',
        'label' => 'Creator outreach assistant',
    ],
];

function mg_mcp_creator_campaign_playbook_contract(string $toolName): array
{
    $contract = MG_MCP_CREATOR_CAMPAIGN_PLAYBOOKS[$toolName] ?? null;
    if (!is_array($contract)) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'Unsupported Creator Campaign playbook.',
            404,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_UNKNOWN'
        );
    }
    return $contract;
}

function mg_mcp_creator_campaign_playbook_public_id(mixed $value, string $field, bool $required = true): string
{
    $id = trim((string)$value);
    if ($id === '' && !$required) {
        return '';
    }
    if ($id === '' || strlen($id) > 80 || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,79}$/', $id) !== 1) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'Invalid ' . $field . '.',
            422,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_VALIDATION_FAILED'
        );
    }
    return $id;
}

function mg_mcp_creator_campaign_playbook_text(
    mixed $value,
    int $minimum,
    int $maximum,
    string $field,
    bool $required = true
): string {
    $text = trim((string)$value);
    $length = mb_strlen($text);
    if ((!$required && $text === '')) {
        return '';
    }
    if ($length < $minimum || $length > $maximum) {
        throw new MgMcpCreatorCampaignPlaybookException(
            $field . ' must be between ' . $minimum . ' and ' . $maximum . ' characters.',
            422,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_VALIDATION_FAILED'
        );
    }
    return $text;
}

function mg_mcp_creator_campaign_playbook_idempotency(mixed $value): string
{
    $key = trim((string)$value);
    if (strlen($key) < 8 || strlen($key) > 190 || preg_match('/^[A-Za-z0-9._:-]+$/', $key) !== 1) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'A valid idempotency key is required.',
            422,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_IDEMPOTENCY_INVALID'
        );
    }
    return $key;
}

function mg_mcp_creator_campaign_playbook_json(mixed $value, string $field, int $maximumBytes = 60000): array
{
    if (!is_array($value)) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'Invalid ' . $field . '.',
            422,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_VALIDATION_FAILED'
        );
    }
    try {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new MgMcpCreatorCampaignPlaybookException(
            'Invalid ' . $field . '.',
            422,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_VALIDATION_FAILED'
        );
    }
    if (strlen($json) > $maximumBytes) {
        throw new MgMcpCreatorCampaignPlaybookException(
            ucfirst($field) . ' is too large.',
            422,
            'MCP_CREATOR_CAMPAIGN_PLAYBOOK_PAYLOAD_TOO_LARGE'
        );
    }
    return $value;
}

function mg_mcp_creator_campaign_playbook_bool(mixed $value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (in_array($value, [1, '1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($value, [0, '0', 'false', 'no', 'off'], true)) {
        return false;
    }
    throw new MgMcpCreatorCampaignPlaybookException(
        'Invalid boolean value.',
        422,
        'MCP_CREATOR_CAMPAIGN_PLAYBOOK_VALIDATION_FAILED'
    );
}
