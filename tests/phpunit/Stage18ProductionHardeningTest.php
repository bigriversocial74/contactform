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

    public function testReadinessUsesCanonicalManifestOperationsAndStripeAuthorities(): void
    {
        $source = $this->read('scripts/validate_launch_readiness.php');
        self::assertStringContainsString("require_once dirname(__DIR__) . '/includes/migrations.php'", $source);
        self::assertStringContainsString("require_once dirname(__DIR__) . '/api/payments/_readiness.php'", $source);
        self::assertStringContainsString('mg_migration_status($pdo)', $source);
        foreach (['operational_incidents','deployment_releases','release_gate_results','retention_policies','operational_check_results','payment_platform_credentials','payment_provider_accounts','payment_webhook_events'] as $table) {
            self::assertStringContainsString("'{$table}'", $source);
        }
        foreach (['stage_migrations','operations_tables','stripe_platform','stripe_selling_merchants','critical_incidents','payment_webhooks'] as $check) {
            self::assertStringContainsString("'{$check}'", $source);
        }
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
