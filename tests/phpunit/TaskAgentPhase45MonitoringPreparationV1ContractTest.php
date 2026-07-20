<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase45MonitoringPreparationV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void{$this->root=dirname(__DIR__,2);}

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path.' must be readable.');
        return $source;
    }

    public function testPhase45CreatesNoDuplicateAlertScheduleOrEventTable(): void
    {
        $phase4=$this->source('database/20260720_task_agent_phase4_v1.sql');
        foreach([
            'CREATE TABLE IF NOT EXISTS task_agent_alerts',
            'CREATE TABLE IF NOT EXISTS task_agent_monitoring',
            'CREATE TABLE IF NOT EXISTS task_agent_schedules',
            'CREATE TABLE IF NOT EXISTS task_agent_events',
            'CREATE TABLE IF NOT EXISTS multi_agent_alerts',
            'CREATE TABLE IF NOT EXISTS multi_agent_monitoring',
        ] as $duplicate)self::assertStringNotContainsString($duplicate,$phase4);
    }

    public function testMonitoringReusesEveryCanonicalPhaseAuthority(): void
    {
        $monitor=$this->source('includes/task-agent-monitoring.php');
        foreach([
            'mg_task_agent_recurring_programs',
            'mg_task_agent_group_gifts',
            'mg_task_agent_delivery_preparations',
            'mg_task_agent_program_rows',
            'mg_task_agent_pending_approvals',
        ] as $authority)self::assertStringContainsString($authority,$monitor);
        foreach([
            'mg_task_agent_recurring_schema_ready',
            'mg_task_agent_group_schema_ready',
            'mg_task_agent_delivery_schema_ready',
            'mg_task_agent_program_schema_ready',
            'mg_task_agent_policy_schema_ready',
        ] as $guard)self::assertStringContainsString($guard,$monitor);
    }

    public function testMonitoringCoversDueCyclesHistoryDeadlinesReadinessCapacityAndApprovals(): void
    {
        $monitor=$this->source('includes/task-agent-monitoring.php');
        foreach([
            "['skipped','completed','draft_created','generated']",
            "['open','locked']",
            'progress_percent',
            'delivery_address_available',
            'gift_preferences_available',
            'address_value_exposed',
            'remaining_budget',
            'max_items',
            'product_count',
            'recipient_count',
            'mg_task_agent_pending_approvals',
            "['high','critical']",
        ] as $marker)self::assertStringContainsString($marker,$monitor);
    }

    public function testMonitoringIsReadOnlyAndDoesNotPersistAlertsOrMutateCanonicalRecords(): void
    {
        $monitor=$this->source('includes/task-agent-monitoring.php');
        $router=$this->source('includes/task-agent-monitoring-router.php');
        $combined=$monitor."\n".$router;
        foreach([
            'INSERT INTO','UPDATE user_recurring_gift_programs','UPDATE user_recurring_gift_runs',
            'UPDATE user_group_gifts','UPDATE user_group_gift_participants',
            'UPDATE user_gifting_schedules','UPDATE user_recipient_data_requests',
            'UPDATE distribution_programs','UPDATE distribution_recipients','UPDATE distribution_allocations',
            'UPDATE agent_approval_requests','UPDATE agent_workflow_actions','DELETE FROM',
        ] as $mutation)self::assertStringNotContainsString($mutation,$combined);
        self::assertStringContainsString("'stored_alert'=>false",$monitor);
        self::assertStringContainsString("'stored_alerts'=>false",$monitor);
        self::assertStringContainsString('no alert row, purchase, message, allocation, issuance, or approval decision was created',$router);
    }

    public function testMonitoringModelContextIsCappedAggregateAndPrivate(): void
    {
        $monitor=$this->source('includes/task-agent-monitoring.php');
        $start=strpos($monitor,'function mg_task_agent_monitor_for_model');
        $end=strpos($monitor,'function mg_task_agent_monitor_card',$start);
        self::assertNotFalse($start);self::assertNotFalse($end);
        $model=substr($monitor,$start,$end-$start);
        self::assertStringContainsString('array_slice($snapshot[\'items\']??[],0,16)',$model);
        foreach(['counts','source','severity','due_at','status','facts','stored_alert','system_query','used_ai'] as $safe)self::assertStringContainsString($safe,$model);
        foreach([
            'title','body','url','public_id','owner_user_id','agent_id','program_id','group_id','plan_id',
            'recipient_name','display_name','contact_name','address_line','postal_code','email','phone',
            'requested_reason','decision_reason','target_reference','request_json','rules_json','policy_json',
            'claim_code','redemption_code','payment','idempotency_key',
        ] as $private)self::assertStringNotContainsString($private,$model);
    }

    public function testRecipientMonitoringUsesReadinessBooleansWithoutAddressValues(): void
    {
        $monitor=$this->source('includes/task-agent-monitoring.php');
        foreach([
            "empty(\$readiness['recipient_identified'])",
            "empty(\$readiness['delivery_address_available'])",
            "empty(\$readiness['gift_preferences_available'])",
            "'address_value_exposed'=>false",
            'Only readiness booleans are shown; no address value is exposed.',
        ] as $marker)self::assertStringContainsString($marker,$monitor);
        foreach([
            "['address_line_1']","['address_line_2']","['city']","['state_region']","['postal_code']",
        ] as $fieldAccess)self::assertStringNotContainsString($fieldAccess,$monitor);
    }

    public function testMonitoringRoutesBeforeGeneralAiForBirthdayAndMerchantAgents(): void
    {
        $recurring=$this->source('includes/task-agent-recurring-programs-router.php');
        $program=$this->source('includes/task-agent-program-coordination-router.php');
        $api=$this->source('api/agents/runtime.php');
        foreach([
            "return 'monitor'",
            'mg_task_agent_monitor_snapshot',
            'mg_task_agent_monitor_route',
            "'task_agent_monitor'",
        ] as $marker)self::assertStringContainsString($marker,$recurring."\n".$program);
        $recurringPos=strpos($api,'mg_task_agent_recurring_chat');
        $programPos=strpos($api,'mg_task_agent_program_chat');
        $generalPos=strpos($api,'mg_multi_agent_runtime_chat');
        self::assertNotFalse($recurringPos);self::assertNotFalse($programPos);self::assertNotFalse($generalPos);
        self::assertLessThan($generalPos,$recurringPos);
        self::assertLessThan($generalPos,$programPos);
    }

    public function testMonitoringUsesZeroAiAndNoProviderCalls(): void
    {
        $monitor=$this->source('includes/task-agent-monitoring.php');
        $router=$this->source('includes/task-agent-monitoring-router.php');
        $recurring=$this->source('includes/task-agent-recurring-programs-router.php');
        $program=$this->source('includes/task-agent-program-coordination-router.php');
        $combined=$monitor."\n".$router."\n".$recurring."\n".$program;
        foreach(["'source'=>'system_query'","'used_ai'=>false","'ai_tokens_total'=>0","'tool'=>'task_agent_monitor'"] as $marker)self::assertStringContainsString($marker,$combined);
        self::assertDoesNotMatchRegularExpression('/\bmg_anthropic_messages\s*\(/',$monitor."\n".$router);
        self::assertDoesNotMatchRegularExpression('/\bmg_ai_credit_consume\s*\(/',$monitor."\n".$router);
        self::assertDoesNotMatchRegularExpression('/\bmg_openai[A-Za-z0-9_]*\s*\(/',$monitor."\n".$router);
    }

    public function testCanvasCardsAreReadOnlyLinksAndAssetsAreLoaded(): void
    {
        $script=$this->source('assets/js/task-agent-monitoring-runtime.js');
        $page=$this->source('agent.php');
        foreach([
            'task_agent_monitor','task_agent_monitor_summary','Calculated on demand from canonical records',
            'No alert row or automated action was created','Read-only review','Zero AI credits',
            'No purchase · No message · No allocation · No issuance · No approval decision',
        ] as $marker)self::assertStringContainsString($marker,$script);
        self::assertStringNotContainsString('fetch(',$script);
        self::assertStringNotContainsString("method:'POST'",$script);
        self::assertStringContainsString('/assets/js/task-agent-monitoring-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-monitoring-v1.css?v=1.0.0',$page);
    }

    public function testMonitoringDoesNotAddRuntimeMutationActions(): void
    {
        $api=$this->source('api/agents/runtime.php');
        foreach([
            'create_monitor_alert','update_monitor_alert','dismiss_monitor_alert','schedule_monitoring',
            'auto_generate_recurring_draft','auto_send_gift','auto_purchase','auto_allocate',
            'auto_issue_reward','auto_approve','monitor_decide_approval',
        ] as $action)self::assertStringNotContainsString("action==='{$action}'",$api);
    }
}
