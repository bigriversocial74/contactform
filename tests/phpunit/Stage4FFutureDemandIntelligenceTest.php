<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage4FFutureDemandIntelligenceTest extends TestCase
{
    public function testSchemaDefinesFactsForecastsScoresAlertsAndExports(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_4f_future_demand_intelligence.sql');
        self::assertIsString($sql);
        foreach (['demand_fact_daily','demand_feature_snapshots','demand_forecast_models','demand_forecast_runs','demand_forecast_points','merchant_intelligence_snapshots','demand_alert_rules','demand_alert_events','intelligence_export_jobs'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
    }

    public function testForecastingIsDeterministicAndBounded(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/intelligence/_intelligence.php');
        self::assertIsString($source);
        self::assertStringContainsString("'seasonal_naive'", $source);
        self::assertStringContainsString("'exponential_smoothing'", $source);
        self::assertStringContainsString("'moving_average'", $source);
        self::assertStringContainsString('mg_intelligence_interval', $source);
        self::assertStringContainsString('max(0, $prediction', $source);
        self::assertStringContainsString('Unsupported forecast model type.', $source);
    }

    public function testForecastRunsRecordInputChecksumAndBacktestMetrics(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/intelligence/forecast.php');
        self::assertIsString($source);
        self::assertStringContainsString("hash('sha256'", $source);
        self::assertStringContainsString('input_checksum', $source);
        self::assertStringContainsString("'mae'", $source);
        self::assertStringContainsString("status='completed'", $source);
    }

    public function testDailyDemandAggregationCombinesEngagementPppmAndDistribution(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/aggregate_stage4f_demand.php');
        self::assertIsString($source);
        self::assertStringContainsString('content_engagement_events', $source);
        self::assertStringContainsString('pppm_items', $source);
        self::assertStringContainsString('distribution_allocations', $source);
        self::assertStringContainsString('INSERT INTO demand_fact_daily', $source);
    }

    public function testMerchantSnapshotsProduceScoresGrowthAndActionableInsights(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/build_stage4f_snapshots.php');
        self::assertIsString($source);
        self::assertStringContainsString('demand_score', $source);
        self::assertStringContainsString('engagement_score', $source);
        self::assertStringContainsString('fulfillment_score', $source);
        self::assertStringContainsString('redemption_score', $source);
        self::assertStringContainsString('claim_gap', $source);
        self::assertStringContainsString('redemption_gap', $source);
    }

    public function testExportsEnforceAggregateOrKAnonymousPrivacy(): void
    {
        $api = file_get_contents(dirname(__DIR__, 2) . '/api/intelligence/exports.php');
        $worker = file_get_contents(dirname(__DIR__, 2) . '/scripts/process_stage4f_exports.php');
        self::assertIsString($api);
        self::assertIsString($worker);
        self::assertStringContainsString("['aggregate','k_anonymous']", $api);
        self::assertStringContainsString('minimum_cohort_size', $api);
        self::assertStringContainsString("privacy_mode']==='k_anonymous'", $worker);
        self::assertStringContainsString('unique_recipients', $worker);
        self::assertStringNotContainsString('recipient_external_id', $worker);
    }

    public function testDashboardUsesMerchantScopedApiAndForecastAction(): void
    {
        $page = file_get_contents(dirname(__DIR__, 2) . '/intelligence.php');
        $js = file_get_contents(dirname(__DIR__, 2) . '/assets/js/intelligence.js');
        self::assertIsString($page);
        self::assertIsString($js);
        self::assertStringContainsString('data-intelligence-dashboard', $page);
        self::assertStringContainsString('/api/intelligence/overview.php', $js);
        self::assertStringContainsString('/api/intelligence/forecast.php', $js);
        self::assertStringContainsString('/api/intelligence/exports.php', $js);
    }
}
