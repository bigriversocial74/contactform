<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignMcpReadV13aContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testReadToolCatalogAndScopesAreComplete(): void
    {
        $tools = (string)file_get_contents($this->root . '/services/mcp/src/tools/creatorCampaigns.ts');
        $sql = (string)file_get_contents($this->root . '/database/20260722_creator_campaign_mcp_read_scopes_v13a_single_install.sql');
        $names = [
            'microgifter.creator_campaigns.list',
            'microgifter.creator_campaigns.get',
            'microgifter.creator_campaigns.validate',
            'microgifter.creator_campaigns.analytics.get',
            'microgifter.creator_campaigns.applications.list',
            'microgifter.creator_campaigns.participants.list',
            'microgifter.creator_campaigns.deliverables.list',
            'microgifter.creator_campaigns.submissions.list',
            'microgifter.creator_campaigns.tracking.get',
            'microgifter.creator_campaigns.attributions.list',
            'microgifter.creator_campaigns.earnings.list',
            'microgifter.creator_campaigns.payouts.list',
            'microgifter.creator_campaigns.disputes.list',
        ];
        foreach ($names as $name) {
            self::assertStringContainsString($name, $tools);
        }
        self::assertSame(13, substr_count($tools, 'server.registerTool('));
        self::assertSame(11, substr_count($sql, "'read',1,1,NOW(),NOW())"));
        self::assertStringNotContainsString("'draft'", $sql);
        self::assertStringNotContainsString("'approval_gated'", $sql);
        self::assertStringNotContainsString("'bounded_auto'", $sql);
    }

    public function testCanonicalBridgeIsScopeAndOwnershipBound(): void
    {
        $bridge = (string)file_get_contents($this->root . '/api/internal/_mcp_creator_campaign_bridge.php');
        $endpoint = (string)file_get_contents($this->root . '/api/internal/mcp-bridge.php');
        self::assertStringContainsString('mg_mcp_bridge_require_scope($context, $scope)', $bridge);
        self::assertStringContainsString('cc.workspace_id=?', $bridge);
        self::assertStringContainsString('a.creator_user_id=?', $bridge);
        self::assertStringContainsString('p.creator_user_id=?', $bridge);
        self::assertStringContainsString("str_starts_with(\$operation, 'creator_campaigns.')", $endpoint);
        self::assertStringContainsString('mg_mcp_creator_campaign_bridge_dispatch', $endpoint);
    }

    public function testPrivacyAndFinancialExecutionBoundaries(): void
    {
        $bridge = (string)file_get_contents($this->root . '/api/internal/_mcp_creator_campaign_bridge.php');
        $tools = (string)file_get_contents($this->root . '/services/mcp/src/tools/creatorCampaigns.ts');
        foreach (['session_hash', 'visitor_hash', 'request_hash', 'provider_reference', 'bank_account', 'routing_number'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $bridge);
        }
        self::assertStringContainsString('anonymous hashes are never exposed', $tools);
        self::assertStringContainsString('without external provider references or banking details', $tools);
        self::assertStringContainsString('readOnlyHint: true', $tools);
        self::assertStringContainsString('destructiveHint: false', $tools);
    }

    public function testNativeValidationAndReceiptPathAreReused(): void
    {
        $bridge = (string)file_get_contents($this->root . '/api/internal/_mcp_creator_campaign_bridge.php');
        $typescript = (string)file_get_contents($this->root . '/services/mcp/src/tools/creatorCampaigns.ts');
        $canonical = (string)file_get_contents($this->root . '/services/mcp/src/bridge/canonicalBridge.ts');
        self::assertStringContainsString('mg_creator_campaign_builder_validation', $bridge);
        self::assertStringContainsString('recordReceipt', $typescript);
        self::assertStringContainsString('inputFingerprint: fingerprint(input)', $typescript);
        self::assertStringContainsString('creatorCampaignRead', $canonical);
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_SERVICE_FAILED', $typescript);
    }
}
