<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpApprovalGatedDraftsPhase3aV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testReleaseManifestDeclaresReviewOnlyBoundary(): void
    {
        $release = require $this->root . '/config/mcp_approval_gated_drafts_phase3a_release.php';
        self::assertSame('microgifter_mcp_approval_gated_drafts_phase3a_v1', $release['release_key']);
        self::assertSame('draft', $release['operation_ceiling']);
        self::assertSame(['gift', 'campaign', 'reward', 'message'], $release['draft_types']);
        self::assertContains('no_task_agent_execution_queue_link', $release['boundaries']);
        self::assertContains('no_mcp_automation_worker_queue_link', $release['boundaries']);
        self::assertContains('execution_enabled_false_in_all_projections', $release['security']);
    }

    public function testMigrationIsCanonicalAndHasNoExecutionQueueForeignKeys(): void
    {
        $manifest = require $this->root . '/config/migrations.php';
        $oauth = array_search('20260720_mcp_external_agent_authorization_phase2a_v1.sql', $manifest['ordered_files'], true);
        $drafts = array_search('20260720_mcp_approval_gated_drafts_phase3a_v1.sql', $manifest['ordered_files'], true);
        self::assertIsInt($oauth);
        self::assertIsInt($drafts);
        self::assertSame($oauth + 1, $drafts);

        $sql = (string)file_get_contents($this->root . '/database/20260720_mcp_approval_gated_drafts_phase3a_v1.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_agent_drafts', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS mcp_agent_draft_events', $sql);
        self::assertStringContainsString("ENUM('pending_review','approved','rejected','canceled','expired')", $sql);
        self::assertStringContainsString("('reward:draft'", $sql);
        self::assertStringNotContainsString('REFERENCES agent_workflow_actions', $sql);
        self::assertStringNotContainsString('REFERENCES agent_workflow_runs', $sql);
        self::assertStringNotContainsString('REFERENCES mcp_automation_actions', $sql);
        self::assertStringNotContainsString('REFERENCES mcp_automation_runs', $sql);
    }

    public function testPhpAuthorityIsIdempotentOwnerScopedAndExecutionDisabled(): void
    {
        $repository = (string)file_get_contents($this->root . '/includes/mcp-drafts/repository.php');
        $bridge = (string)file_get_contents($this->root . '/api/internal/_mcp_draft_bridge.php');
        self::assertStringContainsString('uq_mcp_agent_drafts_connection_idempotency', (string)file_get_contents($this->root . '/database/20260720_mcp_approval_gated_drafts_phase3a_v1.sql'));
        self::assertStringContainsString("'enabled' => false", $repository);
        self::assertStringContainsString("'status' => 'not_enabled'", $repository);
        self::assertStringContainsString('d.owner_user_id=?', $repository);
        self::assertStringContainsString('mg_mcp_draft_require_context', $bridge);
        self::assertStringContainsString("str_starts_with(\$operation, 'draft.')", (string)file_get_contents($this->root . '/api/internal/mcp-bridge.php'));
    }

    public function testNodeRegistryContainsDraftToolsAndNoExecutionTools(): void
    {
        $draftTools = (string)file_get_contents($this->root . '/services/mcp/src/tools/drafts.ts');
        foreach ([
            'microgifter.gift.create_draft',
            'microgifter.campaign.create_draft',
            'microgifter.reward.create_draft',
            'microgifter.message.create_draft',
            'microgifter.drafts.list',
            'microgifter.drafts.get',
            'microgifter.drafts.cancel',
        ] as $tool) self::assertStringContainsString($tool, $draftTools);
        self::assertStringNotContainsString('publish_campaign', $draftTools);
        self::assertStringNotContainsString('send_message', $draftTools);
        self::assertStringNotContainsString('purchase_gift', $draftTools);
        self::assertStringNotContainsString('execute_draft', $draftTools);
    }

    public function testOwnerReviewPageUsesSharedShellAndCsrf(): void
    {
        $page = implode("\n", [
            (string)file_get_contents($this->root . '/includes/mcp-drafts/account-page.php'),
            (string)file_get_contents($this->root . '/includes/mcp-drafts/account-page-phase3b.php'),
            (string)file_get_contents($this->root . '/includes/mcp-drafts/account-page-phase3b-view.php'),
        ]);
        self::assertStringContainsString('account-page-phase3b.php', $page);
        self::assertStringContainsString('mg-app-shell', $page);
        self::assertStringContainsString("require dirname(__DIR__) . '/agent-sidebar.php'", $page);
        self::assertStringContainsString('mg_verify_csrf', $page);
        self::assertStringContainsString('mg_mcp_draft_owner_decide', $page);
        self::assertStringContainsString('No conversion available', $page);
    }

    public function testDynamicRegistrationStaysReadOnly(): void
    {
        $clients = (string)file_get_contents($this->root . '/includes/mcp-oauth/clients.php');
        self::assertStringContainsString("\$registrationType === 'dynamic'", $clients);
        self::assertStringContainsString("? 'read'", $clients);
        self::assertStringContainsString('maximum_operation_class', (string)file_get_contents($this->root . '/admin/mcp-oauth-clients.php'));
    }
}
