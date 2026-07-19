<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MainAdminAgentPhase3ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function file(string $path): string
    {
        $content=file_get_contents($this->root.'/'.ltrim($path,'/'));
        self::assertIsString($content,'Unable to read '.$path);
        return $content;
    }

    public function testMigrationCreatesOperationalControlSchema(): void
    {
        $sql=$this->file('database/20260719_main_admin_agent_phase3.sql');
        foreach([
            'admin_agent_services','admin_agent_service_dependencies','admin_agent_slo_policies',
            'admin_agent_slo_snapshots','admin_agent_incident_workspaces','admin_agent_incident_timeline',
            'admin_agent_cause_candidates','admin_agent_release_gates','admin_agent_brief_subscriptions',
            'admin_agent_brief_deliveries',
        ] as $table) self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_service_dependencies_pair',$sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_slo_snapshots_window',$sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_incident_workspaces_key',$sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_cause_candidates_key',$sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_release_gates_key',$sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_brief_deliveries_key',$sql);
    }

    public function testMigrationSeedsServicesDependenciesSlosAndPermissions(): void
    {
        $sql=$this->file('database/20260719_main_admin_agent_phase3.sql');
        foreach(['identity_access','database_core','commerce_payments','claims_redemption','notification_delivery','admin_automation','admin_operations','security_observability','ai_accounting','campaign_delivery','storefront_experience'] as $service){
            self::assertStringContainsString("'".$service."'",$sql);
        }
        self::assertStringContainsString('admin_agent_service_dependencies',$sql);
        self::assertStringContainsString('daily_availability',$sql);
        self::assertStringContainsString("'admin.admin_agent.incidents'",$sql);
        self::assertStringContainsString("'admin.admin_agent.releases'",$sql);
        self::assertStringContainsString("'admin.admin_agent.briefs'",$sql);
        self::assertStringContainsString("WHERE adapter_key='declare_operations_incident'",$sql);
        self::assertStringContainsString("execution_mode='in_process',enabled=1",$sql);
    }

    public function testTopologyIsDependencyAwareAndDatabaseGrounded(): void
    {
        $service=$this->file('includes/admin-agent-phase3.php');
        self::assertStringContainsString('function mg_admin_agent_phase3_services',$service);
        self::assertStringContainsString('admin_agent_service_dependencies',$service);
        self::assertStringContainsString('mg_admin_agent_domain_health($pdo)',$service);
        self::assertStringContainsString('admin_agent_slo_snapshots',$service);
        self::assertStringContainsString("'dependencies'=>",$service);
    }

    public function testSloSnapshotsTrackErrorBudgetAndBurnRate(): void
    {
        $service=$this->file('includes/admin-agent-phase3.php');
        self::assertStringContainsString('function mg_admin_agent_phase3_evaluate_slos',$service);
        self::assertStringContainsString('$allowedError=max(0.0001,100.0-(float)$policy[\'objective_percent\'])',$service);
        self::assertStringContainsString('$burn=$errorRate/$allowedError',$service);
        self::assertStringContainsString('$budget=max(0.0,100.0-(($errorRate/$allowedError)*100.0))',$service);
        self::assertStringContainsString('$severity=$burn>=(float)$policy[\'critical_burn_rate\']?\'critical\'',$service);
        self::assertStringContainsString('critical_findings',$service);
        self::assertStringContainsString('high_findings',$service);
    }

    public function testIncidentWorkspacesFollowCorrelationsAndStoreTimeline(): void
    {
        $service=$this->file('includes/admin-agent-phase3.php');
        self::assertStringContainsString('function mg_admin_agent_phase3_sync_incidents',$service);
        self::assertStringContainsString('severity IN ("high","critical")',$service);
        self::assertStringContainsString('Incident workspace created',$service);
        self::assertStringContainsString('function mg_admin_agent_phase3_timeline',$service);
        self::assertStringContainsString('function mg_admin_agent_phase3_incident_action',$service);
        self::assertStringContainsString("['watching','declared','investigating','mitigating','monitoring','resolved','dismissed']",$service);
        self::assertStringContainsString('A resolution or dismissal note is required.',$service);
    }

    public function testLinkedOperationsIncidentsPreventPrematureWorkspaceResolution(): void
    {
        $lifecycle=$this->file('includes/admin-agent-phase3-lifecycle.php');
        $api=$this->file('api/admin/admin-agent-phase3.php');
        $runner=$this->file('scripts/run_admin_agent_phase3.php');
        self::assertStringContainsString('function mg_admin_agent_phase3_reconcile_linked_ops_incidents',$lifecycle);
        self::assertStringContainsString("$opsStatus!=='resolved'",$lifecycle);
        self::assertStringContainsString('Linked operations incident remains active',$lifecycle);
        self::assertStringContainsString('function mg_admin_agent_phase3_run_hardened',$lifecycle);
        self::assertStringContainsString('mg_admin_agent_phase3_run_hardened($pdo',$api);
        self::assertStringContainsString('mg_admin_agent_phase3_run_hardened(mg_db()',$runner);
    }

    public function testCauseAnalysisRanksEvidenceAndLabelsHypotheses(): void
    {
        $service=$this->file('includes/admin-agent-phase3.php');
        foreach(['deployment','anomaly','finding','dependency','change_activity'] as $type){
            self::assertStringContainsString("'cause_type'=>'".$type."'",$service);
        }
        self::assertStringContainsString('confidence_percent',$service);
        self::assertStringContainsString('rank_order',$service);
        self::assertStringContainsString('Candidates are evidence-based hypotheses, not proof.',$service);
        self::assertStringContainsString("'cause_candidates_are_hypotheses'=>true",$service);
    }

    public function testReleaseGateIsAdvisoryAndUsesCriticalEvidence(): void
    {
        $service=$this->file('includes/admin-agent-phase3.php');
        $docs=$this->file('docs/operations/main-admin-agent-phase3.md');
        self::assertStringContainsString('function mg_admin_agent_phase3_evaluate_release',$service);
        self::assertStringContainsString('$criticalSlo',$service);
        self::assertStringContainsString('$criticalIncidents',$service);
        self::assertStringContainsString('$postDeployCritical',$service);
        self::assertStringContainsString('$status=$reasons!==[]?\'block\'',$service);
        self::assertStringContainsString('The gate is advisory.',$docs);
        self::assertStringContainsString('It does not deploy, roll back, freeze, or modify production by itself.',$docs);
    }

    public function testBriefDeliveryIsIdempotentAndUsesNotificationCenter(): void
    {
        $service=$this->file('includes/admin-agent-phase3.php');
        $sql=$this->file('database/20260719_main_admin_agent_phase3.sql');
        self::assertStringContainsString('function mg_admin_agent_phase3_process_briefs',$service);
        self::assertStringContainsString('hash(\'sha256\',(int)$subscription[\'id\'].\'|\'.$cadence.\'|\'.$period)',$service);
        self::assertStringContainsString('mg_queue_notice_create($pdo',$service);
        self::assertStringContainsString('admin_agent_brief_deliveries',$service);
        self::assertStringContainsString("ENUM('notification_center')",$sql);
        self::assertStringContainsString("'database_only'=>true",$service);
        self::assertStringContainsString("'credits_used'=>0",$service);
    }

    public function testIncidentDeclarationRequiresReviewApprovalAndConfirmation(): void
    {
        $service=$this->file('includes/admin-agent-phase3-remediation.php');
        $api=$this->file('api/admin/admin-agent-phase3.php');
        $matrix=$this->file('includes/admin-permission-matrix.php');
        self::assertStringContainsString('$adapterKey!==\'declare_operations_incident\'',$service);
        self::assertStringContainsString('mg_ops_incident_declare($pdo',$service);
        self::assertStringContainsString('$expected=\'EXECUTE \'.(string)$execution[\'adapter_key\']',$service);
        self::assertStringContainsString('status="running"',$service);
        self::assertStringContainsString('status="succeeded"',$service);
        self::assertStringContainsString("'admin.admin_agent.execute'=>[]",$api);
        self::assertStringContainsString("'admin.admin_agent.execute' => []",$matrix);
        self::assertStringContainsString('mg_admin_agent_phase3_api_require($actor,\'admin.admin_agent.execute\')',$api);
        self::assertStringNotContainsString('shell_exec(',$service);
        self::assertStringNotContainsString('proc_open(',$service);
        self::assertStringNotContainsString('passthru(',$service);
        self::assertStringNotContainsString('eval(',$service);
    }

    public function testPhase3ApiIsProtectedAndFailClosed(): void
    {
        $api=$this->file('api/admin/admin-agent-phase3.php');
        self::assertStringContainsString('mg_require_api_user()',$api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase3.read'",$api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase3.write'",$api);
        self::assertStringContainsString('mg_require_csrf_for_write($input)',$api);
        self::assertStringContainsString('Main Admin Agent Phase 3 SQL migration is required.',$api);
        foreach(['incident_workspace_action','evaluate_release','update_brief','execute_action'] as $action){
            self::assertStringContainsString('$action===\''.$action.'\'',$api);
        }
        self::assertStringContainsString('$error->httpStatus()',$api);
    }

    public function testChatWorkspaceKeepsCurrentLayoutAndAddsPhase3Controls(): void
    {
        $page=$this->file('admin/admin-agent.php');
        self::assertStringContainsString('agent-workspace-layout.css',$page);
        self::assertStringContainsString('merchant-agent-chat.css',$page);
        self::assertStringContainsString('mg-merchant-agent-composer',$page);
        self::assertStringContainsString('/api/admin/admin-agent-phase3.php',$page);
        self::assertStringContainsString('/api/admin/admin-agent-phase3-stream.php',$page);
        foreach(['data-admin-agent-services','data-admin-agent-slos','data-admin-agent-incidents','data-admin-agent-release-gates','data-admin-agent-briefs'] as $attribute){
            self::assertStringContainsString($attribute,$page);
        }
        foreach(['Service topology report','SLO and error budget report','Incident workspace report','Root cause timeline','Release readiness gate','Scheduled brief delivery'] as $prompt){
            self::assertStringContainsString($prompt,$page);
        }
        self::assertStringContainsString('Database-first · No AI credits',$page);
    }

    public function testClientSupportsOperationalControlsAndPolling(): void
    {
        $client=$this->file('assets/js/admin-agent-phase3.js');
        self::assertStringContainsString('window.setInterval',$client);
        self::assertStringContainsString('15000',$client);
        self::assertStringContainsString("action:'incident_workspace_action'",$client);
        self::assertStringContainsString("action:'request_action'",$client);
        self::assertStringContainsString("action_key:'declare_operations_incident'",$client);
        self::assertStringContainsString("action:'evaluate_release'",$client);
        self::assertStringContainsString("action:'update_brief'",$client);
        self::assertStringContainsString('renderServices',$client);
        self::assertStringContainsString('renderSlos',$client);
        self::assertStringContainsString('renderIncidents',$client);
        self::assertStringContainsString('renderGates',$client);
    }

    public function testSseIsBoundedPrivateAndReleasesSessionLock(): void
    {
        $stream=$this->file('api/admin/admin-agent-phase3-stream.php');
        self::assertStringContainsString('text/event-stream',$stream);
        self::assertStringContainsString('Cache-Control: no-cache, no-store',$stream);
        self::assertStringContainsString('X-Accel-Buffering',$stream);
        self::assertStringContainsString('session_write_close',$stream);
        self::assertStringContainsString('for($iteration=0;$iteration<8;$iteration++)',$stream);
        self::assertStringContainsString('sleep(2)',$stream);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase3.stream'",$stream);
    }

    public function testScheduledRunnerAndOperationsDocumentationExist(): void
    {
        $runner=$this->file('scripts/run_admin_agent_phase3.php');
        $docs=$this->file('docs/operations/main-admin-agent-phase3.md');
        self::assertStringContainsString("PHP_SAPI!=='cli'",$runner);
        self::assertStringContainsString('mg_admin_agent_phase3_run_hardened',$runner);
        self::assertStringContainsString('*/5 * * * *',$docs);
        self::assertStringContainsString('Do not run Phase 1, Phase 2, and Phase 3 runners together.',$docs);
        self::assertStringContainsString('evidence-ranked hypotheses, not proof',$docs);
        self::assertStringContainsString('EXECUTE declare_operations_incident',$docs);
        self::assertStringContainsString('No AI credits are consumed',$docs);
    }

    public function testMigrationManifestRegistersPhase3AfterPhase2(): void
    {
        $manifest=require $this->root.'/config/migrations.php';
        $files=$manifest['ordered_files'];
        $phase2=array_search('20260718_main_admin_agent_phase2.sql',$files,true);
        $phase3=array_search('20260719_main_admin_agent_phase3.sql',$files,true);
        self::assertIsInt($phase2);
        self::assertIsInt($phase3);
        self::assertSame($phase2+1,$phase3);
    }

    public function testPhase3UsesNoExternalModelOrArbitraryExecution(): void
    {
        foreach([
            'includes/admin-agent-phase3.php','includes/admin-agent-phase3-lifecycle.php','includes/admin-agent-phase3-remediation.php',
            'api/admin/admin-agent-phase3.php','api/admin/admin-agent-phase3-stream.php',
        ] as $path){
            $source=$this->file($path);
            self::assertStringNotContainsString('mg_anthropic_messages(',$source);
            self::assertStringNotContainsString('curl_exec(',$source);
            self::assertStringNotContainsString('shell_exec(',$source);
            self::assertStringNotContainsString('proc_open(',$source);
            self::assertStringNotContainsString('eval(',$source);
        }
    }
}
