<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MainAdminAgentPhase5ContractTest extends TestCase
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

    public function testMigrationCreatesRecoveryAssuranceSchema(): void
    {
        $sql = $this->file('database/20260719_main_admin_agent_phase5.sql');
        foreach (['admin_agent_recovery_objectives','admin_agent_backup_evidence','admin_agent_restore_drills','admin_agent_recovery_plans','admin_agent_recovery_gaps','admin_agent_continuity_scorecards'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        foreach (['uq_admin_agent_recovery_objectives_key','uq_admin_agent_backup_evidence_key','uq_admin_agent_restore_drills_key','uq_admin_agent_recovery_plans_key','uq_admin_agent_recovery_gaps_key','uq_admin_agent_continuity_scorecards_key'] as $key) {
            self::assertStringContainsString($key, $sql);
        }
    }

    public function testMigrationRegistersPermissionsAdapterAndCanonicalKey(): void
    {
        $sql = $this->file('database/20260719_main_admin_agent_phase5.sql');
        foreach (['admin.admin_agent.continuity','admin.admin_agent.recovery','admin.admin_agent.evidence'] as $permission) {
            self::assertStringContainsString("'" . $permission . "'", $sql);
        }
        self::assertStringContainsString("'approve_recovery_drill_record'", $sql);
        self::assertStringContainsString("'01ADMINAGENTP5ADAPTER00001'", $sql);
        self::assertStringContainsString("'20260719_main_admin_agent_phase5'", $sql);
        self::assertStringContainsString("LEFT(REPLACE(UUID(),'-',''),26)", $sql);
        self::assertStringNotContainsString('permission_catalog', $sql);
    }

    public function testRecoveryObjectivesTrackRtoRpoFreshnessAndDrillCadence(): void
    {
        $service = $this->file('includes/admin-agent-phase5.php');
        self::assertStringContainsString('function mg_admin_agent_phase5_objectives', $service);
        self::assertStringContainsString('function mg_admin_agent_phase5_objective_action', $service);
        foreach (['rto_minutes','rpo_minutes','backup_max_age_minutes','drill_interval_days','criticality'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString("['low', 'medium', 'high', 'critical']", $service);
        self::assertStringContainsString("['active', 'needs_review', 'retired']", $service);
    }

    public function testEvidenceIngestionStoresValidationMetadataAndFiltersSensitiveKeys(): void
    {
        $service = $this->file('includes/admin-agent-phase5.php');
        $importer = $this->file('scripts/record_admin_agent_recovery_evidence.php');
        self::assertStringContainsString('function mg_admin_agent_phase5_record_backup_evidence', $service);
        foreach (['run_id','backup_sha256','source_table_count','restore_table_count','source_migration_count','restore_migration_count','canary_verified','manifest_verified','migration_status_verified'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        self::assertStringContainsString('forbidden', $service);
        self::assertStringContainsString('--file=', $importer);
        self::assertStringContainsString('json_decode', $importer);
    }

    public function testExistingIsolatedValidatorRemainsTheSourceProcedure(): void
    {
        $validator = $this->file('scripts/validate_database_backup_restore.sh');
        $docs = $this->file('docs/operations/main-admin-agent-phase5.md');
        foreach (['--single-transaction','verify_backup_checksum','create_isolated_restore_database','verify_restored_counts_and_canary','verify_restored_migration_readiness'] as $marker) {
            self::assertStringContainsString($marker, $validator);
        }
        self::assertStringContainsString('scripts/validate_database_backup_restore.sh', $docs);
        self::assertStringContainsString('record_admin_agent_recovery_evidence.php', $docs);
    }

    public function testDrillLifecycleRequiresExternalPassingEvidenceBeforeReview(): void
    {
        $service = $this->file('includes/admin-agent-phase5.php');
        self::assertStringContainsString('function mg_admin_agent_phase5_drill_action', $service);
        self::assertStringContainsString('executed_externally', $service);
        self::assertStringContainsString("'review_ready'", $service);
        self::assertStringContainsString("(string) \$evidence['status'] === 'passed'", $service);
        self::assertStringContainsString("(bool) \$evidence['canary_verified']", $service);
        self::assertStringContainsString('actual_rto_minutes', $service);
        self::assertStringContainsString('actual_rpo_minutes', $service);
    }

    public function testRecoveryPlansPreserveOrderDependenciesValidationAndRunbook(): void
    {
        $service = $this->file('includes/admin-agent-phase5.php');
        $sql = $this->file('database/20260719_main_admin_agent_phase5.sql');
        self::assertStringContainsString('function mg_admin_agent_phase5_plans', $service);
        self::assertStringContainsString('function mg_admin_agent_phase5_plan_action', $service);
        foreach (['recovery_order','prerequisites_json','validation_steps_json','runbook_path','last_reviewed_at'] as $marker) {
            self::assertStringContainsString($marker, $sql);
        }
        self::assertStringContainsString('docs/operations/UPGRADE_ROLLBACK_RESTORE_RUNBOOK.md', $sql);
    }

    public function testContinuityEvaluatorCreatesDurableGapTypesAndScorecards(): void
    {
        $service = $this->file('includes/admin-agent-phase5.php');
        self::assertStringContainsString('function mg_admin_agent_phase5_evaluate_continuity', $service);
        self::assertStringContainsString('function mg_admin_agent_phase5_gap_upsert', $service);
        foreach (['missing_objective','stale_backup','failed_backup','missing_drill','overdue_drill','rto_miss','rpo_miss','missing_plan','plan_review','evidence_incomplete'] as $type) {
            self::assertStringContainsString("'gap_type' => '" . $type . "'", $service);
        }
        self::assertStringContainsString('admin_agent_continuity_scorecards', $service);
        self::assertStringContainsString('occurrence_count=occurrence_count+1', $service);
        self::assertStringContainsString('resolved","dismissed', $service);
    }

    public function testDrillApprovalIsReviewGatedEvidenceOnlyAndTyped(): void
    {
        $service = $this->file('includes/admin-agent-phase5-remediation.php');
        $api = $this->file('api/admin/admin-agent-phase5.php');
        self::assertStringContainsString('approve_recovery_drill_record', $service);
        self::assertStringContainsString('EXECUTE ', $service);
        self::assertStringContainsString('review_ready', $service);
        self::assertStringContainsString('evidence_status', $service);
        self::assertStringContainsString('canary_verified', $service);
        self::assertStringContainsString('manifest_verified', $service);
        self::assertStringContainsString('migration_status_verified', $service);
        self::assertStringContainsString("'admin.admin_agent.execute' => []", $api);
        self::assertStringContainsString("mg_admin_agent_phase5_api_require(\$actor, 'admin.admin_agent.execute')", $api);
        self::assertStringContainsString("\$execution['execution_mode'] !== 'in_process'", $service);
    }

    public function testPhase5ApiIsProtectedRateLimitedCsrfGatedAndFailClosed(): void
    {
        $api = $this->file('api/admin/admin-agent-phase5.php');
        self::assertStringContainsString('mg_require_api_user()', $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase5.read'", $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase5.write'", $api);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
        self::assertStringContainsString('Main Admin Agent Phase 5 SQL migration is required.', $api);
        foreach (['objective_action','record_backup_evidence','drill_action','plan_action','gap_action','execute_action'] as $action) {
            self::assertStringContainsString("\$action === '" . $action . "'", $api);
        }
        self::assertStringContainsString('mg_admin_agent_phase4_state', $api);
        self::assertStringContainsString('$error->httpStatus()', $api);
    }

    public function testWorkspacePreservesEarlierPhasesAndAddsRecoveryControls(): void
    {
        $page = $this->file('admin/admin-agent.php');
        foreach (['agent-workspace-layout.css','personal-gifting-agent.css','merchant-agent-chat.css','mg-merchant-agent-composer'] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
        foreach (['/api/admin/admin-agent-phase5.php','/api/admin/admin-agent-phase5-stream.php','/api/admin/admin-agent-phase4.php','/api/admin/admin-agent-phase4-stream.php','/api/admin/admin-agent-phase3.php','/api/admin/admin-agent-phase3-stream.php','/api/admin/admin-agent-phase2.php','/api/admin/admin-agent-phase2-stream.php'] as $endpoint) {
            self::assertStringContainsString($endpoint, $page);
        }
        foreach (['data-admin-agent-recovery-objectives','data-admin-agent-backup-evidence','data-admin-agent-restore-drills','data-admin-agent-recovery-plans','data-admin-agent-continuity-scorecards','data-admin-agent-recovery-gaps'] as $attribute) {
            self::assertStringContainsString($attribute, $page);
        }
        foreach (['What changed?','Recovery objective report','Backup evidence report','Restore drill report','Recovery plan report','Business continuity scorecards','Recovery gap report','Maintenance window report','Service topology report','Anomaly report','Executive summary','Controlled remediation report'] as $prompt) {
            self::assertStringContainsString($prompt, $page);
        }
        self::assertStringContainsString('Database-first · No AI credits', $page);
    }

    public function testClientSupportsRecoveryActionsSseAndPolling(): void
    {
        $client = $this->file('assets/js/admin-agent-phase5.js');
        foreach (['new EventSource','window.setInterval','15000','renderObjectives','renderEvidence','renderDrills','renderPlans','renderScorecards','renderGaps'] as $marker) {
            self::assertStringContainsString($marker, $client);
        }
        foreach (["action:'objective_action'","action:'drill_action'","action:'plan_action'","action:'gap_action'","action:'request_action'","action_key:'approve_recovery_drill_record'"] as $action) {
            self::assertStringContainsString($action, $client);
        }
        self::assertStringContainsString('No recovery operation was executed.', $client);
    }

    public function testSseIsBoundedPrivateAndReleasesSessionLock(): void
    {
        $stream = $this->file('api/admin/admin-agent-phase5-stream.php');
        foreach (['text/event-stream','Cache-Control: no-cache, no-store','X-Accel-Buffering','session_write_close','for ($iteration = 0; $iteration < 8; $iteration++)','sleep(2)'] as $marker) {
            self::assertStringContainsString($marker, $stream);
        }
        self::assertStringContainsString("mg_rate_limit('admin.agent.phase5.stream'", $stream);
        self::assertStringContainsString('mg_admin_agent_phase4_state', $stream);
    }

    public function testRunnerImporterAndOperationsDocumentationExist(): void
    {
        $runner = $this->file('scripts/run_admin_agent_phase5.php');
        $importer = $this->file('scripts/record_admin_agent_recovery_evidence.php');
        $docs = $this->file('docs/operations/main-admin-agent-phase5.md');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $runner);
        self::assertStringContainsString('mg_admin_agent_phase5_run', $runner);
        self::assertStringContainsString('mg_admin_agent_phase5_record_backup_evidence', $importer);
        self::assertStringContainsString('*/5 * * * *', $docs);
        self::assertStringContainsString('Do not run Phase 1, Phase 2, Phase 3, Phase 4, and Phase 5 runners together.', $docs);
        self::assertStringContainsString('EXECUTE approve_recovery_drill_record', $docs);
        self::assertStringContainsString('No AI credits are consumed', $docs);
    }

    public function testMigrationManifestRegistersPhase5AfterPhase4(): void
    {
        $manifest = require $this->root . '/config/migrations.php';
        $files = $manifest['ordered_files'];
        $phase4 = array_search('20260719_main_admin_agent_phase4.sql', $files, true);
        $phase5 = array_search('20260719_main_admin_agent_phase5.sql', $files, true);
        self::assertIsInt($phase4);
        self::assertIsInt($phase5);
        self::assertSame($phase4 + 1, $phase5);
    }

    public function testPhase5IsDatabaseOnlyAndDelegatesOlderAdapters(): void
    {
        $service = $this->file('includes/admin-agent-phase5.php');
        $remediation = $this->file('includes/admin-agent-phase5-remediation.php');
        self::assertStringContainsString("'database_only' => true", $service);
        self::assertStringContainsString("'used_ai' => false", $service);
        self::assertStringContainsString("'credits_used' => 0", $service);
        self::assertStringContainsString('mg_admin_agent_phase4_execute_adapter', $remediation);
        self::assertStringContainsString('in_process', $remediation);
    }
}
