<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CrmRewardInviteBridgeContractTest extends TestCase
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

    public function testInviteMigrationIsRegisteredAndDefinesReservationTable(): void
    {
        $manifest = $this->read('config/migrations.php');
        $sql = $this->read('database/stage_12_crm_reward_invites.sql');

        self::assertStringContainsString('stage_12_crm_reward_invites.sql', $manifest);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS crm_reward_invites', $sql);
        self::assertStringContainsString('reward_template_id BIGINT UNSIGNED NOT NULL', $sql);
        self::assertStringContainsString("status ENUM('sent','linked','delivered','revoked','expired')", $sql);
        self::assertStringContainsString('wallet_item_id BIGINT UNSIGNED NULL', $sql);
        self::assertStringContainsString('stage_12_crm_reward_invites', $sql);
    }

    public function testMerchantInviteEndpointCreatesPendingInviteAndTimelineEvent(): void
    {
        $source = $this->read('api/merchant/crm-send-reward-invite.php');

        self::assertStringContainsString("mg_require_permission('merchant.campaigns.manage')", $source);
        self::assertStringContainsString('mg_require_csrf_for_write', $source);
        self::assertStringContainsString('crm_reward_invites', $source);
        self::assertStringContainsString("status='sent'", $source);
        self::assertStringContainsString('mg_delivery_enqueue', $source);
        self::assertStringContainsString('crm.reward_invite.sent', $source);
        self::assertStringContainsString('mg_merchant_crm_record_event', $source);
        self::assertStringContainsString('This contact already has an account. Use direct reward send.', $source);
    }

    public function testAuthEndpointsLinkPendingInvitesAfterLoginOrSignup(): void
    {
        $helper = $this->read('includes/merchant-crm-reward-invites.php');
        $register = $this->read('api/auth/register.php');
        $login = $this->read('api/auth/login.php');

        self::assertStringContainsString('function mg_crm_reward_invites_link_for_user', $helper);
        self::assertStringContainsString('INSERT INTO wallet_items', $helper);
        self::assertStringContainsString('UPDATE campaign_contacts SET user_id=?', $helper);
        self::assertStringContainsString('crm.reward_invite.delivered', $helper);
        self::assertStringContainsString('mg_zero_reward_issue_from_wallet', $helper);
        self::assertStringContainsString('mg_crm_reward_invites_link_for_user', $register);
        self::assertStringContainsString('crm_reward_invites', $register);
        self::assertStringContainsString('mg_crm_reward_invites_link_for_user', $login);
        self::assertStringContainsString('crm_reward_invites', $login);
    }

    public function testCrmUiLoadsInviteBridgeAndPostsInviteEndpoint(): void
    {
        $view = $this->read('includes/merchant-crm-view.php');
        $js = $this->read('assets/js/merchant-crm-reward-invite-bridge.js');

        self::assertStringContainsString('/assets/js/merchant-crm-reward-invite-bridge.js', $view);
        self::assertStringContainsString('/api/merchant/crm-send-reward-invite.php', $js);
        self::assertStringContainsString('Invite reward', $js);
        self::assertStringContainsString('!c.has_account', $js);
        self::assertStringContainsString('Reward invite sent.', $js);
    }
}
