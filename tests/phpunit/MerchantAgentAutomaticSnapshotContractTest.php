<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentAutomaticSnapshotContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testWorkspaceLoadsSnapshotWithoutMerchantPrompt(): void
    {
        $page = file_get_contents($this->root . '/merchant-agent-chat.php');
        $js = file_get_contents($this->root . '/assets/js/merchant-agent-automatic-snapshot.js');
        self::assertStringContainsString('data-merchant-agent-latest-snapshot', $page);
        self::assertStringContainsString("load(false);", $js);
        self::assertStringContainsString("Microgifter.get('/api/ai/merchant-agent-snapshot.php?days=30')", $js);
    }

    public function testSnapshotIsSystemFirstAndDoesNotCallAnthropic(): void
    {
        $helper = file_get_contents($this->root . '/includes/ai/merchant-agent-automatic-snapshot.php');
        $endpoint = file_get_contents($this->root . '/api/ai/merchant-agent-snapshot.php');
        self::assertStringContainsString("'system_generated'=>true", $helper);
        self::assertStringContainsString("'ai_used'=>false", $helper);
        self::assertStringNotContainsString('mg_anthropic_messages', $helper . $endpoint);
        self::assertStringNotContainsString('mg_merchant_agent_ai_begin_call', $helper . $endpoint);
        self::assertStringNotContainsString('mg_ai_credit_preflight', $helper . $endpoint);
    }

    public function testSnapshotRemainsOwnerScopedAndCsrfProtectedForRefresh(): void
    {
        $endpoint = file_get_contents($this->root . '/api/ai/merchant-agent-snapshot.php');
        self::assertStringContainsString('mg_merchant_agent_require_owner_access', $endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write', $endpoint);
        self::assertStringContainsString("'workspace_load'", $endpoint);
        self::assertStringContainsString("'manual_refresh'", $endpoint);
    }

    public function testSnapshotHasFreshnessAndHistoryStorage(): void
    {
        $sql = file_get_contents($this->root . '/database/20260718_merchant_agent_automatic_snapshots_v1.sql');
        $helper = file_get_contents($this->root . '/includes/ai/merchant-agent-automatic-snapshot.php');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS merchant_agent_snapshots', $sql);
        self::assertStringContainsString('generated_at', $sql);
        self::assertStringContainsString('expires_at', $sql);
        self::assertStringContainsString('time() + 21600', $helper);
        self::assertStringContainsString('ORDER BY generated_at DESC,id DESC LIMIT 1', $helper);
    }
}