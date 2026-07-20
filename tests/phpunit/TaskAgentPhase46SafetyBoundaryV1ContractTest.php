<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase46SafetyBoundaryV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void{$this->root=dirname(__DIR__,2);}

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path.' must be readable.');
        return $source;
    }

    public function testRecurringProgramsRemainDraftPlanOnlyAndApprovalFirst(): void
    {
        $schema=$this->source('database/20260714_personal_gifting_workflows_phase3.sql');
        $service=$this->source('includes/task-agent-recurring-programs.php');
        $router=$this->source('includes/task-agent-recurring-programs-router.php');
        foreach([
            "generation_mode ENUM('draft_plan_only')",
            "'generation_mode'=>'draft_plan_only'",
            "'approval_required'=>true",
            "'commerce_executed'=>false",
            'No product, cart, charge, message, or delivery will be created.',
        ] as $marker)self::assertStringContainsString($marker,$schema."\n".$service."\n".$router);
        foreach(['capture_payment','payment_method','commerce_checkout','send_gift','issue_reward','claim_code','redemption_code'] as $forbidden)self::assertStringNotContainsString($forbidden,$service);
    }

    public function testGroupGiftsRemainPledgeOnlyAndCollectNoMoney(): void
    {
        $schema=$this->source('database/20260714_personal_gifting_workflows_phase3.sql');
        $service=$this->source('includes/task-agent-group-gifts.php');
        $api=$this->source('api/agents/runtime.php');
        foreach([
            "contribution_mode ENUM('pledge_only')",
            "'pledge_only'",
            "'money_collected'=>false",
            'Pledges are commitments only; no money is collected by this card.',
        ] as $marker)self::assertStringContainsString($marker,$schema."\n".$service);
        foreach([
            "action==='respond_group_invite'",
            "action==='record_external_pledge'",
            "action==='collect_group_payment'",
            "action==='charge_group_gift'",
            "action==='group_checkout'",
        ] as $forbidden)self::assertStringNotContainsString($forbidden,$api);
    }

    public function testDistributionProgramMutationsRemainInCanonicalMerchantWorkspace(): void
    {
        $service=$this->source('includes/task-agent-program-coordination.php');
        $router=$this->source('includes/task-agent-program-coordination-router.php');
        $api=$this->source('api/agents/runtime.php');
        foreach([
            'Program creation stays in the canonical merchant distribution workspace',
            'recipient eligibility, product assignment, allocation, issuance, and status mutations remain',
            '/merchant-distribution-program.php',
        ] as $marker)self::assertStringContainsString($marker,$router."\n".$service);
        foreach([
            'INSERT INTO distribution_programs','UPDATE distribution_programs',
            'INSERT INTO distribution_recipients','UPDATE distribution_recipients',
            'INSERT INTO distribution_allocations','UPDATE distribution_allocations',
            'INSERT INTO distribution_issuance_jobs','UPDATE distribution_issuance_jobs',
        ] as $mutation)self::assertStringNotContainsString($mutation,$service);
        foreach([
            'create_distribution_recipient','approve_distribution_recipient','select_distribution_winner',
            'allocate_distribution_reward','issue_distribution_reward','process_distribution_issuance','distribution_program_status',
        ] as $action)self::assertStringNotContainsString("action==='{$action}'",$api);
    }

    public function testRulesBudgetsStrategiesAndApprovalsRemainReadOnly(): void
    {
        $service=$this->source('includes/task-agent-policy-approvals.php');
        $router=$this->source('includes/task-agent-policy-approvals-router.php');
        $api=$this->source('api/agents/runtime.php');
        foreach([
            '/merchant-distribution-program.php','/merchant-automation.php','/merchant-agent-approvals.php',
            'Approval decisions remain in the canonical Agent Approval Queue',
            'Strategy creation and editing remain in Merchant Automation',
            'Budget, item, per-recipient, and rule changes remain in the canonical Distribution Program editor',
        ] as $handoff)self::assertStringContainsString($handoff,$router);
        foreach([
            'UPDATE distribution_programs','INSERT INTO distribution_programs',
            'UPDATE agent_strategies','INSERT INTO agent_strategies',
            'UPDATE agent_approval_requests','INSERT INTO agent_approval_requests',
            'UPDATE agent_workflow_actions','INSERT INTO agent_workflow_actions',
        ] as $mutation)self::assertStringNotContainsString($mutation,$service."\n".$router);
        foreach([
            'approve_agent_action','reject_agent_action','bulk_approve','create_agent_strategy','update_agent_strategy',
            'activate_agent_strategy','pause_agent_strategy','update_program_budget','update_program_rules',
        ] as $action)self::assertStringNotContainsString("action==='{$action}'",$api);
    }

    public function testMonitoringIsOnDemandReadOnlyAndPersistsNoAlertFeed(): void
    {
        $monitor=$this->source('includes/task-agent-monitoring.php');
        $router=$this->source('includes/task-agent-monitoring-router.php');
        $script=$this->source('assets/js/task-agent-monitoring-runtime.js');
        foreach(["'stored_alert'=>false","'stored_alerts'=>false",'Calculated on demand from canonical records','No alert row or automated action was created'] as $marker)self::assertStringContainsString($marker,$monitor."\n".$router."\n".$script);
        foreach([
            'INSERT INTO','UPDATE user_recurring_gift_programs','UPDATE user_group_gifts',
            'UPDATE user_gifting_schedules','UPDATE distribution_programs','UPDATE distribution_allocations',
            'UPDATE agent_approval_requests','DELETE FROM',
        ] as $mutation)self::assertStringNotContainsString($mutation,$monitor."\n".$router);
        self::assertStringNotContainsString('fetch(',$script);
        self::assertStringNotContainsString("method:'POST'",$script);
    }

    public function testRecipientReadinessNeverProjectsPrivateAddressValues(): void
    {
        $monitor=$this->source('includes/task-agent-monitoring.php');
        foreach([
            "empty(\$readiness['recipient_identified'])",
            "empty(\$readiness['delivery_address_available'])",
            "empty(\$readiness['gift_preferences_available'])",
            "'address_value_exposed'=>false",
        ] as $marker)self::assertStringContainsString($marker,$monitor);
        foreach(["['address_line_1']","['address_line_2']","['city']","['state_region']","['postal_code']"] as $field)self::assertStringNotContainsString($field,$monitor);
    }

    public function testNoAutonomousCommerceApprovalOrMonitoringActionsExist(): void
    {
        $api=$this->source('api/agents/runtime.php');
        foreach([
            'checkout','purchase','charge','send_gift','auto_send_gift','auto_purchase',
            'auto_allocate','auto_issue_reward','auto_approve','monitor_decide_approval',
            'create_monitor_alert','update_monitor_alert','dismiss_monitor_alert','schedule_monitoring',
        ] as $action)self::assertStringNotContainsString("action==='{$action}'",$api);
    }

    public function testRoutinePhaseFourServicesAndRoutersHaveNoAiProviderCalls(): void
    {
        $files=[
            'includes/task-agent-recurring-programs.php','includes/task-agent-recurring-programs-router.php',
            'includes/task-agent-group-gifts.php','includes/task-agent-group-gifts-router.php',
            'includes/task-agent-program-coordination.php','includes/task-agent-program-coordination-router.php',
            'includes/task-agent-policy-approvals.php','includes/task-agent-policy-approvals-router.php',
            'includes/task-agent-monitoring.php','includes/task-agent-monitoring-router.php',
        ];
        $combined='';foreach($files as $file)$combined.="\n".$this->source($file);
        self::assertDoesNotMatchRegularExpression('/\bmg_anthropic_messages\s*\(/',$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_ai_credit_consume\s*\(/',$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_openai[A-Za-z0-9_]*\s*\(/',$combined);
        foreach(["'response_source'=>'system_query'","'used_ai'=>false","'ai_tokens_total'=>0"] as $marker)self::assertStringContainsString($marker,$combined);
    }

    public function testCompactModelContextsExcludeClaimsRedemptionPaymentsAndRawPayloads(): void
    {
        $program=$this->source('includes/task-agent-program-coordination.php');
        $policy=$this->source('includes/task-agent-policy-approvals.php');
        $monitor=$this->source('includes/task-agent-monitoring.php');
        $programModel=substr($program,strpos($program,'function mg_task_agent_program_for_model'),strpos($program,'function mg_task_agent_program_card')-strpos($program,'function mg_task_agent_program_for_model'));
        $policyModel=substr($policy,strpos($policy,'function mg_task_agent_policy_for_model'),strpos($policy,'function mg_task_agent_policy_append_context')-strpos($policy,'function mg_task_agent_policy_for_model'));
        $monitorModel=substr($monitor,strpos($monitor,'function mg_task_agent_monitor_for_model'),strpos($monitor,'function mg_task_agent_monitor_card')-strpos($monitor,'function mg_task_agent_monitor_for_model'));
        foreach([$programModel,$policyModel,$monitorModel] as $model){
            foreach(['claim_code','redemption_code','payment_method','request_json','input_json','failure_message','idempotency_key'] as $private)self::assertStringNotContainsString($private,$model);
        }
        foreach(['title','body','url','public_id','owner_user_id','agent_id','requested_reason','decision_reason','target_reference'] as $private)self::assertStringNotContainsString($private,$monitorModel);
    }

    public function testReleaseManifestAndRunbookStateEverySafetyBoundary(): void
    {
        $release=require $this->root.'/config/task_agent_phase4_release.php';
        $runbook=$this->source('docs/TASK_AGENT_PHASE4_PRODUCTION_RUNBOOK.md');
        self::assertCount(10,$release['release_boundaries']);
        foreach($release['release_boundaries'] as $boundary)self::assertStringNotContainsString('automatic_',str_replace('no_automatic_','',$boundary));
        foreach([
            'does not create a second recurring, group-gifting, distribution, approval, or alert system',
            'do not autonomously purchase, charge, send, message, allocate, select winners, issue rewards, approve actions, claim, redeem, refund, or expose private recipient address values',
            'The Phase 4.5 monitor intentionally persists no alert rows',
        ] as $statement)self::assertStringContainsString($statement,$runbook);
    }
}
