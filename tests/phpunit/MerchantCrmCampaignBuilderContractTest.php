<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmCampaignBuilderContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function read(string $path): string { return (string) file_get_contents($this->root . '/' . $path); }

    public function testCampaignBuilderIsSqlFreeAndEventBacked(): void
    {
        $endpoint = $this->read('api/merchant/crm-campaign-builder.php');
        self::assertStringContainsString('campaign_events', $endpoint);
        self::assertStringContainsString('crm.segment.saved', $endpoint);
        self::assertStringContainsString('crm.campaign_builder.draft', $endpoint);
        self::assertStringContainsString('crm.campaign_builder.launched', $endpoint);
        self::assertStringNotContainsString('CREATE TABLE', $endpoint);
        self::assertStringNotContainsString('ALTER TABLE', $endpoint);
    }

    public function testCampaignBuilderUiMarkersExist(): void
    {
        $commandCenter = $this->read('assets/js/merchant-crm-command-center.js');
        $builder = $this->read('assets/js/merchant-crm-campaign-builder.js');
        $css = $this->read('assets/css/merchant-crm-command-center.css');
        self::assertStringContainsString('data-crm-campaign-builder', $commandCenter);
        self::assertStringContainsString('merchant-crm-campaign-builder.js', $commandCenter);
        self::assertStringContainsString('/api/merchant/crm-campaign-builder.php', $builder);
        self::assertStringContainsString('/api/merchant/crm-bulk-message.php', $builder);
        self::assertStringContainsString('/api/merchant/crm-bulk-reward.php', $builder);
        self::assertStringContainsString('/api/merchant/crm-followup.php', $builder);
        self::assertStringContainsString('mg-crm-builder-layout', $css);
    }

    public function testSavedSegmentsAndDraftsUseMerchantPermissions(): void
    {
        $endpoint = $this->read('api/merchant/crm-campaign-builder.php');
        self::assertStringContainsString('merchant.campaigns.view', $endpoint);
        self::assertStringContainsString('merchant.campaigns.manage', $endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write', $endpoint);
        self::assertStringContainsString('save_segment', $endpoint);
        self::assertStringContainsString('save_draft', $endpoint);
        self::assertStringContainsString('launch_record', $endpoint);
    }
}
