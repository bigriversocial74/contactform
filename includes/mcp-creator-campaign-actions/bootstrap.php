<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app.php';
require_once dirname(__DIR__) . '/creator-campaigns.php';
require_once dirname(__DIR__) . '/mcp-automations.php';

final class MgMcpCreatorCampaignActionException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly string $errorCode = 'MCP_CREATOR_CAMPAIGN_ACTION_INVALID'
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int { return $this->httpStatus; }
    public function errorCode(): string { return $this->errorCode; }
}

const MG_MCP_CREATOR_CAMPAIGN_ACTION_STATUSES = [
    'waiting_for_approval','approved','rejected','executing','succeeded','failed','cancelled','expired',
];

function mg_mcp_creator_campaign_action_catalog(): array
{
    return [
        'microgifter.creator_campaigns.publish' => ['scope'=>'creator_campaigns:publish','risk'=>'high','native_service'=>'creator_campaign.status','native_action'=>'active'],
        'microgifter.creator_campaigns.schedule' => ['scope'=>'creator_campaigns:publish','risk'=>'high','native_service'=>'creator_campaign.status','native_action'=>'scheduled'],
        'microgifter.creator_campaigns.pause' => ['scope'=>'creator_campaigns:publish','risk'=>'high','native_service'=>'creator_campaign.status','native_action'=>'paused'],
        'microgifter.creator_campaigns.resume' => ['scope'=>'creator_campaigns:publish','risk'=>'high','native_service'=>'creator_campaign.status','native_action'=>'active'],
        'microgifter.creator_campaigns.complete' => ['scope'=>'creator_campaigns:publish','risk'=>'high','native_service'=>'creator_campaign.status','native_action'=>'completed'],
        'microgifter.creator_campaigns.cancel' => ['scope'=>'creator_campaigns:publish','risk'=>'critical','native_service'=>'creator_campaign.status','native_action'=>'cancelled'],
        'microgifter.creator_campaigns.application.approve' => ['scope'=>'creator_campaign_participants:manage','risk'=>'high','native_service'=>'creator_campaign.application','native_action'=>'approve'],
        'microgifter.creator_campaigns.application.decline' => ['scope'=>'creator_campaign_participants:manage','risk'=>'high','native_service'=>'creator_campaign.application','native_action'=>'decline'],
        'microgifter.creator_campaigns.invitation.send' => ['scope'=>'creator_campaign_participants:manage','risk'=>'high','native_service'=>'creator_campaign.invitation','native_action'=>'send'],
        'microgifter.creator_campaigns.agreement.offer' => ['scope'=>'creator_campaign_agreements:manage','risk'=>'critical','native_service'=>'creator_campaign.agreement','native_action'=>'offer'],
        'microgifter.creator_campaigns.participant.suspend' => ['scope'=>'creator_campaign_participants:manage','risk'=>'high','native_service'=>'creator_campaign.participant','native_action'=>'suspended'],
        'microgifter.creator_campaigns.participant.remove' => ['scope'=>'creator_campaign_participants:manage','risk'=>'critical','native_service'=>'creator_campaign.participant','native_action'=>'removed'],
        'microgifter.creator_campaigns.submission.approve' => ['scope'=>'creator_campaign_submissions:review','risk'=>'high','native_service'=>'creator_campaign.submission','native_action'=>'approved'],
        'microgifter.creator_campaigns.submission.request_revision' => ['scope'=>'creator_campaign_submissions:review','risk'=>'high','native_service'=>'creator_campaign.submission','native_action'=>'revision_requested'],
        'microgifter.creator_campaigns.submission.reject' => ['scope'=>'creator_campaign_submissions:review','risk'=>'high','native_service'=>'creator_campaign.submission','native_action'=>'rejected'],
        'microgifter.creator_campaigns.attribution.override' => ['scope'=>'creator_campaign_attribution:manage','risk'=>'critical','native_service'=>'creator_campaign.attribution','native_action'=>'override'],
        'microgifter.creator_campaigns.earning.approve' => ['scope'=>'creator_campaign_earnings:manage','risk'=>'high','native_service'=>'creator_campaign.earning','native_action'=>'approved'],
        'microgifter.creator_campaigns.earning.hold' => ['scope'=>'creator_campaign_earnings:manage','risk'=>'high','native_service'=>'creator_campaign.earning','native_action'=>'held'],
        'microgifter.creator_campaigns.earning.reject' => ['scope'=>'creator_campaign_earnings:manage','risk'=>'critical','native_service'=>'creator_campaign.earning','native_action'=>'rejected'],
        'microgifter.creator_campaigns.earning.reverse' => ['scope'=>'creator_campaign_earnings:manage','risk'=>'critical','native_service'=>'creator_campaign.earning','native_action'=>'reversed'],
        'microgifter.creator_campaigns.payout.record' => ['scope'=>'creator_campaign_payouts:manage','risk'=>'critical','native_service'=>'creator_campaign.payout','native_action'=>'record'],
        'microgifter.creator_campaigns.dispute.resolve' => ['scope'=>'creator_campaign_disputes:manage','risk'=>'critical','native_service'=>'creator_campaign.dispute','native_action'=>'resolve'],
    ];
}

function mg_mcp_creator_campaign_action_contract(string $toolName): array
{
    $contract = mg_mcp_creator_campaign_action_catalog()[$toolName] ?? null;
    if (!is_array($contract)) {
        throw new MgMcpCreatorCampaignActionException('Creator Campaign action is not supported.', 422, 'MCP_CREATOR_CAMPAIGN_ACTION_UNSUPPORTED');
    }
    return $contract;
}

function mg_mcp_creator_campaign_action_schema_ready(PDO $pdo): bool
{
    foreach (['mcp_automation_actions','mcp_automation_runs','mcp_action_receipts','mcp_creator_campaign_action_approvals','creator_campaign_earning_reviews'] as $table) {
        $stmt=$pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
        $stmt->execute([$table]);
        if (!$stmt->fetchColumn()) return false;
    }
    return true;
}

function mg_mcp_creator_campaign_action_text(mixed $value,int $max,string $label,bool $required=true): string
{
    $text=trim((string)$value);
    if (($required && $text==='') || mb_strlen($text)>$max) {
        throw new MgMcpCreatorCampaignActionException('Invalid '.$label.'.',422,'MCP_CREATOR_CAMPAIGN_ACTION_VALIDATION_FAILED');
    }
    return $text;
}

function mg_mcp_creator_campaign_action_public_id(mixed $value,string $label,bool $required=true): string
{
    $id=mg_mcp_creator_campaign_action_text($value,80,$label,$required);
    if ($id!=='' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,79}$/',$id)!==1) {
        throw new MgMcpCreatorCampaignActionException('Invalid '.$label.'.',422,'MCP_CREATOR_CAMPAIGN_ACTION_VALIDATION_FAILED');
    }
    return $id;
}

function mg_mcp_creator_campaign_action_uuid(mixed $value,string $label): string
{
    $id=trim((string)$value);
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$id)!==1) {
        throw new MgMcpCreatorCampaignActionException('Invalid '.$label.'.',422,'MCP_CREATOR_CAMPAIGN_ACTION_VALIDATION_FAILED');
    }
    return $id;
}

function mg_mcp_creator_campaign_action_json(mixed $value,string $label,int $maxBytes=60000): array
{
    if (!is_array($value)) throw new MgMcpCreatorCampaignActionException('Invalid '.$label.'.');
    $json=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if (strlen($json)>$maxBytes) throw new MgMcpCreatorCampaignActionException(ucfirst($label).' is too large.',413,'MCP_CREATOR_CAMPAIGN_ACTION_TOO_LARGE');
    return $value;
}

function mg_mcp_creator_campaign_action_fingerprint(array $value): string
{
    return hash('sha256',json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}

function mg_mcp_creator_campaign_action_state_token(string $type,string $publicId,int $lockVersion,string $status): string
{
    return hash('sha256',$type.'|'.$publicId.'|'.$lockVersion.'|'.$status);
}

function mg_mcp_creator_campaign_action_risk_rank(string $risk): int
{
    return ['low'=>10,'medium'=>20,'high'=>30,'critical'=>40][$risk]??1000;
}
