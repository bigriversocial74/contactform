<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CampaignStampDistributionContractTest extends TestCase
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

    public function testMerchantCampaignStampEndpointExists(): void
    {
        $source = $this->read('api/merchant/campaign-send.php');
        foreach([
            'mg_campaign_send_action_key',
            'campaign_feed_send',
            'email_list_send',
            'sms_send',
            'qr_claim_prompt_send',
            'agentic_discovery_send',
            'mg_stamp_debit_send',
            'merchant_campaign_send',
            'stamp_ledger',
        ] as $needle){
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testMerchantCampaignStampPageExists(): void
    {
        $page = $this->read('merchant-campaign-stamps.php');
        $view = $this->read('includes/merchant-campaign-stamps-view.php');
        $router = $this->read('includes/merchant-view.php');
        $js = $this->read('assets/js/stage12-campaign-send.js');
        self::assertStringContainsString('$merchantView=\'campaign_stamps\';', $page);
        self::assertStringContainsString('data-stage12-campaign-send', $view);
        self::assertStringContainsString('merchant-campaign-stamps-view.php', $router);
        self::assertStringContainsString('/api/merchant/campaign-send.php', $js);
    }

    public function testActiveCampaignsRequireAttachedRewardTemplates(): void
    {
        $source = $this->read('api/merchant/campaigns.php');
        self::assertStringContainsString('mg_campaign_requires_reward_template', $source);
        self::assertStringContainsString("\$status === 'active'", $source);
        self::assertStringContainsString('Active campaigns require an attached reward template.', $source);
        self::assertStringContainsString("'reward_attached' => !empty(\$row['reward_template_public_id'])", $source);
        self::assertStringContainsString("'reward_attached' => \$rewardTemplateId !== null", $source);
    }

    public function testGenericCampaignEngagementIssuesWalletRewards(): void
    {
        $source = $this->read('api/public/campaigns/engage.php');
        self::assertStringContainsString("INNER JOIN reward_templates rt ON rt.id = c.reward_template_id", $source);
        self::assertStringContainsString("rt.status = \\'active\\'", $source);
        self::assertStringContainsString('mg_public_campaign_enforce_reward_limits', $source);
        self::assertStringContainsString('INSERT INTO wallet_items', $source);
        self::assertStringContainsString("'wallet_item.issued'", $source);
        self::assertStringContainsString('UPDATE campaigns SET issued_count = issued_count + 1', $source);
        self::assertStringContainsString('UPDATE reward_templates SET issued_count = issued_count + 1', $source);
        self::assertStringContainsString('mg_zero_reward_issue_from_wallet', $source);
        self::assertStringContainsString("'already_issued' => true", $source);
        self::assertStringContainsString("'already_issued' => false", $source);
    }
}
