<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCustomerAgentTimelineTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testCustomerAgentTimelineHelperBuildsAuditTrail(): void
    {
        $helper = $this->source('includes/merchant-customer-agent-timeline.php');
        foreach (['mg_customer_agent_timeline','mg_customer_agent_timeline_rows','mg_customer_agent_timeline_summary','mg_customer_agent_timeline_resolve_contact','mg_customer_agent_timeline_campaign_contact_ids'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['crm.agent.approval.approved','crm.agent.execution.completed','crm.agent.message.draft.created','crm.agent.message.sent','crm.followup.created','crm.playbook.triggered'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['agent_recommendations','merchant_decisions','executions','message_drafts_sends','followup_tasks'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['guardrail_applied','playbook_title','decided_by_user_id','/merchant-agent-monitor.php','/merchant-agent-approvals.php','/merchant-agent-execution.php','/merchant-agent-messages.php','/merchant-followups.php'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testCustomerAgentTimelineApiIsScoped(): void
    {
        $api = $this->source('api/merchant/customer-agent-timeline.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_customer_agent_timeline'] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testCustomerProfileLoadsTimelineAssets(): void
    {
        $page = $this->source('merchant-customer.php');
        foreach (['merchant-customer-agent-timeline.css','merchant-customer-agent-timeline.js','data-merchant-view="customer_profile"'] as $needle) {
            self::assertStringContainsString($needle, $page);
        }
    }

    public function testCustomerAgentTimelineAssetsRenderPanelAndLinks(): void
    {
        $js = $this->source('assets/js/merchant-customer-agent-timeline.js');
        $css = $this->source('assets/css/merchant-customer-agent-timeline.css');
        foreach (['/api/merchant/customer-agent-timeline.php','data-customer-agent-timeline','data-customer-agent-items','data-customer-agent-summary','data-customer-agent-timeline-mini'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['Agent Customer Timeline','Message Outbox','Execution Center','Review Queue','Agent Monitor'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-cp-agent-timeline-card','.mg-cp-agent-summary','.mg-cp-agent-groups','.mg-cp-agent-mini-card'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }
}
