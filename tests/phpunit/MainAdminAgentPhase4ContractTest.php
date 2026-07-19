<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MainAdminAgentPhase4ContractTest extends TestCase
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

    public function testMigrationCreatesReliabilityGovernanceSchema(): void
    {
        $sql = $this->file('database/20260719_main_admin_agent_phase4.sql');
        foreach ([
            'admin_agent_maintenance_windows',
            'admin_agent_change_risk_assessments',
            'admin_agent_reliability_scorecards',
            'admin_agent_capacity_forecasts',
            'admin_agent_incident_learning',
            'admin_agent_prevention_followups',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_maintenance_windows_key', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_change_risk_key', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_reliability_key', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_capacity_forecasts_key', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_incident_learning_key', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_prevention_followups_key', $sql);
    }

    public function testMigrationUsesCanonicalPermissionsAndAdapterIdentity(): void
    {
        $sql = $this->file('database/20260719_main_admin_agent_phase4.sql');
        self::assertStringContainsString('INSERT IGNORE INTO permissions (slug,name,description,created_at)', $sql);
        self::assertStringNotContainsString('permission_catalog', $sql);
        foreach ([
            'admin.admin_agent.maintenance',
            'admin.admin_agent.reliability',
            'admin.admin_agent.learning',
            'admin.admin_agent.forecasts',
        ] as $permission) {
            self::assertStringContainsString("'" . $permission . "'", $sql);
        }
        self::assertStringContainsString("'01ADMINAGENTP4ADAPTER00001'", $sql);
        self::assertStringContainsString("'create_prevention_followup'", $sql);
        self::assertStringContainsString("'20260719_main_admin_agent_phase4'", $sql);
    }

    public function testMaintenanceWindowsNeverSuppressSecurityOrCriticalEvidence(): void
    {
        $service = $this->file('includes/admin-agent-phase4.php');
        $docs = $this->file('docs/operations/main-admin-agent-phase4.md');
        self::assertStringContainsString('function mg_admin_agent_phase4_maintenance_action', $service);
        self::assertStringContainsString("if((string)\$service['domain']==='security') \$mode='observe_only'", $service);
        self::assertStringContainsString('security_findings_suppressed', $service);
        self::assertStringContainsString('critical_findings_suppressed', $service);
        self::assertStringContainsString('Security findings and critical evidence remain visible', $docs);
        self::assertStringNotContainsString('DELETE FROM admin_agent_findings', $service);
    }

    public function testChangeRiskUsesDeploymentReliabilityAndMaintenanceEvidence(): void
    {
        $service = $this->file('includes/admin-agent-phase4.php');
        self::assertStringContainsString('function mg_admin_agent_phase4_evaluate_change', $service);
        foreach ([
            'critical_or_high_services',
            'changed_files',
            'migration_count',
            'critical_slo_total',
            'warning_slo_total',
            'active_incident_total',
            'deployments_last_24h',
            'maintenance_window_active',
        ] as $factor) {
            self::assertStringContainsString("'" . $factor . "'", $service);
        }
        self::assertStringContainsString('mg_admin_agent_phase4_active_maintenance', $service);
        self::assertStringContainsString('Delay non-essential production changes', $service);
    }

    public function testReliabilityScorecardsCoverSevenThirtyAndNinetyDays(): void
    {
        $service = $this->file('includes/admin-agent-phase4.php');
        self::assertStringContainsString('function mg_admin_agent_phase4_generate_scorecards', $service);
        self::assertStringContainsString('foreach([7,30,90] as $days)', $service);
        self::assertStringContainsString('availability_percent', $service);
        self::assertStringContainsString('error_budget_remaining_percent', $service);
        self::assertStringContainsString('warning_snapshot_total', $service);
        self::assertStringContainsString('critical_snapshot_total', $service);
        self::assertStringContainsString('incident_total', $service);
        self::assertStringContainsString('reliability_score', $service);
    }

    public function testCapacityForecastsUseStoredMetricHistoryWithoutExternalModels(): void
    {
        $service = $this->file('includes/admin-agent-phase4.php');
        self::assertStringContainsString('function mg_admin_agent_phase4_generate_capacity_forecasts', $service);
        self::assertStringContainsString('admin_agent_metric_samples', $service);
        self::assertStringContainsString('INTERVAL 14 DAY', $service);
        self::assertStringContainsString('HAVING sample_total>=2', $service);
        self::assertStringContainsString('$pred7', $service);
        self::assertStringContainsString('$pred30', $service);
        self::assertStringContainsString('admin_agent_metric_baselines', $service);
        self::assertStringContainsString('utilization_percent', $service);
    }

    public function testIncidentLearningUsesResolvedEvidenceAndLabelsHypotheses(): void
    {
        $service = $this->file('includes/admin-agent-phase4.php');
        self::assertStringContainsString('function mg_admin_agent_phase4_generate_learning', $service);
        self::assertStringContainsString('admin_agent_incident_timeline', $service);
        self::assertStringContainsString('admin_agent_cause_candidates', $service);
        self::assertStringContainsString('evidence-ranked hypothesis until administrator review', $service);
        self::assertStringContainsString('prevention_actions_json', $service);
        self::assertStringContainsString('function mg_admin_agent_phase4_learning_action', $service);
        self::assertStringContainsString("'root_causes_are_hypotheses'=>true", $service);
    }

    public function testPreventionFollowupRequiresReviewApprovalAndTypedConfirmation(): void
    {
        $service = $this->file('includes/admin-agent-phase4-remediation.php');
        $api = $this->file('api/admin/admin-agent-phase4.php');
        $matrix = $this->file('includes/admin-permission-matrix.php');
        self::assertStringContainsString("\$adapterKey!=='create_prevention_followup'", $service);
        self::assertStringContainsString("(string)\$learning['ops_status']!=='resolved'", $service);
        self::assertStringContainsString('mg_ops_review_save($pdo', $service);
        self::assertStringContainsString("\$expected='EXECUTE '.(string)\$execution['adapter_key']", $service);
        self::assertStringContainsString('status="running"', $service);
        self::assertStringContainsString('status="succeeded"', $service);
        self::assertStringContainsString("'admin.admin_agent.execute'=>[]", $api);
        self::assertStringContainsString("'admin.admin_agent.execute' => []", $matrix);
        self::assertStringContainsString("mg_admin_agent_phase4_api_require(\$actor,'admin.admin_agent.execute')", $api);
        foreach (['shell_exec(', 'proc_open(', 'passthru(', 'eval('] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $service);
        }
    }

    public function testPhase4ApiIsProtectedRateLimitedAndFailClosed(): void
    {
        $api = $this->file('api/admin/admin-agent-phase4.php');
        self::assertStringContainsString('mg_require_api_user()', $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase4.read'", $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase4.write'", $api);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
        self::assertStringContainsString('Main Admin Agent Phase 4 SQL migration is required.', $api);
        foreach (['maintenance_action', 'evaluate_change', 'learning_action', 'execute_action'] as $action) {
            self::assertStringContainsString("\$action==='" . $action . "'", $api);
        }
        self::assertStringContainsString('mg_admin_agent_phase3_state', $api);
        self::assertStringContainsString('$error->httpStatus()', $api);
    }

    public function testChatWorkspacePreservesEarlierPhasesAndAddsGovernanceControls(): void
    {
        $page = $this->file('admin/admin-agent.php');
        foreach (['agent-workspace-layout.css', 'personal-gifting-agent.css', 'merchant-agent-chat.css', 'mg-merchant-agent-composer'] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
        foreach ([
            '/api/admin/admin-agent-phase4.php',
            '/api/admin/admin-agent-phase4-stream.php',
            '/api/admin/admin-agent-phase3.php',
            '/api/admin/admin-agent-phase3-stream.php',
            '/api/admin/admin-agent-phase2.php',
            '/api/admin/admin-agent-phase2-stream.php',
        ] as $endpoint) {
            self::assertStringContainsString($endpoint, $page);
        }
        foreach ([
            'data-admin-agent-maintenance',
            'data-admin-agent-change-risks',
            'data-admin-agent-reliability',
            'data-admin-agent-capacity',
            'data-admin-agent-learning',
            'data-admin-agent-prevention',
        ] as $attribute) {
            self::assertStringContainsString($attribute, $page);
        }
        foreach ([
            'What changed?',
            'Maintenance window report',
            'Deployment change risk report',
            'Historical reliability scorecards',
            'Capacity forecast report',
            'Incident learning and postmortem report',
            'Prevention follow-up report',
            'Service topology report',
            'Anomaly report',
            'Deployment impact report',
            'Escalation report',
            'Executive summary',
            'Controlled remediation report',
        ] as $prompt) {
            self::assertStringContainsString($prompt, $page);
        }
        self::assertStringContainsString('Database-first · No AI credits', $page);
    }

    public function testClientSupportsGovernanceActionsSseAndPolling(): void
    {
        $client = $this->file('assets/js/admin-agent-phase4.js');
        foreach (['new EventSource', 'window.setInterval', '15000', 'renderMaintenance', 'renderRisks', 'renderReliability', 'renderCapacity', 'renderLearning', 'renderPrevention'] as $marker) {
            self::assertStringContainsString($marker, $client);
        }
        foreach ([
            "action:'maintenance_action'",
            "action:'evaluate_change'",
            "action:'learning_action'",
            "action:'request_action'",
            "action_key:'create_prevention_followup'",
        ] as $action) {
            self::assertStringContainsString($action, $client);
        }
        self::assertStringContainsString('Security and critical evidence will remain visible.', $client);
    }

    public function testSseIsBoundedPrivateAndReleasesSessionLock(): void
    {
        $stream = $this->file('api/admin/admin-agent-phase4-stream.php');
        foreach (['text/event-stream', 'Cache-Control: no-cache, no-store', 'X-Accel-Buffering', 'session_write_close', 'for($iteration=0;$iteration<8;$iteration++)', 'sleep(2)'] as $marker) {
            self::assertStringContainsString($marker, $stream);
        }
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase4.stream'", $stream);
        self::assertStringContainsString('mg_admin_agent_phase3_state', $stream);
    }

    public function testScheduledRunnerAndOperationsDocumentationExist(): void
    {
        $runner = $this->file('scripts/run_admin_agent_phase4.php');
        $docs = $this->file('docs/operations/main-admin-agent-phase4.md');
        self::assertStringContainsString("PHP_SAPI!=='cli'", $runner);
        self::assertStringContainsString('mg_admin_agent_phase4_run', $runner);
        self::assertStringContainsString('*/5 * * * *', $docs);
        self::assertStringContainsString('Do not run Phase 1, Phase 2, Phase 3, and Phase 4 runners together.', $docs);
        self::assertStringContainsString('EXECUTE create_prevention_followup', $docs);
        self::assertStringContainsString('evidence-ranked hypotheses, not proof', $docs);
        self::assertStringContainsString('No AI credits are consumed', $docs);
    }

    public function testMigrationManifestRegistersPhase4AfterPhase3(): void
    {
        $manifest = require $this->root . '/config/migrations.php';
        $files = $manifest['ordered_files'];
        $phase3 = array_search('20260719_main_admin_agent_phase3.sql', $files, true);
        $phase4 = array_search('20260719_main_admin_agent_phase4.sql', $files, true);
        self::assertIsInt($phase3);
        self::assertIsInt($phase4);
        self::assertSame($phase3 + 1, $phase4);
    }

    public function testPhase4UsesNoExternalModelOrArbitraryExecution(): void
    {
        foreach ([
            'includes/admin-agent-phase4.php',
            'includes/admin-agent-phase4-remediation.php',
            'api/admin/admin-agent-phase4.php',
            'api/admin/admin-agent-phase4-stream.php',
        ] as $path) {
            $source = $this->file($path);
            foreach (['mg_anthropic_messages(', 'mg_openai', 'curl_exec(', 'shell_exec(', 'proc_open(', 'eval('] as $unsafe) {
                self::assertStringNotContainsString($unsafe, $source);
            }
        }
    }
}
