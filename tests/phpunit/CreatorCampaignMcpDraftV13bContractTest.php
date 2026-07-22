<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignMcpDraftV13bContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testProposalToolCatalogAndScopesAreComplete(): void
    {
        $tools = (string)file_get_contents($this->root . '/services/mcp/src/tools/creatorCampaignDrafts.ts');
        $sql = (string)file_get_contents($this->root . '/database/20260722_creator_campaign_mcp_draft_scopes_v13b_single_install.sql');
        $names = [
            'microgifter.creator_campaigns.draft.create','microgifter.creator_campaigns.draft.update',
            'microgifter.creator_campaigns.products.propose','microgifter.creator_campaigns.eligibility.propose',
            'microgifter.creator_campaigns.deliverables.propose','microgifter.creator_campaigns.compensation.propose',
            'microgifter.creator_campaigns.attribution.propose','microgifter.creator_campaigns.budget.propose',
            'microgifter.creator_campaigns.rights.propose','microgifter.creator_campaigns.terms.propose',
            'microgifter.creator_campaigns.invitation.draft','microgifter.creator_campaigns.message.draft',
            'microgifter.creator_campaigns.submission_feedback.draft',
        ];
        foreach ($names as $name) self::assertStringContainsString($name, $tools);
        self::assertSame(12, substr_count($sql, "'draft',1,1,NOW(),NOW())"));
        self::assertStringNotContainsString("'approval_gated'", $sql);
        self::assertStringNotContainsString("'bounded_auto'", $sql);
        self::assertStringNotContainsString("'prohibited'", $sql);
    }

    public function testProposalWritesOnlyToTheReviewLedger(): void
    {
        $bridge = (string)file_get_contents($this->root . '/api/internal/_mcp_creator_campaign_draft_bridge.php');
        $draftBridge = (string)file_get_contents($this->root . '/api/internal/_mcp_draft_bridge.php');
        self::assertStringContainsString('INSERT INTO mcp_agent_drafts', $bridge);
        self::assertStringContainsString('mg_mcp_draft_event', $bridge);
        self::assertStringContainsString('mg_mcp_creator_campaign_proposal_requested', $draftBridge);
        self::assertStringContainsString('mg_mcp_creator_campaign_proposal_create', $draftBridge);
        self::assertStringNotContainsString('INSERT INTO creator_campaigns', $bridge);
        self::assertStringNotContainsString('UPDATE creator_campaigns', $bridge);
        self::assertStringNotContainsString('INSERT INTO creator_campaign_earning', $bridge);
        self::assertStringNotContainsString('INSERT INTO creator_campaign_payout', $bridge);
    }

    public function testWorkspaceResourceAndRiskBoundariesAreEnforced(): void
    {
        $bridge = (string)file_get_contents($this->root . '/api/internal/_mcp_creator_campaign_draft_bridge.php');
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_PROPOSAL_SCOPE_DENIED', $bridge);
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_PROPOSAL_WORKSPACE_REQUIRED', $bridge);
        self::assertStringContainsString('mg_creator_campaign_repository_by_public_id', $bridge);
        self::assertStringContainsString('mg_creator_campaign_repository_assert_product_owned', $bridge);
        self::assertStringContainsString("um.code='creator'", $bridge);
        self::assertStringContainsString("'compensation.propose' => 'high'", $bridge);
        self::assertStringContainsString("'budget.propose' => 'high'", $bridge);
        self::assertStringContainsString("'rights.propose' => 'high'", $bridge);
        self::assertStringContainsString("'terms.propose' => 'high'", $bridge);
    }

    public function testApprovalStillCannotConvertOrExecuteTheProposal(): void
    {
        $bridge = (string)file_get_contents($this->root . '/api/internal/_mcp_creator_campaign_draft_bridge.php');
        $account = (string)file_get_contents($this->root . '/includes/mcp-drafts/account-page-phase3b.php');
        $view = (string)file_get_contents($this->root . '/includes/mcp-drafts/account-page-phase3b-view.php');
        self::assertStringContainsString("'native_conversion_enabled' => false", $bridge);
        self::assertStringContainsString("'external_effects' => false", $bridge);
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_PROPOSAL_CONVERSION_DISABLED', $account);
        self::assertStringContainsString('Awaiting approval-gated canonical actions', $view);
        self::assertStringContainsString('Phase 13C', $view);
    }

    public function testNodeToolsUseCanonicalDraftAuthorityAndReceipts(): void
    {
        $tools = (string)file_get_contents($this->root . '/services/mcp/src/tools/creatorCampaignDrafts.ts');
        $registry = (string)file_get_contents($this->root . '/services/mcp/src/tools/registry.ts');
        self::assertStringContainsString('bridge.createDraft', $tools);
        self::assertStringContainsString('creator_campaign_proposal: true', $tools);
        self::assertStringContainsString('operationClass: "draft"', $tools);
        self::assertStringContainsString('inputFingerprint: fingerprint(input)', $tools);
        self::assertStringContainsString('destructiveHint: false', $tools);
        self::assertStringContainsString('registerCreatorCampaignDraftTools', $registry);
    }
}
