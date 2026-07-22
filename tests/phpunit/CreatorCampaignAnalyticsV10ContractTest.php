<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class CreatorCampaignAnalyticsV10ContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    public function testAnalyticsReusesAuthoritativeRecordsWithoutSql(): void
    {
        self::assertFileDoesNotExist($this->root . '/database/20260722_creator_campaign_analytics_v10.sql');
        $query = file_get_contents($this->root . '/includes/creator-campaigns/analytics-query.php');
        self::assertStringContainsString('creator_campaign_tracking_events', $query);
        self::assertStringContainsString('creator_campaign_attributions', $query);
        self::assertStringContainsString('creator_campaign_earning_events', $query);
        self::assertStringContainsString("e.status='accepted'", $query);
        self::assertStringContainsString("a.status IN ('attributed','overridden')", $query);
        self::assertGreaterThanOrEqual(4, substr_count($query, "e.status='accepted'"));
    }
    public function testOwnershipAndFinancialBoundaries(): void
    {
        $query = file_get_contents($this->root . '/includes/creator-campaigns/analytics-query.php');
        $context = file_get_contents($this->root . '/includes/creator-campaigns/analytics-context.php');
        self::assertStringContainsString('cc.workspace_id', $query);
        self::assertStringContainsString("\$scope['participant_id'] !== null && \$participantColumn !== null", $query);
        self::assertStringContainsString("if (\$scope['mode'] !== 'merchant')", $query);
        self::assertStringContainsString('merchant.intelligence.view', $context);
        self::assertStringContainsString('creator.campaign_messages.view_own', $context);
    }
    public function testDateAndRateSafety(): void
    {
        require_once $this->root . '/includes/creator-campaigns/analytics-definitions.php';
        self::assertSame(0, mg_creator_campaign_analytics_conversion_rate_bps(2, 0));
        self::assertSame(2500, mg_creator_campaign_analytics_conversion_rate_bps(1, 4));
        $range = mg_creator_campaign_analytics_normalize_range(['range' => 'last_30_days']);
        self::assertSame('day', $range['bucket']);
        $this->expectException(InvalidArgumentException::class);
        mg_creator_campaign_analytics_normalize_range(['range'=>'custom','from'=>'2020-01-01','to'=>'2025-01-01']);
    }
    public function testCsvFormulaProtectionAndWhitelist(): void
    {
        require_once $this->root . '/includes/creator-campaigns/analytics-definitions.php';
        require_once $this->root . '/includes/creator-campaigns/analytics-export.php';
        self::assertSame("'=SUM(A1:A2)", mg_creator_campaign_analytics_csv_cell('=SUM(A1:A2)'));
        self::assertSame('safe', mg_creator_campaign_analytics_csv_cell('safe'));
        $this->expectException(InvalidArgumentException::class);
        mg_creator_campaign_analytics_export_rows(['campaigns'=>[]], 'unknown');
    }
}
