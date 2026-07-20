<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase46ProductionQaV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void{$this->root=dirname(__DIR__,2);}

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path.' must be readable.');
        return $source;
    }

    public function testReleaseManifestAndSingleMigrationOrder(): void
    {
        $release=require $this->root.'/config/task_agent_phase4_release.php';
        $migrations=require $this->root.'/config/migrations.php';
        self::assertSame('task_agent_phase4_v1',$release['release_key']);
        self::assertContains('task_agent_phase3_v1',$release['depends_on']);
        self::assertSame(['20260720_task_agent_phase4_v1.sql'],$release['required_migrations']);
        self::assertSame('20260720_task_agent_phase3_shortlist_v1.sql',$release['migration_after']);
        $ordered=array_values($migrations['ordered_files']);
        $phase3=array_search('20260720_task_agent_phase3_shortlist_v1.sql',$ordered,true);
        $phase4=array_search('20260720_task_agent_phase4_v1.sql',$ordered,true);
        self::assertIsInt($phase3);self::assertIsInt($phase4);
        self::assertSame($phase3+1,$phase4,'The single Phase 4 migration must immediately follow the Phase 3 shortlist migration.');
        $sql=$this->source('database/20260720_task_agent_phase4_v1.sql');
        self::assertStringContainsString("VALUES ('20260720_task_agent_phase4_v1'",$sql);
        self::assertSame(1,substr_count($sql,'INSERT INTO schema_migrations'));
    }

    public function testPhase4AddsOnlyAssociationTables(): void
    {
        $sql=$this->source('database/20260720_task_agent_phase4_v1.sql');
        foreach([
            'multi_agent_recurring_program_links',
            'multi_agent_group_gift_links',
            'multi_agent_distribution_program_links',
        ] as $table)self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        foreach([
            'user_recurring_gift_programs','user_recurring_gift_runs','user_group_gifts','user_group_gift_participants',
            'distribution_programs','distribution_recipients','distribution_allocations','distribution_issuance_jobs',
            'agent_strategies','agent_approval_requests','task_agent_alerts','task_agent_monitoring',
        ] as $canonical)self::assertStringNotContainsString('CREATE TABLE IF NOT EXISTS '.$canonical,$sql);
    }

    public function testAllPhaseAuthoritiesAssetsContractsAndScorecardsExist(): void
    {
        $files=[
            'includes/task-agent-recurring-programs.php','includes/task-agent-recurring-program-links.php','includes/task-agent-recurring-programs-router.php',
            'assets/js/task-agent-recurring-programs-runtime.js','assets/css/task-agent-recurring-programs-v1.css','tests/phpunit/TaskAgentPhase41RecurringProgramsV1ContractTest.php','docs/TASK_AGENT_PHASE4_1_SCORECARD.md',
            'includes/task-agent-group-gifts.php','includes/task-agent-group-gifts-router.php','assets/js/task-agent-group-gifts-runtime.js','assets/css/task-agent-group-gifts-v1.css','tests/phpunit/TaskAgentPhase42GroupGiftingV1ContractTest.php','docs/TASK_AGENT_PHASE4_2_SCORECARD.md',
            'includes/task-agent-program-coordination.php','includes/task-agent-program-coordination-router.php','assets/js/task-agent-program-coordination-runtime.js','assets/css/task-agent-program-coordination-v1.css','tests/phpunit/TaskAgentPhase43WorkplaceCommunityProgramsV1ContractTest.php','docs/TASK_AGENT_PHASE4_3_SCORECARD.md',
            'includes/task-agent-policy-approvals.php','includes/task-agent-policy-approvals-router.php','assets/js/task-agent-policy-approvals-runtime.js','assets/css/task-agent-policy-approvals-v1.css','tests/phpunit/TaskAgentPhase44RulesBudgetsApprovalsV1ContractTest.php','docs/TASK_AGENT_PHASE4_4_SCORECARD.md',
            'includes/task-agent-monitoring.php','includes/task-agent-monitoring-router.php','assets/js/task-agent-monitoring-runtime.js','assets/css/task-agent-monitoring-v1.css','tests/phpunit/TaskAgentPhase45MonitoringPreparationV1ContractTest.php','docs/TASK_AGENT_PHASE4_5_SCORECARD.md',
            'config/task_agent_phase4_release.php','docs/TASK_AGENT_PHASE4_PRODUCTION_RUNBOOK.md',
        ];
        foreach($files as $file)self::assertFileExists($this->root.'/'.$file,$file.' is required for the Phase 4 release.');
    }

    public function testReleaseAssetsAreVersionedAndLoaded(): void
    {
        $release=require $this->root.'/config/task_agent_phase4_release.php';
        $page=$this->source('agent.php');
        self::assertCount(10,$release['runtime_assets']);
        foreach($release['runtime_assets'] as $asset){
            self::assertStringContainsString('?v=',$asset);
            self::assertStringContainsString($asset,$page);
            $path=parse_url($asset,PHP_URL_PATH);
            self::assertIsString($path);
            self::assertFileExists($this->root.'/'.ltrim($path,'/'));
        }
    }

    public function testCompleteDeterministicRouteChainPrecedesGeneralAiRuntime(): void
    {
        $api=$this->source('api/agents/runtime.php');
        $chatStart=strpos($api,"if(\$action==='chat')");
        self::assertNotFalse($chatStart);
        $chat=substr($api,$chatStart,1000);
        $recurring=strpos($chat,'mg_task_agent_recurring_chat');
        $group=strpos($chat,'mg_task_agent_group_chat');
        $program=strpos($chat,'mg_task_agent_program_chat');
        $general=strpos($chat,'mg_multi_agent_runtime_chat');
        foreach([$recurring,$group,$program,$general] as $position)self::assertNotFalse($position);
        self::assertTrue($recurring<$group&&$group<$program&&$program<$general);

        $programRouter=$this->source('includes/task-agent-program-coordination-router.php');
        $monitor=strpos($programRouter,"if(\$monitorIntent!=='')return 'monitor'");
        $policy=strpos($programRouter,"if(\$policyIntent!=='')return 'policy'");
        self::assertNotFalse($monitor);self::assertNotFalse($policy);self::assertLessThan($policy,$monitor);
        self::assertStringContainsString("if(\$intent==='monitor')return mg_task_agent_monitor_route",$programRouter);

        $recurringRouter=$this->source('includes/task-agent-recurring-programs-router.php');
        self::assertStringContainsString("return 'monitor'",$recurringRouter);
        self::assertStringContainsString("if(\$intent==='monitor')return mg_task_agent_monitor_route",$recurringRouter);
    }

    public function testCanonicalAuthoritiesMatchReleaseManifest(): void
    {
        $release=require $this->root.'/config/task_agent_phase4_release.php';
        $all=array_merge(...array_values($release['canonical_authorities']));
        foreach([
            'user_recurring_gift_programs','user_recurring_gift_runs','user_gifting_plans',
            'user_group_gifts','user_group_gift_participants','user_contact_lists',
            'distribution_programs','distribution_program_products','distribution_recipients','distribution_allocations','distribution_issuance_jobs',
            'agent_strategies','agent_workflow_runs','agent_workflow_actions','agent_approval_requests',
            'user_gifting_schedules','user_recipient_data_requests',
        ] as $authority)self::assertContains($authority,$all);
    }

    public function testAllModelProjectionsAreBounded(): void
    {
        $recurring=$this->source('includes/task-agent-recurring-programs.php');
        $group=$this->source('includes/task-agent-group-gifts.php');
        $program=$this->source('includes/task-agent-program-coordination.php');
        $policy=$this->source('includes/task-agent-policy-approvals.php');
        $monitor=$this->source('includes/task-agent-monitoring.php');
        self::assertStringContainsString('array_slice($programs,0,8)',$recurring);
        self::assertStringContainsString('array_slice($groups,0,8)',$group);
        self::assertStringContainsString('array_slice($programs,0,12)',$program);
        self::assertStringContainsString('array_slice($guardrails,0,12)',$policy);
        self::assertStringContainsString('array_slice($strategies,0,12)',$policy);
        self::assertStringContainsString("array_slice(\$snapshot['items']??[],0,16)",$monitor);
    }

    public function testPhase4ModelProjectionsExcludeHighRiskPayloads(): void
    {
        $sources=[
            'program'=>$this->source('includes/task-agent-program-coordination.php'),
            'policy'=>$this->source('includes/task-agent-policy-approvals.php'),
            'monitor'=>$this->source('includes/task-agent-monitoring.php'),
        ];
        $ranges=[];
        $ranges['program']=substr($sources['program'],strpos($sources['program'],'function mg_task_agent_program_for_model'),strpos($sources['program'],'function mg_task_agent_program_card')-strpos($sources['program'],'function mg_task_agent_program_for_model'));
        $ranges['policy']=substr($sources['policy'],strpos($sources['policy'],'function mg_task_agent_policy_for_model'),strpos($sources['policy'],'function mg_task_agent_policy_append_context')-strpos($sources['policy'],'function mg_task_agent_policy_for_model'));
        $ranges['monitor']=substr($sources['monitor'],strpos($sources['monitor'],'function mg_task_agent_monitor_for_model'),strpos($sources['monitor'],'function mg_task_agent_monitor_card')-strpos($sources['monitor'],'function mg_task_agent_monitor_for_model'));
        foreach($ranges as $name=>$range){
            foreach(['address_line','postal_code','email','phone','claim_code','redemption_code','payment_method','request_json','failure_message','idempotency_key'] as $private){
                self::assertStringNotContainsString($private,$range,$name.' model context must exclude '.$private);
            }
        }
        foreach(['title','body','url','public_id','owner_user_id','agent_id','requested_reason','decision_reason','target_reference'] as $private){
            self::assertStringNotContainsString($private,$ranges['monitor']);
        }
    }

    public function testRunbookContainsDeploymentSmokePrivacyAiRollbackAndBlockers(): void
    {
        $runbook=$this->source('docs/TASK_AGENT_PHASE4_PRODUCTION_RUNBOOK.md');
        foreach([
            'Required SQL','Deployment order','Phase 4.1 — Recurring Gift Programs','Phase 4.2 — Group Gifting',
            'Phase 4.3 — Workplace and Community Programs','Phase 4.4 — Rules, Budgets, and Approvals',
            'Phase 4.5 — Monitoring and Preparation','Isolation tests','Privacy tests','AI usage verification',
            'Observability','Rollback','Release blockers','20260720_task_agent_phase4_v1.sql',
        ] as $section)self::assertStringContainsString($section,$runbook);
    }

    public function testReleaseBoundariesAreComplete(): void
    {
        $release=require $this->root.'/config/task_agent_phase4_release.php';
        foreach([
            'existing_program_authorities_are_reused_not_duplicated',
            'recurring_cycles_create_reviewable_draft_plans_only',
            'group_gifts_are_pledge_only_and_collect_no_payment',
            'distribution_program_mutations_stay_in_canonical_merchant_workspaces',
            'rules_budgets_strategies_and_approval_decisions_are_read_only_in_specialized_agents',
            'monitoring_is_on_demand_and_persists_no_alert_feed',
            'recipient_readiness_exposes_booleans_not_private_address_values',
            'no_automatic_purchase_message_allocation_issuance_or_approval',
            'routine_phase4_routes_use_system_queries_and_zero_ai_credits',
            'ai_is_reserved_for_explicit_sanitized_synthesis_only',
        ] as $boundary)self::assertContains($boundary,$release['release_boundaries']);
    }
}
