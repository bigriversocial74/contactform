<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentActivityMonitorTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testAgentMonitorHelperBuildsStatusesExplanationsAndFilters(): void
    {
        $helper = $this->source('includes/merchant-agent-monitor.php');
        foreach (['mg_agent_monitor_payload','mg_agent_monitor_statuses','mg_agent_monitor_guardrail_summary','mg_agent_monitor_recommendation_item','mg_agent_monitor_event_item'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['needs_approval','ready_to_run','blocked','created_task','recommendation_only','blocked_by_daily_limit','why','guardrail_applied','recommended_next_action'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['agent_can_monitor','agent_can_recommend','agent_can_create_task','agent_requires_approval','max_actions_per_day'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testAgentMonitorApiIsMerchantScopedAndReadOnly(): void
    {
        $api = $this->source('api/merchant/agent-monitor.php');
        $helper = $this->source('includes/merchant-agent-monitor.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_monitor_payload'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
        foreach (['WHERE merchant_user_id=?', 'mg_automation_current_settings', 'mg_crm_playbook_scan', 'mg_automation_log'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testAgentMonitorPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-monitor.php');
        $view = $this->source('includes/merchant-agent-monitor-view.php');
        $js = $this->source('assets/js/merchant-agent-monitor.js');
        $css = $this->source('assets/css/merchant-agent-monitor.css');
        foreach (['Agent Activity Monitor','merchant-agent-monitor.css','merchant-agent-monitor.js','data-merchant-view="agent_monitor"','Agent Monitor'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-monitor','data-agent-statuses','data-agent-activity','data-agent-filter="needs_approval"','data-agent-filter="ready_to_run"','data-agent-filter="blocked"'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-monitor.php','Why this action','Guardrail applied','Recommended next action','Merchant approval required'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-monitor-hero','.mg-agent-monitor-kpis','.mg-agent-status-card','.mg-agent-activity-card','.mg-agent-explain-grid'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testExistingAutomationSurfacesLinkToAgentMonitor(): void
    {
        $automationPage = $this->source('merchant-automation.php');
        $automationView = $this->source('includes/merchant-automation-view.php');
        $retention = $this->source('assets/js/merchant-crm-retention-playbooks.js');
        $customer = $this->source('assets/js/merchant-customer-retention-recommendations.js');
        foreach (['/merchant-agent-monitor.php','Agent Monitor','agent_monitor'] as $needle) {
            self::assertStringContainsString($needle, $automationPage . $automationView);
        }
        foreach (['/merchant-agent-monitor.php','data-retention-agent-monitor','Monitor'] as $needle) {
            self::assertStringContainsString($needle, $retention);
        }
        foreach (['/merchant-agent-monitor.php','data-cp-agent-monitor','View agent explanation'] as $needle) {
            self::assertStringContainsString($needle, $customer);
        }
    }
}
