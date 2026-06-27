<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentRoiAttributionTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testRoiHelperBuildsAttributionMetrics(): void
    {
        $helper = $this->source('includes/merchant-agent-roi.php');
        foreach (['mg_agent_roi','mg_agent_roi_redemption_rows','mg_agent_roi_agent_touchpoints','mg_agent_roi_attribute_redemptions','mg_agent_roi_summary','mg_agent_roi_group_attribution','mg_agent_roi_customer_attribution','mg_agent_roi_daily'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['microgift_redemptions','amount_cents','merchant_user_id','claimant_user_id','redeemed_at','microgift_instances'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['agent_touched_customers','agent_influenced_claims','estimated_revenue_influenced_cents','message_to_claim_rate','followup_to_claim_rate','campaign_roi_by_agent_workflow_cents','psr_impact_estimate_cents'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['by_playbook','by_campaign','by_customer','recent_redemptions','data_sources'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testRoiApiIsScoped(): void
    {
        $api = $this->source('api/merchant/agent-roi.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_roi'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testRoiPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-roi.php');
        $view = $this->source('includes/merchant-agent-roi-view.php');
        $js = $this->source('assets/js/merchant-agent-roi.js');
        $css = $this->source('assets/css/merchant-agent-roi.css');
        foreach (['Agent ROI Attribution','merchant-agent-roi.css','merchant-agent-roi.js','data-merchant-view="agent_roi"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-roi','data-roi-customers','data-roi-claims','data-roi-revenue','data-roi-message-rate','data-roi-followup-rate','data-roi-psr'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-roi.php','data-agent-roi-funnel','data-roi-playbooks','data-roi-campaigns','data-roi-customers-table','data-roi-daily','data-roi-recent'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-roi-hero','.mg-agent-roi-kpis','.mg-agent-roi-funnel','.mg-agent-roi-event'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testOutcomeAnalyticsLinksToRoiAttribution(): void
    {
        $analyticsView = $this->source('includes/merchant-agent-analytics-view.php');
        foreach (['/merchant-agent-roi.php','ROI Attribution','Open ROI attribution'] as $needle) {
            self::assertStringContainsString($needle, $analyticsView);
        }
    }
}
