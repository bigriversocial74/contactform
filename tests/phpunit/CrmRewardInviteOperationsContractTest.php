<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CrmRewardInviteOperationsContractTest extends TestCase
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

    public function testInviteOperationsListEndpointIsOwnerScopedAndReportsTotals(): void
    {
        $source = $this->read('api/merchant/crm-reward-invites.php');

        self::assertStringContainsString("mg_require_permission('merchant.campaigns.view')", $source);
        self::assertStringContainsString('mg_crm_reward_invites_ready', $source);
        self::assertStringContainsString('crm_reward_invites i', $source);
        self::assertStringContainsString('WHERE i.merchant_user_id=?', $source);
        self::assertStringContainsString('conversion_rate', $source);
        self::assertStringContainsString('invite_url', $source);
    }

    public function testInviteResendEndpointQueuesEmailAndTimelineEvent(): void
    {
        $source = $this->read('api/merchant/crm-reward-invite-resend.php');

        self::assertStringContainsString("mg_require_permission('merchant.campaigns.manage')", $source);
        self::assertStringContainsString('mg_require_csrf_for_write', $source);
        self::assertStringContainsString("Only pending invites can be resent.", $source);
        self::assertStringContainsString('mg_delivery_enqueue', $source);
        self::assertStringContainsString('campaign.crm_reward_invite_resend', $source);
        self::assertStringContainsString('crm.reward_invite.resent', $source);
        self::assertStringContainsString('mg_merchant_crm_record_event', $source);
    }

    public function testInviteRevokeEndpointBlocksDeliveredInvitesAndRecordsTimeline(): void
    {
        $source = $this->read('api/merchant/crm-reward-invite-revoke.php');

        self::assertStringContainsString("mg_require_permission('merchant.campaigns.manage')", $source);
        self::assertStringContainsString('mg_require_csrf_for_write', $source);
        self::assertStringContainsString("status='revoked'", $source);
        self::assertStringContainsString('Delivered or linked reward invites cannot be revoked.', $source);
        self::assertStringContainsString('crm.reward_invite.revoked', $source);
        self::assertStringContainsString('mg_merchant_crm_record_event', $source);
    }

    public function testMerchantCrmLoadsInviteOperationsPanel(): void
    {
        $view = $this->read('includes/merchant-crm-view.php');
        $js = $this->read('assets/js/merchant-crm-reward-invite-operations.js');

        self::assertStringContainsString('/assets/js/merchant-crm-reward-invite-operations.js', $view);
        self::assertStringContainsString('/api/merchant/crm-reward-invites.php?limit=100', $js);
        self::assertStringContainsString('/api/merchant/crm-reward-invite-resend.php', $js);
        self::assertStringContainsString('/api/merchant/crm-reward-invite-revoke.php', $js);
        self::assertStringContainsString('Reward Invite Operations', $js);
        self::assertStringContainsString('data-invite-resend', $js);
        self::assertStringContainsString('data-invite-revoke', $js);
        self::assertStringContainsString('data-invite-copy', $js);
    }

    public function testTimelineCardsArePolishedForInviteEvents(): void
    {
        $js = $this->read('assets/js/merchant-crm.js');

        self::assertStringContainsString('crm.reward_invite.sent', $js);
        self::assertStringContainsString('crm.reward_invite.resent', $js);
        self::assertStringContainsString('crm.reward_invite.delivered', $js);
        self::assertStringContainsString('crm.reward_invite.revoked', $js);
        self::assertStringContainsString('Direct reward sent', $js);
        self::assertStringContainsString('detailForEvent', $js);
        self::assertStringContainsString('wallet_item_id', $js);
        self::assertStringContainsString('invite_id', $js);
    }
}
