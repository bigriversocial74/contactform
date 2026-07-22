<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app.php';

final class MgMcpAutomationGrantException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly string $errorCode = 'MCP_AUTOMATION_GRANT_INVALID'
    ) { parent::__construct($message); }
    public function httpStatus(): int { return $this->httpStatus; }
    public function errorCode(): string { return $this->errorCode; }
}

const MG_MCP_AUTOMATION_GRANT_STATUSES = ['draft', 'active', 'paused', 'expired', 'revoked'];
const MG_MCP_AUTOMATION_GRANT_OPERATION_CLASSES = ['read', 'monitor', 'recommend', 'task', 'draft', 'approval_gated'];
const MG_MCP_AUTOMATION_GRANT_RISK_LEVELS = ['low', 'medium', 'high', 'critical'];

function mg_mcp_automation_operation_rank(string $operationClass): int
{
    return match ($operationClass) {
        'read' => 10, 'monitor' => 20, 'recommend' => 30, 'task' => 40, 'draft' => 50,
        'approval_gated' => 60, 'bounded_auto' => 70, 'prohibited' => 1000, default => 1000,
    };
}

function mg_mcp_automation_playbook_catalog(): array
{
    return [
        'catalog_research' => [
            'label'=>'Catalog research','description'=>'Read account context and published catalog items for owner-directed research.','operation_class'=>'read','workspace_required'=>false,
            'tools'=>['microgifter.account.get_connection_context'=>'profile:read','microgifter.catalog.search'=>'catalog:read','microgifter.catalog.get_item'=>'catalog:read'],
        ],
        'gift_draft_preparation' => [
            'label'=>'Gift draft preparation','description'=>'Prepare reviewable gift drafts. No purchase, issuance, delivery, or charge.','operation_class'=>'draft','workspace_required'=>false,
            'tools'=>['microgifter.catalog.search'=>'catalog:read','microgifter.catalog.get_item'=>'catalog:read','microgifter.gift.create_draft'=>'gift:draft'],
        ],
        'campaign_draft_preparation' => [
            'label'=>'Campaign draft preparation','description'=>'Prepare reviewable merchant campaign drafts without publication, scheduling, or spend.','operation_class'=>'draft','workspace_required'=>true,
            'tools'=>['microgifter.campaign.create_draft'=>'campaign:draft'],
        ],
        'reward_draft_preparation' => [
            'label'=>'Reward draft preparation','description'=>'Prepare reviewable reward drafts without activation, issuance, or fulfillment.','operation_class'=>'draft','workspace_required'=>true,
            'tools'=>['microgifter.reward.create_draft'=>'reward:draft'],
        ],
        'message_draft_preparation' => [
            'label'=>'Message draft preparation','description'=>'Prepare reviewable message drafts without sending or scheduling.','operation_class'=>'draft','workspace_required'=>true,
            'tools'=>['microgifter.message.create_draft'=>'message:draft'],
        ],
        'creator_campaign_lifecycle_actions' => [
            'label'=>'Creator Campaign lifecycle actions','description'=>'Request publication, scheduling, pause, resume, completion, or cancellation. Every request requires a separate owner approval and manual execution.','operation_class'=>'approval_gated','workspace_required'=>true,
            'tools'=>[
                'microgifter.creator_campaigns.publish'=>'creator_campaigns:publish','microgifter.creator_campaigns.schedule'=>'creator_campaigns:publish',
                'microgifter.creator_campaigns.pause'=>'creator_campaigns:publish','microgifter.creator_campaigns.resume'=>'creator_campaigns:publish',
                'microgifter.creator_campaigns.complete'=>'creator_campaigns:publish','microgifter.creator_campaigns.cancel'=>'creator_campaigns:publish',
            ],
        ],
        'creator_campaign_participant_actions' => [
            'label'=>'Creator Campaign participant actions','description'=>'Request application decisions, invitations, agreement offers, and participant controls with per-action owner approval.','operation_class'=>'approval_gated','workspace_required'=>true,
            'tools'=>[
                'microgifter.creator_campaigns.application.approve'=>'creator_campaign_participants:manage','microgifter.creator_campaigns.application.decline'=>'creator_campaign_participants:manage',
                'microgifter.creator_campaigns.invitation.send'=>'creator_campaign_participants:manage','microgifter.creator_campaigns.agreement.offer'=>'creator_campaign_agreements:manage',
                'microgifter.creator_campaigns.participant.suspend'=>'creator_campaign_participants:manage','microgifter.creator_campaigns.participant.remove'=>'creator_campaign_participants:manage',
            ],
        ],
        'creator_campaign_review_actions' => [
            'label'=>'Creator Campaign review actions','description'=>'Request submission decisions and attribution overrides with per-action owner approval.','operation_class'=>'approval_gated','workspace_required'=>true,
            'tools'=>[
                'microgifter.creator_campaigns.submission.approve'=>'creator_campaign_submissions:review','microgifter.creator_campaigns.submission.request_revision'=>'creator_campaign_submissions:review',
                'microgifter.creator_campaigns.submission.reject'=>'creator_campaign_submissions:review','microgifter.creator_campaigns.attribution.override'=>'creator_campaign_attribution:manage',
            ],
        ],
        'creator_campaign_financial_actions' => [
            'label'=>'Creator Campaign financial actions','description'=>'Request earning decisions, internal payout records, and dispute resolutions. Critical actions require explicit owner approval and manual execution; no payment provider is called.','operation_class'=>'approval_gated','workspace_required'=>true,
            'tools'=>[
                'microgifter.creator_campaigns.earning.approve'=>'creator_campaign_earnings:manage','microgifter.creator_campaigns.earning.hold'=>'creator_campaign_earnings:manage',
                'microgifter.creator_campaigns.earning.reject'=>'creator_campaign_earnings:manage','microgifter.creator_campaigns.earning.reverse'=>'creator_campaign_earnings:manage',
                'microgifter.creator_campaigns.payout.record'=>'creator_campaign_payouts:manage','microgifter.creator_campaigns.dispute.resolve'=>'creator_campaign_disputes:manage',
            ],
        ],
    ];
}

function mg_mcp_automation_schema_ready(PDO $pdo): bool
{
    foreach (['mcp_connections','mcp_connection_scopes','mcp_automation_grants','mcp_automations','mcp_automation_triggers','mcp_automation_runs','mcp_automation_actions','mcp_action_receipts','mcp_security_events'] as $table) {
        $stmt=$pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');$stmt->execute([$table]);if(!$stmt->fetchColumn())return false;
    }
    return true;
}

function mg_mcp_automation_json_list(mixed $value): array
{
    if(is_array($value))return array_values(array_unique(array_filter(array_map('strval',$value),static fn(string $item):bool=>$item!=='')));
    if(!is_string($value)||trim($value)==='')return [];
    try{$decoded=json_decode($value,true,32,JSON_THROW_ON_ERROR);}catch(Throwable){return [];}
    return is_array($decoded)?array_values(array_unique(array_filter(array_map('strval',$decoded),static fn(string $item):bool=>$item!==''))):[];
}

function mg_mcp_automation_json_object(mixed $value): array
{
    if(is_array($value))return $value;if(!is_string($value)||trim($value)==='')return [];
    try{$decoded=json_decode($value,true,32,JSON_THROW_ON_ERROR);}catch(Throwable){return [];}
    return is_array($decoded)?$decoded:[];
}
