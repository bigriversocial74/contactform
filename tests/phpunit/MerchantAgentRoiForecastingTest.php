<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentRoiForecastingTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testForecastHelperBuildsScenarioForecasts(): void
    {
        $helper = $this->source('includes/merchant-agent-forecast.php');
        foreach (['mg_agent_forecast','mg_agent_forecast_input','mg_agent_forecast_scenario_config','mg_agent_forecast_project_period','mg_agent_forecast_from_roi','mg_agent_forecast_rows'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['conservative','base','aggressive','claim_lift_multiplier','avg_redemption_value_cents'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['expected_agent_influenced_claims','expected_redemption_value_cents','message_to_claim_projection','followup_to_claim_projection','psr_impact_estimate_cents'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['by_playbook','by_campaign','by_customer','historical_daily','data_sources'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testForecastApiIsScoped(): void
    {
        $api = $this->source('api/merchant/agent-forecast.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_forecast'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testForecastPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-forecast.php');
        $view = $this->source('includes/merchant-agent-forecast-view.php');
        $js = $this->source('assets/js/merchant-agent-forecast.js');
        $css = $this->source('assets/css/merchant-agent-forecast.css');
        foreach (['Agent ROI Forecasting','merchant-agent-forecast.css','merchant-agent-forecast.js','data-merchant-view="agent_forecast"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-forecast','data-forecast-scenario','data-forecast-claim-lift','data-forecast-avg-value','data-fc-claims','data-fc-revenue','data-fc-psr'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-forecast.php','data-forecast-periods','data-forecast-playbooks','data-forecast-campaigns','data-forecast-customers','data-forecast-daily'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-forecast-hero','.mg-agent-forecast-controls','.mg-agent-forecast-kpis','.mg-agent-forecast-periods'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testRoiAttributionLinksToForecasting(): void
    {
        $roiView = $this->source('includes/merchant-agent-roi-view.php');
        foreach (['/merchant-agent-forecast.php','ROI Forecasting','Open ROI forecast'] as $needle) {
            self::assertStringContainsString($needle, $roiView);
        }
    }
}
