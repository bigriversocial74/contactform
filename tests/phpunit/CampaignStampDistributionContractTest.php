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
}
