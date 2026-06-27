<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentOutcomeAnalyticsTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testAnalyticsHelperBuildsOutcomeMetrics(): void
    {
        $helper = $this->source('includes/merchant-agent-analytics.php');
        foreach (['mg_agent_analytics','mg_agent_analytics_rows','mg_agent_analytics_summary','mg_agent_analytics_grouped','mg_agent_analytics_customer_breakdown','mg_agent_analytics_daily'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['recommendations_generated','approval_rate','execution_completion_rate','draft_to_send_rate','followup_conversion_rate','failed_skipped_rate','customers_touched'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['crm.agent.approval.approved','crm.agent.execution.completed','crm.agent.message.draft.created','crm.agent.message.sent','crm.agent.execution.failed','crm.agent.execution.skipped'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['by_playbook','by_campaign','by_customer','by_event_type','daily','recent_events'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testAnalyticsApiIsScoped(): void
    {
        $api = $this->source('api/merchant/agent-analytics.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_analytics'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testAnalyticsPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-analytics.php');
        $view = $this->source('includes/merchant-agent-analytics-view.php');
        $js = $this->source('assets/js/merchant-agent-analytics.js');
        $css = $this->source('assets/css/merchant-agent-analytics.css');
        foreach (['Agent Outcome Analytics','merchant-agent-analytics.css','merchant-agent-analytics.js','data-merchant-view="agent_analytics"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-analytics','data-aa-recommendations','data-aa-approval-rate','data-aa-execution-rate','data-aa-draft-rate','data-aa-customers','data-aa-recent'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-analytics.php','data-agent-analytics-funnel','data-aa-playbooks','data-aa-customers','data-aa-events','data-aa-daily'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-analytics-hero','.mg-agent-analytics-kpis','.mg-agent-analytics-funnel','.mg-agent-analytics-event'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testExistingAgentWorkflowLinksToOutcomeAnalytics(): void
    {
        $monitor = $this->source('includes/merchant-agent-monitor-view.php');
        $execution = $this->source('includes/merchant-agent-execution-view.php');
        $messages = $this->source('includes/merchant-agent-messages-view.php');
        foreach (['/merchant-agent-analytics.php','Outcome Analytics','Open outcome analytics'] as $needle) {
            self::assertStringContainsString($needle, $monitor . $execution . $messages);
        }
    }
}
