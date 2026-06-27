<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentGrowthPlannerTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testGrowthPlannerHelperBuildsActionPlan(): void
    {
        $helper = $this->source('includes/merchant-agent-growth-plan.php');
        foreach (['mg_agent_growth_plan','mg_agent_growth_input','mg_agent_growth_goal_config','mg_agent_growth_risk_config','mg_agent_growth_effort_config','mg_agent_growth_recommended_actions','mg_agent_growth_sections'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['claims','revenue','psr','reactivation','conservative','balanced','aggressive','low','medium','high'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['best_next_playbooks','best_customers_to_follow_up','campaigns_worth_repeating','message_opportunities','followup_opportunities','claim_revenue_psr_targets'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['expected_claims','expected_revenue_cents','expected_psr_impact_cents','required_messages','required_followups','review_queue_url','message_outbox_url'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testGrowthPlannerApiIsScoped(): void
    {
        $api = $this->source('api/merchant/agent-growth-plan.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_growth_plan'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testGrowthPlannerPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-growth-plan.php');
        $view = $this->source('includes/merchant-agent-growth-plan-view.php');
        $js = $this->source('assets/js/merchant-agent-growth-plan.js');
        $css = $this->source('assets/css/merchant-agent-growth-plan.css');
        foreach (['Agent Growth Planner','merchant-agent-growth-plan.css','merchant-agent-growth-plan.js','data-merchant-view="agent_growth"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-growth-plan','data-growth-goal','data-growth-timeframe','data-growth-risk','data-growth-effort','data-growth-actions-list'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-growth-plan.php','data-growth-playbooks','data-growth-campaigns','data-growth-customers','data-growth-opportunities'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-growth-hero','.mg-agent-growth-controls','.mg-agent-growth-kpis','.mg-agent-growth-action-card'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testForecastingLinksToGrowthPlanner(): void
    {
        $forecastView = $this->source('includes/merchant-agent-forecast-view.php');
        foreach (['/merchant-agent-growth-plan.php','Growth Planner','Open growth planner'] as $needle) {
            self::assertStringContainsString($needle, $forecastView);
        }
    }
}
