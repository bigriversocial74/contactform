<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage18ProductionHardeningTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testSchemaDefinesIncidentsReleasesGatesRetentionAndChecks(): void
    {
        $sql = $this->read('database/stage_18_production_hardening_launch_readiness.sql');
        foreach (['operational_incidents','operational_incident_events','deployment_releases','release_gate_results','retention_policies','retention_runs','operational_check_results'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('stage_18_production_hardening_launch_readiness', $sql);
    }

    public function testProductionReleaseRequiresAllMandatoryGates(): void
    {
        $source = $this->read('api/operations/_operations.php');
        foreach (['composer_validate','php_syntax','ordered_migrations','clean_install','security_suite','phpunit','browser_smoke','backup_verified','rollback_verified','readiness_checks'] as $gate) {
            self::assertStringContainsString("'{$gate}'", $source);
        }
        self::assertStringContainsString('Production release gates cannot be waived.', $source);
        self::assertStringContainsString("status<>'passed'", $source);
    }

    public function testIncidentLifecycleIsExplicitAndAudited(): void
    {
        $source = $this->read('api/operations/_operations.php');
        foreach (['investigate','mitigate','resolve','close','reopen'] as $action) {
            self::assertStringContainsString("'{$action}'", $source);
        }
        self::assertStringContainsString('operational_incident_events', $source);
        self::assertStringContainsString('Incident cannot perform this transition.', $source);
    }

    public function testRetentionProcessorUsesStrictAllowlist(): void
    {
        $source = $this->read('scripts/run_retention.php');
        foreach (['security_logs','delivery_events','payment_webhook_events','agent_execution_events','agent_swarm_events'] as $table) {
            self::assertStringContainsString("'{$table}'", $source);
        }
        self::assertStringContainsString("\$policy['action_type']!=='delete'", $source);
        self::assertStringContainsString('DELETE FROM `{$table}`', $source);
    }

    public function testReadinessUsesAppliedMigrationsAndStage18OperationalTables(): void
    {
        $source = $this->read('scripts/validate_launch_readiness.php');
        foreach (['stage_12_universal_tips','stage_13_subscriptions_monetization','stage_14_posts_feed_social','stage_15_psr_demand_intelligence','stage_16_agent_execution_orchestration','stage_17_multi_agent_swarms','stage_18_production_hardening_launch_readiness'] as $migration) {
            self::assertStringContainsString("'{$migration}'", $source);
        }
        foreach (['operational_incidents','deployment_releases','release_gate_results','retention_policies','operational_check_results'] as $table) {
            self::assertStringContainsString("'{$table}'", $source);
        }
        self::assertStringContainsString('stage_migrations', $source);
        self::assertStringContainsString('operations_tables', $source);
        self::assertStringContainsString('critical_incidents', $source);
        self::assertStringContainsString('payment_webhooks', $source);
    }

    public function testAdminEndpointsRequireOperationsPermissionsAndCsrf(): void
    {
        $incidents = $this->read('api/admin/operations-incidents.php');
        self::assertStringContainsString("mg_require_permission('operations.incidents.manage')", $incidents);
        self::assertStringContainsString('mg_require_csrf_for_write(', $incidents);
        $releases = $this->read('api/admin/operations-releases.php');
        self::assertStringContainsString("mg_require_permission('operations.releases.manage')", $releases);
        self::assertStringContainsString('mg_require_csrf_for_write(', $releases);
    }

    public function testStage18DoesNotCreateParallelBusinessAuthorities(): void
    {
        foreach (['api/operations/_operations.php','api/admin/operations-incidents.php','api/admin/operations-releases.php','scripts/run_retention.php'] as $path) {
            $source = $this->read($path);
            self::assertStringNotContainsString('INSERT INTO ledger_entries', $source);
            self::assertStringNotContainsString('UPDATE wallets', $source);
            self::assertStringNotContainsString('UPDATE microgift_instances', $source);
            self::assertStringNotContainsString('UPDATE entitlements', $source);
        }
    }
}
