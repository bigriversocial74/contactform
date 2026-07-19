<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MainAdminAgentPhase2ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function file(string $path): string
    {
        $content = file_get_contents($this->root . '/' . ltrim($path, '/'));
        self::assertIsString($content, 'Unable to read ' . $path);
        return $content;
    }

    public function testMigrationCreatesHistoricalIntelligenceSchema(): void
    {
        $sql = $this->file('database/20260718_main_admin_agent_phase2.sql');
        foreach ([
            'admin_agent_metric_samples',
            'admin_agent_metric_baselines',
            'admin_agent_anomalies',
            'admin_agent_deployments',
            'admin_agent_runbooks',
            'admin_agent_correlations',
            'admin_agent_escalation_policies',
            'admin_agent_escalations',
            'admin_agent_executive_summaries',
            'admin_agent_remediation_adapters',
            'admin_agent_remediation_executions',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        foreach ([
            'uq_admin_agent_metric_baselines_series',
            'uq_admin_agent_anomalies_key',
            'uq_admin_agent_correlations_key',
            'uq_admin_agent_escalations_key',
            'uq_admin_agent_remediation_executions_idempotency',
        ] as $index) {
            self::assertStringContainsString('UNIQUE KEY ' . $index, $sql);
        }
    }

    public function testMigrationSeedsPoliciesRunbooksAdaptersAndPermissions(): void
    {
        $sql = $this->file('database/20260718_main_admin_agent_phase2.sql');
        foreach ([
            'anomaly_baselines', 'cross_system_correlation',
            'security_governance_change', 'notification_queue_pressure',
            'automation_sla_degradation', 'deployment_regression', 'multi_domain_critical',
            'critical_correlation', 'high_correlation', 'critical_anomaly', 'high_finding',
            'run_admin_agent_scan', 'run_ai_credit_reconciliation',
            'generate_migration_plan', 'investigate_security_events',
        ] as $marker) {
            self::assertStringContainsString("'" . $marker . "'", $sql);
        }
        self::assertStringContainsString("'admin.admin_agent.escalations'", $sql);
        self::assertStringContainsString("'admin.admin_agent.deployments'", $sql);
        self::assertStringContainsString("'admin.admin_agent.execute'", $sql);
        self::assertStringContainsString("WHERE r.slug='super_admin'", $sql);
        self::assertStringContainsString("'run_queue_automation'", $sql);
        self::assertStringContainsString("'disabled',0,1", $sql);
    }

    public function testMetricBaselinesUseBoundedOnlineStatistics(): void
    {
        $service = $this->file('includes/admin-agent-phase2.php');
        $sql = $this->file('database/20260718_main_admin_agent_phase2.sql');
        foreach (['m2_value','variance_value','stddev_value'] as $column) {
            self::assertStringContainsString($column, $sql);
        }
        self::assertStringContainsString('minimum_samples INT UNSIGNED NOT NULL DEFAULT 8', $sql);
        self::assertStringContainsString('anomaly_threshold DECIMAL(8,3) NOT NULL DEFAULT 3.000', $sql);
        self::assertStringContainsString('$delta = $value - $previousMean', $service);
        self::assertStringContainsString('$newMean = $previousMean + ($delta / $newCount)', $service);
        self::assertStringContainsString('$newM2 = $previousM2 + ($delta * $delta2)', $service);
        self::assertStringContainsString('$zScore = $difference / $previousStddev', $service);
        self::assertStringContainsString('mg_admin_agent_phase2_resolve_anomaly', $service);
        self::assertStringContainsString('recurrence_count=recurrence_count+IF', $service);
    }

    public function testCrossSystemCorrelationCoversRequiredRules(): void
    {
        $service = $this->file('includes/admin-agent-phase2.php');
        foreach ([
            'multi_domain_critical', 'notification_queue_pressure',
            'automation_sla_degradation', 'security_governance_change',
            'deployment_regression', 'ai_provider_accounting_risk',
        ] as $type) {
            self::assertStringContainsString("'correlation_type'=>'" . $type . "'", $service);
        }
        self::assertStringContainsString('recommended_action_key', $service);
        self::assertStringContainsString('runbook_key', $service);
        self::assertStringContainsString('Correlation conditions cleared in a later analysis run.', $service);
    }

    public function testDeploymentAwarenessSupportsCliAndEnvironmentMetadata(): void
    {
        $service = $this->file('includes/admin-agent-phase2.php');
        $runner = $this->file('scripts/record_admin_agent_deployment.php');
        self::assertStringContainsString('function mg_admin_agent_phase2_record_deployment', $service);
        foreach (['MG_DEPLOY_COMMIT_SHA','GIT_COMMIT_SHA','MG_DEPLOY_BRANCH','MG_DEPLOY_ENV'] as $variable) {
            self::assertStringContainsString($variable, $service);
        }
        self::assertStringContainsString("['commit:'", $runner);
        self::assertStringContainsString('mg_admin_agent_phase2_correlate($pdo)', $runner);
        self::assertStringContainsString("PHP_SAPI!=='cli'", $runner);
    }

    public function testEscalationsAndSummariesReuseExistingAdminFoundations(): void
    {
        $service = $this->file('includes/admin-agent-phase2.php');
        self::assertStringContainsString("require_once dirname(__DIR__) . '/api/admin/_queue_alerts.php'", $service);
        self::assertStringContainsString('function mg_admin_agent_phase2_process_escalations', $service);
        self::assertStringContainsString('admin_queue_notifications', $service);
        self::assertStringContainsString('notification_public_id', $service);
        self::assertStringContainsString('function mg_admin_agent_phase2_generate_summary', $service);
        self::assertStringContainsString('admin_agent_executive_summaries', $service);
        self::assertStringContainsString("\$periodType==='weekly'", $service);
        self::assertStringContainsString("'database_only'=>true", $service);
        self::assertStringContainsString("'used_ai'=>false", $service);
        self::assertStringContainsString("'credits_used'=>0", $service);
    }

    public function testControlledRemediationIsApprovedConfirmedAndIdempotent(): void
    {
        $service = $this->file('includes/admin-agent-phase2-remediation.php');
        $sql = $this->file('database/20260718_main_admin_agent_phase2.sql');
        self::assertStringContainsString('function mg_admin_agent_phase2_review_action', $service);
        self::assertStringContainsString('function mg_admin_agent_phase2_execute_action', $service);
        self::assertStringContainsString("\$expected='EXECUTE '.(string)\$execution['adapter_key']", $service);
        self::assertStringContainsString('status="running"', $service);
        self::assertStringContainsString('status="succeeded"', $service);
        self::assertStringContainsString('idempotency_key', $service);
        self::assertStringContainsString("ENUM('in_process','disabled')", $sql);
        foreach (['shell_exec(','passthru(','system(','proc_open(','eval('] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $service);
        }
    }

    public function testOnlyExplicitExecutionPermissionCanApproveOrExecute(): void
    {
        $api = $this->file('api/admin/admin-agent-phase2.php');
        $matrix = $this->file('includes/admin-permission-matrix.php');
        self::assertStringContainsString("'admin.admin_agent.execute' => []", $api);
        self::assertStringContainsString("'admin.admin_agent.execute' => []", $matrix);
        self::assertStringContainsString("mg_admin_agent_phase2_api_require(\$actor,'admin.admin_agent.execute')", $api);
        self::assertStringContainsString("WHERE r.slug='super_admin'", $this->file('database/20260718_main_admin_agent_phase2.sql'));
    }

    public function testPhase2ApiIsRateLimitedCsrfProtectedAndFailClosed(): void
    {
        $api = $this->file('api/admin/admin-agent-phase2.php');
        self::assertStringContainsString('mg_require_api_user()', $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase2.read'", $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase2.write'", $api);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
        self::assertStringContainsString('mg_admin_agent_phase2_schema_state', $api);
        self::assertStringContainsString('Main Admin Agent Phase 2 SQL migration is required.', $api);
        foreach (['record_deployment','generate_summary','intelligence_action','acknowledge_escalation','review_action','execute_action'] as $action) {
            self::assertStringContainsString("\$action==='" . $action . "'", $api);
        }
    }

    public function testAdminChatKeepsCurrentLayoutAndAddsPhase2Rails(): void
    {
        $page = $this->file('admin/admin-agent.php');
        foreach (['agent-workspace-layout.css','personal-gifting-agent.css','merchant-agent-chat.css','mg-merchant-agent-composer'] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
        self::assertStringContainsString('/api/admin/admin-agent-phase2.php', $page);
        self::assertStringContainsString('/api/admin/admin-agent-phase2-stream.php', $page);
        foreach (['data-admin-agent-correlations','data-admin-agent-anomalies','data-admin-agent-escalations','data-admin-agent-deployments','data-admin-agent-remediation'] as $attribute) {
            self::assertStringContainsString($attribute, $page);
        }
        foreach (['Anomaly report','Cross-system correlations','Deployment impact report','Escalation report','Executive summary','Controlled remediation report'] as $prompt) {
            self::assertStringContainsString($prompt, $page);
        }
        self::assertStringContainsString('Database-first · No AI credits', $page);
    }

    public function testClientSupportsLiveIntelligenceAndTypedExecution(): void
    {
        $client = $this->file('assets/js/admin-agent-phase2.js');
        foreach (['new EventSource','setInterval','15000','renderCorrelations','renderAnomalies','renderEscalations','renderRemediation'] as $marker) {
            self::assertStringContainsString($marker, $client);
        }
        foreach (['record_deployment','generate_summary','review_action','execute_action'] as $action) {
            self::assertStringContainsString("action: '" . $action . "'", $client);
        }
        self::assertStringContainsString("var expected = 'EXECUTE ' + actionKey", $client);
    }

    public function testSseIsBoundedPrivateAndReleasesSessionLock(): void
    {
        $stream = $this->file('api/admin/admin-agent-phase2-stream.php');
        foreach (['text/event-stream','Cache-Control: no-cache, no-store','X-Accel-Buffering','session_write_close','for($iteration=0;$iteration<8;$iteration++)','sleep(2)'] as $marker) {
            self::assertStringContainsString($marker, $stream);
        }
    }

    public function testScheduledRunnerAndOperationsDocumentationExist(): void
    {
        $runner = $this->file('scripts/run_admin_agent_phase2.php');
        $docs = $this->file('docs/operations/main-admin-agent-phase2.md');
        self::assertStringContainsString("PHP_SAPI!=='cli'", $runner);
        self::assertStringContainsString('mg_admin_agent_phase2_run', $runner);
        foreach (['*/5 * * * *','approximately 40 minutes','No AI credits','explicit super-admin execution boundary','record_admin_agent_deployment.php'] as $marker) {
            self::assertStringContainsString($marker, $docs);
        }
    }

    public function testPhase2CodeUsesNoExternalModelOrArbitraryExecution(): void
    {
        foreach ([
            'includes/admin-agent-phase2.php',
            'includes/admin-agent-phase2-remediation.php',
            'api/admin/admin-agent-phase2.php',
            'api/admin/admin-agent-phase2-stream.php',
        ] as $path) {
            $source = $this->file($path);
            foreach (['mg_anthropic_messages(','mg_openai','curl_exec(','shell_exec(','proc_open('] as $unsafe) {
                self::assertStringNotContainsString($unsafe, $source);
            }
        }
    }

    public function testCanonicalMigrationIsRegisteredAfterPhase1(): void
    {
        $manifest = $this->file('config/migrations.php');
        self::assertStringContainsString("'20260718_main_admin_agent_phase1.sql',\n        '20260718_main_admin_agent_phase2.sql'", $manifest);
    }
}
