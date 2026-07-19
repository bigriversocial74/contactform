<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MainAdminAgentPhase6ContractTest extends TestCase
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

    public function testMigrationCreatesFinalReadinessSchema(): void
    {
        $sql = $this->file('database/20260719_main_admin_agent_phase6.sql');
        foreach ([
            'admin_agent_phase6_settings',
            'admin_agent_scheduler_heartbeats',
            'admin_agent_continuity_alerts',
            'admin_agent_drill_schedules',
            'admin_agent_attestations',
            'admin_agent_readiness_checks',
            'admin_agent_continuity_brief_deliveries',
            'admin_agent_readiness_exports',
            'admin_agent_retention_previews',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        foreach (['continuity_alert', 'recovery_drill_due', 'recovery_objective_breach', 'continuity_digest', 'scheduler_missed'] as $type) {
            self::assertStringContainsString("'" . $type . "'", $sql);
        }
    }

    public function testMigrationRegistersPermissionsSettingsAndCanonicalKey(): void
    {
        $sql = $this->file('database/20260719_main_admin_agent_phase6.sql');
        foreach (['admin.admin_agent.readiness', 'admin.admin_agent.setup', 'admin.admin_agent.export'] as $permission) {
            self::assertStringContainsString("'" . $permission . "'", $sql);
        }
        self::assertStringContainsString('expected_runner_interval_minutes', $sql);
        self::assertStringContainsString('scorecard_retention_days', $sql);
        self::assertStringContainsString("'20260719_main_admin_agent_phase6'", $sql);
        self::assertStringContainsString("LEFT(REPLACE(UUID(),'-',''),26)", $sql);
    }

    public function testFinalReadinessEngineTracksSchedulerAlertsAndChecks(): void
    {
        $service = $this->file('includes/admin-agent-phase6.php');
        foreach ([
            'function mg_admin_agent_phase6_heartbeat_state',
            'function mg_admin_agent_phase6_evaluate_alerts',
            'function mg_admin_agent_phase6_evaluate_readiness',
            'function mg_admin_agent_phase6_check_upsert',
        ] as $function) {
            self::assertStringContainsString($function, $service);
        }
        foreach (['phase6_schema', 'analysis_current', 'scheduler_active', 'objectives_reviewed', 'plans_ready', 'evidence_current', 'critical_drills_verified', 'alerts_clear', 'alert_delivery_enabled'] as $check) {
            self::assertStringContainsString("'" . $check . "'", $service);
        }
        self::assertStringContainsString('admin_queue_notifications', $service);
        self::assertStringContainsString('scheduler_missed', $service);
    }

    public function testDrillCalendarBriefsAttestationsAndExportsAreDurable(): void
    {
        $service = $this->file('includes/admin-agent-phase6.php');
        foreach ([
            'function mg_admin_agent_phase6_sync_drill_schedules',
            'function mg_admin_agent_phase6_schedule_action',
            'function mg_admin_agent_phase6_attest',
            'function mg_admin_agent_phase6_generate_brief',
            'function mg_admin_agent_phase6_generate_export',
            'function mg_admin_agent_phase6_export_json',
        ] as $function) {
            self::assertStringContainsString($function, $service);
        }
        self::assertStringContainsString("'database_only' => true", $service);
        self::assertStringContainsString("'used_ai' => false", $service);
        self::assertStringContainsString("'credits_used' => 0", $service);
    }

    public function testRetentionIsPreviewOnly(): void
    {
        $service = $this->file('includes/admin-agent-phase6.php');
        $docs = $this->file('docs/operations/main-admin-agent-phase6.md');
        self::assertStringContainsString('function mg_admin_agent_phase6_retention_preview', $service);
        self::assertStringContainsString("'execution' => 'preview_only'", $service);
        self::assertStringNotContainsString('DELETE FROM admin_agent_', $service);
        self::assertStringContainsString('does not delete historical records automatically', $docs);
    }

    public function testBrowserEvidenceUploadIsProtectedAndMetadataOnly(): void
    {
        $upload = $this->file('api/admin/admin-agent-phase6-evidence-upload.php');
        foreach (['mg_require_api_user()', 'mg_rate_limit', 'mg_require_csrf_for_write', 'is_uploaded_file', '1024 * 1024', "pathinfo(\$name, PATHINFO_EXTENSION)", 'json_decode', "['run_id', 'status']", 'mg_admin_agent_phase5_record_backup_evidence'] as $marker) {
            self::assertStringContainsString($marker, $upload);
        }
        self::assertStringContainsString("'scope_key'] = 'database'", $upload);
        self::assertStringContainsString("'source_key'] = 'database_backup_restore_validator'", $upload);
        self::assertStringNotContainsString('move_uploaded_file', $upload);
    }

    public function testReadOnlyRouterDoesNotRunReadinessEvaluation(): void
    {
        $router = $this->file('api/admin/admin-agent-phase6-router.php');
        $readonly = $this->file('includes/admin-agent-phase6-readonly.php');
        self::assertStringContainsString("REQUEST_METHOD", $router);
        self::assertStringContainsString("require __DIR__ . '/admin-agent-phase6.php'", $router);
        self::assertStringContainsString('mg_admin_agent_phase6_state_readonly', $router);
        self::assertStringContainsString('mg_admin_agent_phase6_readiness_state', $readonly);
        self::assertStringContainsString('mg_admin_agent_phase6_latest_retention_preview', $readonly);
        self::assertStringNotContainsString('mg_admin_agent_phase6_evaluate_readiness(', $readonly);
        self::assertStringNotContainsString('mg_admin_agent_phase6_retention_preview(', $readonly);
    }

    public function testPhase6ApiIsProtectedAndPreservesEarlierActions(): void
    {
        $api = $this->file('api/admin/admin-agent-phase6.php');
        self::assertStringContainsString('mg_require_api_user()', $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase6.read'", $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase6.write'", $api);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
        self::assertStringContainsString("'admin.admin_agent.execute' => []", $api);
        foreach (['final_readiness', 'phase6_settings', 'continuity_alert_action', 'drill_schedule_action', 'attest', 'generate_continuity_brief', 'generate_readiness_export', 'load_readiness_export', 'retention_preview'] as $action) {
            self::assertStringContainsString("'" . $action . "'", $api);
        }
        foreach (['mg_admin_agent_phase5_send', 'mg_admin_agent_phase4_send', 'mg_admin_agent_phase3_send', 'mg_admin_agent_phase2_send'] as $fallback) {
            self::assertStringContainsString($fallback, $api);
        }
    }

    public function testWorkspaceIsBrowserDrivenAndPreservesPhaseOneThroughFive(): void
    {
        $page = $this->file('admin/admin-agent.php');
        foreach (['agent-workspace-layout.css', 'personal-gifting-agent.css', 'merchant-agent-chat.css', 'mg-merchant-agent-composer'] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
        foreach ([
            '/api/admin/admin-agent-phase6-router.php',
            '/api/admin/admin-agent-phase6.php',
            '/api/admin/admin-agent-phase6-stream.php',
            '/api/admin/admin-agent-phase6-evidence-upload.php',
            '/api/admin/admin-agent-phase5.php',
            '/api/admin/admin-agent-phase4.php',
            '/api/admin/admin-agent-phase3.php',
            '/api/admin/admin-agent-phase2.php',
        ] as $endpoint) {
            self::assertStringContainsString($endpoint, $page);
        }
        foreach (['Configured', 'Evidence Current', 'Drill Verified', 'Alerting Active', 'Production Ready', 'Run final readiness check', 'Upload validator JSON', 'Generate readiness export'] as $label) {
            self::assertStringContainsString($label, $page);
        }
        foreach (['What changed?', 'Recovery objective report', 'Maintenance window report', 'Service topology report', 'Anomaly report', 'Executive summary', 'Controlled remediation report'] as $prompt) {
            self::assertStringContainsString($prompt, $page);
        }
        self::assertStringContainsString('Database-first · No AI credits', $page);
    }

    public function testClientSupportsOneClickSetupUploadSseAndPolling(): void
    {
        $client = $this->file('assets/js/admin-agent-phase6.js');
        foreach (['runFinal', 'uploadEvidence', 'generateBrief', 'generateExport', 'downloadExport', 'configure', 'retention', 'new EventSource', 'window.setInterval', '15000'] as $marker) {
            self::assertStringContainsString($marker, $client);
        }
        self::assertStringContainsString("qa('[data-admin-agent-final-readiness]').forEach", $client);
        self::assertStringContainsString("action:'final_readiness'", $client);
        self::assertStringContainsString("action:'generate_readiness_export'", $client);
        self::assertStringContainsString('new FormData()', $client);
        self::assertStringContainsString('No data was deleted.', $client);
    }

    public function testScheduledRunnerRecordsCompletedHeartbeat(): void
    {
        $runner = $this->file('scripts/run_admin_agent_phase6.php');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $runner);
        self::assertStringContainsString('mg_admin_agent_phase6_run', $runner);
        self::assertStringContainsString('trigger_source', $runner);
        self::assertStringContainsString('"scheduled","succeeded"', $runner);
        self::assertStringContainsString('"scheduled","failed"', $runner);
        self::assertStringContainsString('mg_admin_agent_phase6_heartbeat_state', $runner);
    }

    public function testNotificationCenterRecognizesPhase6Types(): void
    {
        $api = $this->file('api/admin/notifications.php');
        foreach (['continuity_alert', 'recovery_drill_due', 'recovery_objective_breach', 'continuity_digest', 'scheduler_missed'] as $type) {
            self::assertStringContainsString("'" . $type . "'", $api);
        }
    }

    public function testOperationsGuideRequiresNoTerminalForRoutineUse(): void
    {
        $docs = $this->file('docs/operations/main-admin-agent-phase6.md');
        self::assertStringContainsString('Routine use does not require SSH, Bash, or terminal access.', $docs);
        self::assertStringContainsString('Browser activation', $docs);
        self::assertStringContainsString('Manual mode', $docs);
        self::assertStringContainsString('hosting control panel', $docs);
        self::assertStringContainsString('Upload validator JSON', $docs);
        self::assertStringContainsString('After successful production activation, GitHub issue #1201 can be closed.', $docs);
    }

    public function testMigrationManifestRegistersPhase6AfterPhase5(): void
    {
        $manifest = require $this->root . '/config/migrations.php';
        $files = $manifest['ordered_files'];
        $phase5 = array_search('20260719_main_admin_agent_phase5.sql', $files, true);
        $phase6 = array_search('20260719_main_admin_agent_phase6.sql', $files, true);
        self::assertIsInt($phase5);
        self::assertIsInt($phase6);
        self::assertSame($phase5 + 1, $phase6);
    }

    public function testPhase6UsesNoExternalModelCredits(): void
    {
        foreach (['includes/admin-agent-phase6.php', 'includes/admin-agent-phase6-readonly.php', 'api/admin/admin-agent-phase6.php', 'api/admin/admin-agent-phase6-router.php', 'api/admin/admin-agent-phase6-stream.php'] as $path) {
            $source = $this->file($path);
            self::assertStringNotContainsString('mg_anthropic_messages(', $source);
            self::assertStringNotContainsString('mg_openai', $source);
        }
        $service = $this->file('includes/admin-agent-phase6.php');
        self::assertStringContainsString("'database_only' => true", $service);
        self::assertStringContainsString("'used_ai' => false", $service);
        self::assertStringContainsString("'credits_used' => 0", $service);
    }
}
