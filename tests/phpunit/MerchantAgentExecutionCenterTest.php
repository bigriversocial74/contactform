<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentExecutionCenterTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testExecutionHelperBuildsReviewedExecutionFlow(): void
    {
        $helper = $this->source('includes/merchant-agent-execution.php');
        foreach (['mg_agent_execution_queue','mg_agent_execution_find_item','mg_agent_execution_perform','mg_agent_execution_source_events','mg_agent_execution_latest_events','mg_agent_execution_record'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['crm.agent.approval.approved','crm.agent.approval.task_created','crm.agent.execution.started','crm.agent.execution.completed','crm.agent.execution.failed','crm.agent.execution.skipped','crm.agent.message.draft.created'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['approved_not_executed','executing','completed','failed','skipped','execute_approved_action','create_followup_task','draft_customer_message','retry_failed_execution'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['mg_automation_record_event','mg_crm_playbook_create_followup','mg_crm_playbook_scan','source_id','approval_id'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testExecutionApisAreScopedAndProtected(): void
    {
        $queue = $this->source('api/merchant/agent-execution.php');
        $action = $this->source('api/merchant/agent-execute-action.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_execution_queue'] as $needle) {
            self::assertStringContainsString($needle, $queue);
        }
        foreach (['mg_require_method(\'POST\')', "mg_require_permission('merchant.campaigns.manage')", 'mg_require_csrf_for_write', 'mg_agent_execution_find_item', 'mg_agent_execution_perform'] as $needle) {
            self::assertStringContainsString($needle, $action);
        }
        foreach (['execute_approved_action','create_followup_task','draft_customer_message','mark_skipped','retry_failed_execution'] as $needle) {
            self::assertStringContainsString($needle, $action);
        }
    }

    public function testExecutionPageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-execution.php');
        $view = $this->source('includes/merchant-agent-execution-view.php');
        $js = $this->source('assets/js/merchant-agent-execution.js');
        $css = $this->source('assets/css/merchant-agent-execution.css');
        foreach (['Agent Execution Center','merchant-agent-execution.css','merchant-agent-execution.js','data-merchant-view="agent_execution"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-execution','data-agent-execution-list','data-agent-execution-status','data-execution-filter="approved_not_executed"','data-execution-filter="failed"'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-execution.php','/api/merchant/agent-execute-action.php','execute_approved_action','draft_customer_message','mark_skipped'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-execution-hero','.mg-agent-execution-kpis','.mg-agent-execution-card','.mg-agent-execution-explain'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testExistingAgentSurfacesLinkToExecutionCenter(): void
    {
        $automationView = $this->source('includes/merchant-automation-view.php');
        $monitorView = $this->source('includes/merchant-agent-monitor-view.php');
        $approvalsView = $this->source('includes/merchant-agent-approvals-view.php');
        $approvalsJs = $this->source('assets/js/merchant-agent-approvals.js');
        $retention = $this->source('assets/js/merchant-crm-retention-playbooks.js');
        foreach (['/merchant-agent-execution.php','Execution Center'] as $needle) {
            self::assertStringContainsString($needle, $automationView . $monitorView . $approvalsView . $retention);
        }
        foreach (['Open execution center','/merchant-agent-execution.php'] as $needle) {
            self::assertStringContainsString($needle, $approvalsJs);
        }
    }
}
