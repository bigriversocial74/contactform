<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MainAdminAgentPhase1ContractTest extends TestCase
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

    public function testMigrationCreatesUnifiedMonitoringAndChatSchema(): void
    {
        $sql = $this->file('database/20260718_main_admin_agent_phase1.sql');
        foreach ([
            'admin_agent_monitors',
            'admin_agent_scans',
            'admin_agent_events',
            'admin_agent_findings',
            'admin_agent_finding_actions',
            'admin_agent_threads',
            'admin_agent_messages',
            'admin_agent_action_reviews',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_events_key', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_findings_key', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_admin_agent_action_reviews_idempotency', $sql);
        self::assertStringContainsString("'admin.admin_agent.view'", $sql);
        self::assertStringContainsString("'admin.admin_agent.chat'", $sql);
        self::assertStringContainsString("'admin.admin_agent.manage'", $sql);
        self::assertStringContainsString("'admin.admin_agent.actions'", $sql);
    }

    public function testMonitorRegistryCoversExistingAdminFoundations(): void
    {
        $sql = $this->file('database/20260718_main_admin_agent_phase1.sql');
        foreach ([
            'security_events',
            'audit_activity',
            'operations_incidents',
            'support_queue_sla',
            'notification_delivery',
            'automation_freshness',
            'ai_credit_accounting',
            'migration_readiness',
        ] as $monitor) {
            self::assertStringContainsString("'" . $monitor . "'", $sql);
        }
    }

    public function testRuntimeNormalizesEventsAndDeduplicatesFindings(): void
    {
        $service = $this->file('includes/admin-agent.php');
        $runtime = $this->file('includes/admin-agent-runtime.php');
        self::assertStringContainsString('function mg_admin_agent_event_key', $service);
        self::assertStringContainsString("hash('sha256'", $service);
        self::assertStringContainsString('INSERT IGNORE INTO admin_agent_events', $service);
        self::assertStringContainsString('function mg_admin_agent_finding_key', $service);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $service);
        self::assertStringContainsString('function mg_admin_agent_auto_resolve', $service);
        self::assertStringContainsString('reopened_by_monitor', $service);
        self::assertStringContainsString('mg_admin_agent_scan_runtime', $runtime);
        self::assertStringContainsString('$detectedKeys[] = $failure[\'key\']', $runtime);
        self::assertStringContainsString('array_reverse($rows)', $runtime);
        self::assertStringNotContainsString('[values]', $runtime);
    }

    public function testReportsAreDatabaseOnlyAndUseNoExternalModel(): void
    {
        $service = $this->file('includes/admin-agent.php');
        $runtime = $this->file('includes/admin-agent-runtime.php');
        foreach ([$service, $runtime] as $source) {
            self::assertStringNotContainsString('mg_anthropic_messages(', $source);
            self::assertStringNotContainsString('mg_openai', $source);
            self::assertStringNotContainsString('curl_exec(', $source);
        }
        self::assertStringContainsString("'database_only'=>true", $runtime);
        self::assertStringContainsString("'used_ai'=>false", $runtime);
        self::assertStringContainsString("'credits_used'=>0", $runtime);
    }

    public function testAdminChatPageMatchesCurrentAgentWorkspacePattern(): void
    {
        $page = $this->file('admin/admin-agent.php');
        self::assertStringContainsString('agent-workspace-layout.css', $page);
        self::assertStringContainsString('personal-gifting-agent.css', $page);
        self::assertStringContainsString('merchant-agent-chat.css', $page);
        self::assertStringContainsString('data-admin-agent-feed', $page);
        self::assertStringContainsString('data-admin-agent-form', $page);
        self::assertStringContainsString('mg-merchant-agent-composer', $page);
        self::assertStringContainsString('data-admin-agent-drawer', $page);
        self::assertStringContainsString('What changed?', $page);
        self::assertStringContainsString('Database-first · No AI credits', $page);
    }

    public function testLiveUpdatesUseSseWithBoundedPollingFallback(): void
    {
        $stream = $this->file('api/admin/admin-agent-stream.php');
        $client = $this->file('assets/js/admin-agent.js');
        self::assertStringContainsString('text/event-stream', $stream);
        self::assertStringContainsString('X-Accel-Buffering', $stream);
        self::assertStringContainsString('session_write_close', $stream);
        self::assertStringContainsString('for ($iteration = 0; $iteration < 8; $iteration++)', $stream);
        self::assertStringContainsString('mg_admin_agent_events_runtime', $stream);
        self::assertStringContainsString('new EventSource', $client);
        self::assertStringContainsString('setInterval', $client);
        self::assertStringContainsString('15000', $client);
    }

    public function testApiIsPermissionGatedRateLimitedAndCsrfProtected(): void
    {
        $api = $this->file('api/admin/admin-agent.php');
        self::assertStringContainsString('mg_require_api_user()', $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.read'", $api);
        self::assertStringContainsString("mg_rate_limit('admin.agent.write'", $api);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
        self::assertStringContainsString("'admin.admin_agent.view'", $api);
        self::assertStringContainsString("'admin.admin_agent.chat'", $api);
        self::assertStringContainsString("'admin.admin_agent.manage'", $api);
        self::assertStringContainsString("'admin.admin_agent.actions'", $api);
        self::assertStringContainsString('admin-agent-runtime.php', $api);
    }

    public function testActionsAreReviewOnlyAndIdempotent(): void
    {
        $service = $this->file('includes/admin-agent.php');
        $sql = $this->file('database/20260718_main_admin_agent_phase1.sql');
        self::assertStringContainsString('function mg_admin_agent_request_action', $service);
        self::assertStringContainsString('idempotency', $service);
        self::assertStringContainsString('"pending"', $service);
        self::assertStringContainsString("'review_required'=>true", $service);
        self::assertStringContainsString("'executed'=>false", $service);
        self::assertStringContainsString("status ENUM('pending','approved','rejected','executed','canceled')", $sql);
        self::assertStringNotContainsString('exec(', $service);
        self::assertStringNotContainsString('shell_exec(', $service);
    }

    public function testScheduledRunnerAndOperationsDocumentationExist(): void
    {
        $runner = $this->file('scripts/run_admin_agent_monitor_runtime.php');
        $docs = $this->file('docs/operations/main-admin-agent.md');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $runner);
        self::assertStringContainsString('mg_admin_agent_scan_runtime', $runner);
        self::assertStringContainsString('*/5 * * * *', $docs);
        self::assertStringContainsString('No AI credits', $docs);
        self::assertStringContainsString('review-gated', $docs);
    }

    public function testMigrationPermissionAndCommandCenterIntegrationAreRegistered(): void
    {
        $manifest = $this->file('config/migrations.php');
        $matrix = $this->file('includes/admin-permission-matrix.php');
        $command = $this->file('admin/operations-command.php');
        self::assertStringContainsString("'20260718_main_admin_agent_phase1.sql'", $manifest);
        self::assertStringContainsString("'admin.admin_agent'", $matrix);
        self::assertStringContainsString("'admin.admin_agent.view'", $matrix);
        self::assertStringContainsString('/admin/admin-agent.php', $command);
        self::assertStringContainsString('Open Main Admin Agent', $command);
    }
}
