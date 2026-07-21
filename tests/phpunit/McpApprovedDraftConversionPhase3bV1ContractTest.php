<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpApprovedDraftConversionPhase3bV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testReleaseManifestDeclaresHumanCreatedInactiveDraftBoundary(): void
    {
        $release = require $this->root . '/config/mcp_approved_draft_conversion_phase3b_release.php';
        self::assertSame('microgifter_mcp_approved_draft_conversion_phase3b_v1', $release['release_key']);
        self::assertSame('human_created_native_draft', $release['operation_ceiling']);
        self::assertContains('20260720_mcp_approved_draft_conversion_phase3b_v1', $release['required_migrations']);
        self::assertContains('source_draft_must_be_approved', $release['security']);
        self::assertContains('no_external_agent_conversion_tool', $release['security']);
        self::assertContains('no_commerce_execution', $release['boundaries']);
        self::assertContains('no_task_agent_execution_queue', $release['boundaries']);
        self::assertContains('no_mcp_automation_worker_queue', $release['boundaries']);
    }

    public function testMigrationFollowsPhase3AAndCreatesOnlyConversionEvidence(): void
    {
        $manifest = require $this->root . '/config/migrations.php';
        $phase3a = array_search('20260720_mcp_approval_gated_drafts_phase3a_v1.sql', $manifest['ordered_files'], true);
        $phase3b = array_search('20260720_mcp_approved_draft_conversion_phase3b_v1.sql', $manifest['ordered_files'], true);
        self::assertIsInt($phase3a);
        self::assertIsInt($phase3b);
        self::assertSame($phase3a + 1, $phase3b);

        $sql = file_get_contents($this->root . '/database/20260720_mcp_approved_draft_conversion_phase3b_v1.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('mcp_agent_draft_conversions', $sql);
        self::assertStringContainsString('mcp_agent_draft_conversion_events', $sql);
        self::assertStringContainsString("ENUM('prepared','created','opened','canceled')", $sql);
        self::assertStringNotContainsString('agent_workflow_actions', $sql);
        self::assertStringNotContainsString('mcp_automation_actions', $sql);
    }

    public function testOwnerConversionRequiresSeparateCsrfProtectedActions(): void
    {
        $page = (string)file_get_contents($this->root . '/includes/mcp-drafts/account-page-phase3b.php')
            . (string)file_get_contents($this->root . '/includes/mcp-drafts/account-page-phase3b-view.php');
        $open = file_get_contents($this->root . '/account-agent-draft-open.php');
        self::assertStringContainsString("value=\"prepare_conversion\"", $page);
        self::assertStringContainsString("value=\"create_native\"", $page);
        self::assertStringContainsString("value=\"cancel_conversion\"", $page);
        self::assertStringContainsString('mg_csrf_token()', $page);
        self::assertStringContainsString('REQUEST_METHOD', (string)$open);
        self::assertStringContainsString("!== 'POST'", (string)$open);
        self::assertStringContainsString('mg_verify_csrf', (string)$open);
        self::assertStringContainsString('mg_mcp_conversion_mark_opened', (string)$open);
    }

    public function testCanonicalConversionCreatesInactiveNativeDraftsOnly(): void
    {
        $authority = implode("\n", [
            (string)file_get_contents($this->root . '/includes/mcp-drafts/conversion.php'),
            (string)file_get_contents($this->root . '/includes/mcp-drafts/conversion-native-drafts.php'),
            (string)file_get_contents($this->root . '/includes/mcp-drafts/conversion-workflow.php'),
        ]);
        foreach ([
            "'private','draft'",
            "'crm.campaign_builder.draft'",
            "'crm.agent.message.draft.created'",
            "'draft',?",
            "'execution_enabled' => false",
            'mg_mcp_conversion_workspace',
            'mg_mcp_conversion_require_merchant_package',
        ] as $needle) {
            self::assertStringContainsString($needle, $authority);
        }
        self::assertStringNotContainsString("'crm.campaign_builder.launched'", $authority);
        self::assertStringNotContainsString("'crm.agent.message.sent'", $authority);
        self::assertStringNotContainsString('INSERT INTO agent_workflow_actions', $authority);
        self::assertStringNotContainsString('INSERT INTO mcp_automation_actions', $authority);
    }

    public function testExternalMcpStillHasNoConversionOrExecutionTool(): void
    {
        $tools = file_get_contents($this->root . '/services/mcp/src/tools/drafts.ts');
        self::assertIsString($tools);
        foreach (['convert', 'publish', 'send_message', 'purchase', 'activate_reward', 'launch_campaign'] as $forbidden) {
            self::assertStringNotContainsString('microgifter.' . $forbidden, $tools);
        }
    }
}
