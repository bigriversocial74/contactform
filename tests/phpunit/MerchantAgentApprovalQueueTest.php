<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentApprovalQueueTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testApprovalQueueHelperBuildsMerchantScopedDecisionFlow(): void
    {
        $helper = $this->source('includes/merchant-agent-approvals.php');
        foreach (['mg_agent_approval_queue','mg_agent_approval_find_item','mg_agent_approval_record_decision','mg_agent_approval_recommendation_item','mg_agent_approval_event_item'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['crm.agent.approval.approved','crm.agent.approval.rejected','crm.agent.approval.deferred','crm.agent.approval.task_created'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['risk_level','guardrail_applied','expected_action','merchant_approval_required','can_create_task'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['mg_crm_playbook_scan','mg_automation_log','mg_agent_monitor_guardrail_summary','mg_crm_playbook_create_followup'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testApprovalApisAreScopedAndProtected(): void
    {
        $queue = $this->source('api/merchant/agent-approvals.php');
        $action = $this->source('api/merchant/agent-approval-action.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_approval_queue'] as $needle) {
            self::assertStringContainsString($needle, $queue);
        }
        foreach (['mg_require_method(\'POST\')', "mg_require_permission('merchant.campaigns.manage')", 'mg_require_csrf_for_write', 'mg_agent_approval_find_item', 'mg_agent_approval_record_decision'] as $needle) {
            self::assertStringContainsString($needle, $action);
        }
        foreach (['approve','reject','defer','create_task'] as $needle) {
            self::assertStringContainsString($needle, $action);
        }
    }

    public function testApprovalPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-approvals.php');
        $view = $this->source('includes/merchant-agent-approvals-view.php');
        $js = $this->source('assets/js/merchant-agent-approvals.js');
        $css = $this->source('assets/css/merchant-agent-approvals.css');
        foreach (['Agent Approval Queue','merchant-agent-approvals.css','merchant-agent-approvals.js','data-merchant-view="agent_approvals"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-approvals','data-agent-approval-list','data-agent-approval-status','data-approval-filter="pending"','data-approval-filter="task_created"'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-approvals.php','/api/merchant/agent-approval-action.php','data-approval-action="approve"','data-approval-action="reject"','data-approval-action="create_task"'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-approvals-hero','.mg-agent-approvals-kpis','.mg-agent-approval-card','.mg-agent-approval-explain'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testExistingAgentSurfacesLinkToApprovalQueue(): void
    {
        $automationView = $this->source('includes/merchant-automation-view.php');
        $monitorPage = $this->source('merchant-agent-monitor.php');
        $monitorView = $this->source('includes/merchant-agent-monitor-view.php');
        $monitorJs = $this->source('assets/js/merchant-agent-monitor.js');
        $retention = $this->source('assets/js/merchant-crm-retention-playbooks.js');
        $customer = $this->source('assets/js/merchant-customer-retention-recommendations.js');
        foreach (['/merchant-agent-approvals.php','Review Queue'] as $needle) {
            self::assertStringContainsString($needle, $automationView . $monitorView . $retention . $customer);
        }
        foreach (['agent_review','/merchant-agent-approvals.php'] as $needle) {
            self::assertStringContainsString($needle, $monitorPage);
        }
        foreach (['Review approval','Approval queue'] as $needle) {
            self::assertStringContainsString($needle, $monitorJs);
        }
    }
}
