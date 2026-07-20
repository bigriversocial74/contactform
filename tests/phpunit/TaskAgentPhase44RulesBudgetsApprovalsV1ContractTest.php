<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase44RulesBudgetsApprovalsV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void{$this->root=dirname(__DIR__,2);}

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path.' must be readable.');
        return $source;
    }

    public function testCanonicalRulesBudgetsStrategiesAndApprovalsAreReused(): void
    {
        $distribution=$this->source('database/stage_4e_distribution_external_inputs.sql');
        $execution=$this->source('database/stage_16_agent_execution_orchestration.sql');
        $service=$this->source('includes/task-agent-policy-approvals.php');
        foreach(['budget_cents','max_items','per_recipient_limit','rules_json'] as $marker)self::assertStringContainsString($marker,$distribution);
        foreach(['agent_strategies','agent_workflow_runs','agent_workflow_actions','agent_approval_requests'] as $table){
            self::assertStringContainsString($table,$execution);
            self::assertStringContainsString($table,$service);
        }
    }

    public function testPhase4DoesNotCreateDuplicateRuleOrApprovalTables(): void
    {
        $phase4=$this->source('database/20260720_task_agent_phase4_v1.sql');
        foreach([
            'CREATE TABLE IF NOT EXISTS task_agent_rules','CREATE TABLE IF NOT EXISTS task_agent_budgets',
            'CREATE TABLE IF NOT EXISTS task_agent_strategies','CREATE TABLE IF NOT EXISTS task_agent_approvals',
            'CREATE TABLE IF NOT EXISTS multi_agent_strategy','CREATE TABLE IF NOT EXISTS multi_agent_approval',
        ] as $duplicate)self::assertStringNotContainsString($duplicate,$phase4);
    }

    public function testProgramGuardrailsAreMerchantOwnerAgentAndTypeScoped(): void
    {
        $service=$this->source('includes/task-agent-policy-approvals.php');
        foreach([
            'dp.merchant_user_id=link.owner_user_id','link.owner_user_id=? AND link.agent_id=?',
            'dp.program_type IN','owner_user_id=? AND agent_id=?',
            "ar.owner_user_id=? AND wr.agent_id=? AND ar.status='pending'",
        ] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testSpecializedAgentCannotMutateCanonicalPoliciesOrDecisions(): void
    {
        $service=$this->source('includes/task-agent-policy-approvals.php');
        $router=$this->source('includes/task-agent-policy-approvals-router.php');
        $api=$this->source('api/agents/runtime.php');
        $combined=$service."\n".$router;
        foreach([
            'UPDATE distribution_programs','INSERT INTO distribution_programs','UPDATE agent_strategies','INSERT INTO agent_strategies',
            'UPDATE agent_approval_requests','INSERT INTO agent_approval_requests','UPDATE agent_workflow_actions','INSERT INTO agent_workflow_actions',
        ] as $mutation)self::assertStringNotContainsString($mutation,$combined);
        foreach([
            'approve_agent_action','reject_agent_action','decide_agent_approval','bulk_approve',
            'create_agent_strategy','update_agent_strategy','activate_agent_strategy','pause_agent_strategy','retire_agent_strategy',
            'update_program_budget','update_program_rules','update_recipient_limit',
        ] as $action)self::assertStringNotContainsString("action==='{$action}'",$api);
    }

    public function testAllSensitiveChangesHandoffToCanonicalCenters(): void
    {
        $router=$this->source('includes/task-agent-policy-approvals-router.php');
        $script=$this->source('assets/js/task-agent-policy-approvals-runtime.js');
        foreach([
            '/merchant-automation.php','/merchant-agent-approvals.php','/merchant-distribution-program.php',
            'Approval decisions remain in the canonical Agent Approval Queue',
            'Strategy creation and editing remain in Merchant Automation',
            'Budget, item, per-recipient, and rule changes remain in the canonical Distribution Program editor',
            'The specialized agent cannot decide, bulk approve, or execute this action',
        ] as $marker)self::assertStringContainsString($marker,$router."\n".$script);
    }

    public function testCanonicalApprovalControlsRemainExplicitAndOwnerIsolated(): void
    {
        $workflow=$this->source('api/agents/_workflow.php');
        $approvalApi=$this->source('api/agents/approvals.php');
        foreach([
            "ar.public_id=? AND ar.owner_user_id=?",
            "!in_array(\$decision,['approve','reject'],true)",
            "in_array((string)\$approval['risk_level'],['high','critical'],true)&&\$reason===''",
            'Approval decision conflicts with the recorded decision',
            "'bulk_approval_enabled'=>false",
            "'high_risk_reason_required'=>true",
        ] as $marker)self::assertStringContainsString($marker,$workflow."\n".$approvalApi);
    }

    public function testModelContextIsAggregateAndKeyOnly(): void
    {
        $service=$this->source('includes/task-agent-policy-approvals.php');
        $start=strpos($service,'function mg_task_agent_policy_for_model');
        $end=strpos($service,'function mg_task_agent_policy_append_context',$start);
        self::assertNotFalse($start);self::assertNotFalse($end);
        $model=substr($service,$start,$end-$start);
        foreach(['program_guardrails','strategies','approval_summary','remaining_budget','per_recipient_limit','rule_keys','policy_keys','action_types','high_risk','expiring_soon'] as $marker)self::assertStringContainsString($marker,$model);
        foreach(['public_id','owner_user_id','agent_id','target_reference','requested_reason','decision_reason','rules_json','policy_json','trigger_config_json','request_json','input_json','failure_message','idempotency_key'] as $private)self::assertStringNotContainsString($private,$model);
        self::assertStringContainsString('array_slice($guardrails,0,12)',$model);
        self::assertStringContainsString('array_slice($strategies,0,12)',$model);
    }

    public function testPolicyRoutingUsesExistingPreAiProgramInterceptor(): void
    {
        $programRouter=$this->source('includes/task-agent-program-coordination-router.php');
        $api=$this->source('api/agents/runtime.php');
        foreach([
            "require_once __DIR__.'/task-agent-policy-approvals.php'",
            "require_once __DIR__.'/task-agent-policy-approvals-router.php'",
            "if(\$policyIntent!=='')return 'policy'",
            "\$intent==='policy'?mg_task_agent_policy_route",
            "\$tool=\$intent==='policy'?'policy_approvals':'distribution_programs'",
        ] as $marker)self::assertStringContainsString($marker,$programRouter);
        $programPos=strpos($api,'mg_task_agent_program_chat');
        $generalPos=strpos($api,'mg_multi_agent_runtime_chat');
        self::assertNotFalse($programPos);self::assertNotFalse($generalPos);self::assertLessThan($generalPos,$programPos);
    }

    public function testRoutinePolicyWorkIsDeterministicAndZeroAi(): void
    {
        $router=$this->source('includes/task-agent-policy-approvals-router.php');
        $service=$this->source('includes/task-agent-policy-approvals.php');
        foreach(["'response_source'=>'system_query'","'used_ai'=>false","'ai_tokens_total'=>0","'tool'=>'policy_approvals'"] as $marker)self::assertStringContainsString($marker,$router);
        $combined=$router."\n".$service;
        self::assertDoesNotMatchRegularExpression('/\bmg_anthropic_messages\s*\(/',$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_ai_credit_consume\s*\(/',$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_openai[A-Za-z0-9_]*\s*\(/',$combined);
    }

    public function testCanvasIsReadOnlyAndAssetsAreLoaded(): void
    {
        $script=$this->source('assets/js/task-agent-policy-approvals-runtime.js');
        $page=$this->source('agent.php');
        foreach(['program_guardrail','agent_strategy_policy','agent_approval_handoff','policy_handoff','No bulk approval','No AI decision'] as $marker)self::assertStringContainsString($marker,$script);
        self::assertStringNotContainsString("fetch('/api/agents/approvals.php'",$script);
        self::assertStringNotContainsString("method:'POST'",$script);
        self::assertStringContainsString('/assets/js/task-agent-policy-approvals-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-policy-approvals-v1.css?v=1.0.0',$page);
    }
}
