<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignMcpBoundedPlaybooksV13dContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        return (string)file_get_contents($this->root . '/' . $path);
    }

    private function playbookService(): string
    {
        $files = glob($this->root . '/includes/mcp-creator-campaign-playbooks/*.php') ?: [];
        return implode("\n", array_map(static fn(string $file): string => (string)file_get_contents($file), $files));
    }

    public function testToolAndScopeCatalogsAreExact(): void
    {
        $tools = $this->read('services/mcp/src/tools/creatorCampaignPlaybooks.ts');
        $sql = $this->read('database/20260722_creator_campaign_mcp_bounded_playbooks_v13d_single_install.sql');
        $names = [
            'microgifter.creator_campaigns.playbooks.campaign_preparation.run',
            'microgifter.creator_campaigns.playbooks.application_review.run',
            'microgifter.creator_campaigns.playbooks.content_review.run',
            'microgifter.creator_campaigns.playbooks.campaign_health.run',
            'microgifter.creator_campaigns.playbooks.earnings_review.run',
            'microgifter.creator_campaigns.playbooks.creator_outreach.run',
        ];
        foreach ($names as $name) {
            self::assertStringContainsString($name, $tools);
        }
        self::assertSame(6, substr_count($sql, "'draft',1,1,NOW(),NOW())"));
        self::assertStringNotContainsString("'bounded_auto'", $sql);
        self::assertStringNotContainsString("'prohibited'", $sql);
    }

    public function testRunsRequireAnActiveMatchingOwnerDefinitionAndGrant(): void
    {
        $service = $this->playbookService();
        self::assertStringContainsString('mg_mcp_automation_lock_owner_definition', $service);
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_PLAYBOOK_CONNECTION_MISMATCH', $service);
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_PLAYBOOK_DEFINITION_MISMATCH', $service);
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_PLAYBOOK_WORKSPACE_MISMATCH', $service);
        self::assertStringContainsString('mg_mcp_automation_assert_grant_activatable', $service);
        self::assertStringContainsString('mg_mcp_automation_authorize_grant_action', $service);
        self::assertStringContainsString("trigger_type='manual'", $service);
    }

    public function testEachRunCreatesOnlyAReviewArtifactAndEvidence(): void
    {
        $service = $this->playbookService();
        self::assertStringContainsString('INSERT INTO mcp_agent_drafts', $service);
        self::assertStringContainsString('INSERT INTO mcp_automation_runs', $service);
        self::assertStringContainsString('INSERT INTO mcp_automation_actions', $service);
        self::assertStringContainsString('INSERT INTO mcp_action_receipts', $service);
        self::assertStringContainsString("'creator_campaign_playbook_output' => true", $service);
        self::assertStringContainsString("'native_conversion_enabled' => false", $service);
        self::assertStringContainsString("'canonical_action_request_created' => false", $service);
        self::assertStringContainsString("'canonical_mutation_enabled' => false", $service);
        self::assertStringContainsString("'external_effects' => false", $service);
        self::assertStringNotContainsString('mg_mcp_creator_campaign_action_request(', $service);
        self::assertStringNotContainsString('mg_creator_campaign_transition_status(', $service);
        self::assertStringNotContainsString('mg_creator_campaign_payout_create(', $service);
    }

    public function testPlaybookSpecificSafetyBoundariesArePresent(): void
    {
        $service = $this->playbookService();
        self::assertStringContainsString("'publication_enabled' => false", $service);
        self::assertStringContainsString("'application_decision_enabled' => false", $service);
        self::assertStringContainsString("'submission_decision_enabled' => false", $service);
        self::assertStringContainsString("'earning_decision_enabled' => false", $service);
        self::assertStringContainsString("'payout_record_enabled' => false", $service);
        self::assertStringContainsString("'payment_provider_enabled' => false", $service);
        self::assertStringContainsString("'invitation_send_enabled' => false", $service);
        self::assertStringContainsString("um.code='creator'", $service);
        self::assertStringContainsString("cp.status='active'", $service);
    }

    public function testMcpToolsAreDraftOnlyAndFailClosed(): void
    {
        $tools = $this->read('services/mcp/src/tools/creatorCampaignPlaybooks.ts');
        $bridge = $this->read('services/mcp/src/bridge/canonicalBridge.ts');
        $registry = $this->read('services/mcp/src/tools/registry.ts');
        self::assertStringContainsString('connection.maximumOperationClass === "draft"', $tools);
        self::assertStringContainsString('runCreatorCampaignPlaybook', $tools);
        self::assertStringContainsString('operationClass: "draft"', $tools);
        self::assertStringContainsString('MICROGIFTER_TOOL_DISABLED', $tools);
        self::assertStringContainsString('creator_campaign_playbooks.run', $bridge);
        self::assertStringContainsString('registerCreatorCampaignPlaybookTools', $registry);
    }

    public function testLegacyPhase4bSimulationBoundaryRemainsUnchanged(): void
    {
        $simulation = $this->read('includes/mcp-automations/simulations.php');
        $view = $this->read('includes/mcp-automations/definitions-page-view.php');
        self::assertStringContainsString("'execution_attempted' => false", $simulation);
        self::assertStringContainsString("'action_receipts_created' => 0", $simulation);
        self::assertStringNotContainsString('INSERT INTO mcp_action_receipts', $simulation);
        self::assertStringContainsString('Simulation-only deployment state', $view);
        self::assertStringContainsString('No scheduler or canonical action path exists in Phase 4B', $view);
    }
}
