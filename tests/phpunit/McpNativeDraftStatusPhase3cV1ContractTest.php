<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class McpNativeDraftStatusPhase3cV1ContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    public function testReleaseManifest(): void
    {
        $release = require $this->root . '/config/mcp_native_draft_status_phase3c_release.php';
        self::assertSame('read_only_native_status', $release['operation_ceiling']);
        self::assertSame([], $release['required_migrations']);
        self::assertContains('existing_draft_read_enrichment', $release['capabilities']);
    }
    public function testNoNewSqlAndExistingLedger(): void
    {
        self::assertFileDoesNotExist($this->root . '/database/20260721_mcp_native_draft_status_phase3c_v1.sql');
        $authority = (string)file_get_contents($this->root . '/includes/mcp-drafts/native-status/event-ledger.php');
        self::assertStringContainsString('INSERT INTO events', $authority);
        self::assertStringContainsString('mcp.agent_draft.native_status.changed', $authority);
    }
    public function testAuthorityIsReadOnly(): void
    {
        $files = glob($this->root . '/includes/mcp-drafts/native-status/*.php') ?: [];
        $authority = implode("\n", array_map(static fn(string $file): string => (string)file_get_contents($file), $files));
        self::assertStringContainsString('FROM gifts', $authority);
        self::assertStringContainsString('FROM campaign_events', $authority);
        self::assertStringContainsString("'reward' . '_templates'", $authority);
        self::assertStringNotContainsString('UPDATE gifts', $authority);
        self::assertStringNotContainsString('UPDATE reward_templates', $authority);
        self::assertStringNotContainsString('INSERT INTO campaign_events', $authority);
        self::assertStringNotContainsString('INSERT INTO agent_workflow_actions', $authority);
        self::assertStringNotContainsString('INSERT INTO mcp_automation_actions', $authority);
    }
    public function testOwnerRefreshAndBridgeEnrichment(): void
    {
        $actions = (string)file_get_contents($this->root . '/includes/mcp-drafts/native-status/account-actions.php');
        self::assertStringContainsString('refresh_status', $actions);
        self::assertStringContainsString('mg_verify_csrf', $actions);
        $bridge = (string)file_get_contents($this->root . '/api/internal/mcp-bridge.php');
        self::assertStringContainsString("['handoff']", $bridge);
        self::assertStringContainsString('mg_mcp_native_status_attach_connection_drafts', $bridge);
    }
}
