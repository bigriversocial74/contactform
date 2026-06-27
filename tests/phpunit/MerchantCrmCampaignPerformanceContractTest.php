<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmCampaignPerformanceContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function read(string $path): string { return (string) file_get_contents($this->root . '/' . $path); }

    public function testPerformanceEndpointIsSqlFreeAndUsesExistingTables(): void
    {
        $endpoint = $this->read('api/merchant/crm-campaign-performance.php');
        self::assertStringContainsString('campaign_events', $endpoint);
        self::assertStringContainsString('wallet_items', $endpoint);
        self::assertStringContainsString('campaign_contacts', $endpoint);
        self::assertStringContainsString('crm_reward_invites', $endpoint);
        self::assertStringContainsString('message_delivery_jobs', $endpoint);
        self::assertStringContainsString('crm.campaign_builder.launched', $endpoint);
        self::assertStringNotContainsString('CREATE TABLE', $endpoint);
        self::assertStringNotContainsString('ALTER TABLE', $endpoint);
    }

    public function testPerformanceDashboardUiMarkersExist(): void
    {
        $command = $this->read('assets/js/merchant-crm-command-center.js');
        $dashboard = $this->read('assets/js/merchant-crm-performance-dashboard.js');
        $css = $this->read('assets/css/merchant-crm-command-center.css');
        self::assertStringContainsString('data-crm-tab-target="performance"', $command);
        self::assertStringContainsString('data-crm-performance-dashboard', $command);
        self::assertStringContainsString('merchant-crm-performance-dashboard.js', $command);
        self::assertStringContainsString('/api/merchant/crm-campaign-performance.php', $dashboard);
        self::assertStringContainsString('data-crm-performance-kpis', $dashboard);
        self::assertStringContainsString('data-crm-performance-runs', $dashboard);
        self::assertStringContainsString('data-crm-performance-segments', $dashboard);
        self::assertStringContainsString('data-crm-performance-campaigns', $dashboard);
        self::assertStringContainsString('mg-crm-performance-grid', $css);
    }

    public function testPerformanceEndpointUsesMerchantViewPermission(): void
    {
        $endpoint = $this->read('api/merchant/crm-campaign-performance.php');
        self::assertStringContainsString('mg_require_method(\'GET\')', $endpoint);
        self::assertStringContainsString('merchant.campaigns.view', $endpoint);
        self::assertStringContainsString('mg_merchant_ensure_workspace', $endpoint);
        self::assertStringContainsString('schema_ready', $endpoint);
    }
}
