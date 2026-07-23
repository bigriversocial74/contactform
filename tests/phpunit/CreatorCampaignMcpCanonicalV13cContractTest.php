<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignMcpCanonicalV13cContractTest extends TestCase
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

    public function testToolAndScopeCatalogsAreExact(): void
    {
        $tools = $this->read('services/mcp/src/tools/creatorCampaignActions.ts');
        $sql = $this->read('database/20260722_creator_campaign_mcp_canonical_actions_v13c_single_install.sql');
        $names = [
            'microgifter.creator_campaigns.publish',
            'microgifter.creator_campaigns.schedule',
            'microgifter.creator_campaigns.pause',
            'microgifter.creator_campaigns.resume',
            'microgifter.creator_campaigns.complete',
            'microgifter.creator_campaigns.cancel',
            'microgifter.creator_campaigns.application.approve',
            'microgifter.creator_campaigns.application.decline',
            'microgifter.creator_campaigns.invitation.send',
            'microgifter.creator_campaigns.agreement.offer',
            'microgifter.creator_campaigns.participant.suspend',
            'microgifter.creator_campaigns.participant.remove',
            'microgifter.creator_campaigns.submission.approve',
            'microgifter.creator_campaigns.submission.request_revision',
            'microgifter.creator_campaigns.submission.reject',
            'microgifter.creator_campaigns.attribution.override',
            'microgifter.creator_campaigns.earning.approve',
            'microgifter.creator_campaigns.earning.hold',
            'microgifter.creator_campaigns.earning.reject',
            'microgifter.creator_campaigns.earning.reverse',
            'microgifter.creator_campaigns.payout.record',
            'microgifter.creator_campaigns.dispute.resolve',
        ];
        foreach ($names as $name) {
            self::assertStringContainsString($name, $tools);
        }
        self::assertSame(22, substr_count($tools, 'register(server,dependencies,"microgifter.creator_campaigns.'));
        self::assertSame(8, substr_count($sql, "'approval_gated',1,1,NOW(),NOW())"));
        self::assertStringNotContainsString("'bounded_auto'", $sql);
        self::assertStringNotContainsString("'prohibited'", $sql);
    }

    public function testMcpBoundaryIsRequestOnly(): void
    {
        $tools = $this->read('services/mcp/src/tools/creatorCampaignActions.ts');
        $bridge = $this->read('api/internal/_mcp_creator_campaign_action_bridge.php');
        $request = $this->read('includes/mcp-creator-campaign-actions/request-service.php');
        self::assertStringContainsString('requestCreatorCampaignAction', $tools);
        self::assertStringContainsString('performed:false', $tools);
        self::assertStringContainsString('waiting_for_owner_approval', $tools);
        self::assertStringContainsString('mg_mcp_creator_campaign_action_request', $bridge);
        self::assertStringNotContainsString('mg_mcp_creator_campaign_action_execute', $bridge);
        self::assertStringContainsString('INSERT INTO mcp_automation_runs', $request);
        self::assertStringContainsString('INSERT INTO mcp_automation_actions', $request);
        self::assertStringContainsString('INSERT INTO mcp_creator_campaign_action_approvals', $request);
        self::assertDoesNotMatchRegularExpression('/(?:INSERT INTO|UPDATE|DELETE FROM) creator_campaign_/', $request);
    }

    public function testOwnerApprovalAndExecutionAreSeparate(): void
    {
        $page = $this->read('account-creator-campaign-actions.php')
            . $this->read('includes/mcp-creator-campaign-actions/owner-page-view.php');
        $owner = $this->read('includes/mcp-creator-campaign-actions/owner-service.php');
        $execution = $this->read('includes/mcp-creator-campaign-actions/execution-service.php');
        self::assertStringContainsString('mg_mcp_creator_campaign_action_decide', $page);
        self::assertStringContainsString('mg_mcp_creator_campaign_action_execute', $page);
        self::assertStringContainsString('confirm_execute', $page);
        self::assertStringContainsString('Execute approved action', $page);
        self::assertStringContainsString("status='approved'", $owner);
        self::assertStringContainsString('MCP_CREATOR_CAMPAIGN_ACTION_STATE_CHANGED', $execution);
        self::assertStringContainsString('mg_mcp_automation_authorize_grant_action', $execution);
        self::assertStringContainsString('approval_expires_at', $execution);
    }

    public function testExecutionUsesOnlyNativeServicesAndCanonicalReceipts(): void
    {
        $execution = $this->read('includes/mcp-creator-campaign-actions/execution-service.php');
        foreach ([
            'mg_creator_campaign_transition_status',
            'mg_creator_campaign_application_review_merchant',
            'mg_creator_campaign_invitation_create_merchant',
            'mg_creator_campaign_agreement_offer_merchant',
            'mg_creator_campaign_participant_transition_merchant',
            'mg_creator_campaign_submission_review_merchant',
            'mg_creator_campaign_attribution_override_merchant',
            'mg_creator_campaign_earning_decide_merchant',
            'mg_creator_campaign_payout_create',
            'mg_creator_campaign_dispute_transition',
        ] as $function) {
            self::assertStringContainsString($function, $execution);
        }
        self::assertStringContainsString('INSERT INTO mcp_action_receipts', $execution);
        self::assertStringContainsString('before_state_token', $execution);
        self::assertStringContainsString('after_state_token', $execution);
        self::assertStringNotContainsString('provider_reference', $execution);
    }

    public function testOauthAndGrantAuthorityRemainBounded(): void
    {
        $oauth = $this->read('includes/mcp-oauth/operation-classes.php')
            . $this->read('includes/mcp-oauth/clients.php');
        $grants = $this->read('includes/mcp-automations/bootstrap.php')
            . $this->read('includes/mcp-automations/create-grant.php');
        self::assertStringContainsString("\$registrationType === 'dynamic'", $oauth);
        self::assertStringContainsString("['read', 'draft', 'approval_gated']", $oauth);
        self::assertStringContainsString('owner_execution_required', $oauth);
        self::assertStringContainsString('creator_campaign_lifecycle_actions', $grants);
        self::assertStringContainsString('creator_campaign_financial_actions', $grants);
        self::assertStringContainsString("\$riskCeiling !== 'critical'", $grants);
        self::assertStringContainsString('external_client_direct_execution', $grants);
    }

    public function testEarningDecisionsAreNativeAndPermissioned(): void
    {
        $earning = $this->read('includes/creator-campaigns/earning-service.php');
        $sql = $this->read('database/20260722_creator_campaign_mcp_canonical_actions_v13c_single_install.sql');
        self::assertStringContainsString('merchant.creator_earnings.manage', $earning);
        self::assertStringContainsString('mg_creator_campaign_compensation_reverse', $earning);
        self::assertStringContainsString('creator_campaign_earning_reviews', $earning);
        self::assertStringContainsString('merchant.creator_earnings.manage', $sql);
    }
}
