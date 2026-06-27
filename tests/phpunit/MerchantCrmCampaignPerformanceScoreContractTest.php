<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmCampaignPerformanceScoreContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function read(string $path): string { return (string) file_get_contents($this->root . '/' . $path); }

    public function testCampaignPerformanceScorePanelUsesExistingDataSources(): void
    {
        $audit = $this->read('assets/js/merchant-crm-launch-audit-trail.js');
        $score = $this->read('assets/js/merchant-crm-campaign-performance-score.js');
        $css = $this->read('assets/css/merchant-crm-campaign-performance-score.css');

        self::assertStringContainsString('merchant-crm-campaign-performance-score.js', $audit);
        self::assertStringContainsString('data-crm-tab-target\',\'campaign-score\'', $score);
        self::assertStringContainsString('data-crm-tab-panel\',\'campaign-score\'', $score);
        self::assertStringContainsString('/api/merchant/crm-campaign-builder.php', $score);
        self::assertStringContainsString('/api/merchant/crm-performance-insights.php?days=90', $score);
        self::assertStringContainsString('scoreFor', $score);
        self::assertStringContainsString('labelFor', $score);
        self::assertStringContainsString('data-crm-campaign-score-kpis', $score);
        self::assertStringContainsString('data-crm-campaign-score-list', $score);
        self::assertStringContainsString('mg-crm-campaign-score-kpis', $css);
        self::assertStringContainsString('mg-crm-campaign-score-bar', $css);
    }
}
