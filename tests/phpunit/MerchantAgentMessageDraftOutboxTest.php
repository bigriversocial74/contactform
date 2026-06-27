<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentMessageDraftOutboxTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testMessageHelperBuildsMerchantControlledDraftFlow(): void
    {
        $helper = $this->source('includes/merchant-agent-messages.php');
        foreach (['mg_agent_message_queue','mg_agent_message_find_item','mg_agent_message_perform','mg_agent_message_source_events','mg_agent_message_latest_events','mg_agent_message_record'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['crm.agent.message.draft.created','crm.agent.message.draft.edited','crm.agent.message.draft.approved','crm.agent.message.sent','crm.agent.message.discarded','crm.agent.message.followup_created'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['draft','edited','approved','sent','discarded','followup_created','edit_draft','approve_draft','send_message','discard_draft','convert_to_followup_task'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
        foreach (['mg_automation_record_event','mg_agent_execution_queue','mg_agent_execution_find_recommendation','mg_crm_playbook_create_followup','message_draft_id'] as $needle) {
            self::assertStringContainsString($needle, $helper);
        }
    }

    public function testMessageApisAreScopedAndProtected(): void
    {
        $queue = $this->source('api/merchant/agent-message-drafts.php');
        $action = $this->source('api/merchant/agent-message-action.php');
        foreach (['mg_require_method(\'GET\')', "mg_require_permission('merchant.campaigns.view')", 'mg_merchant_ensure_workspace', 'mg_agent_message_queue'] as $needle) {
            self::assertStringContainsString($needle, $queue);
        }
        foreach (['mg_require_method(\'POST\')', "mg_require_permission('merchant.campaigns.manage')", 'mg_require_csrf_for_write', 'mg_agent_message_find_item', 'mg_agent_message_perform'] as $needle) {
            self::assertStringContainsString($needle, $action);
        }
        foreach (['edit_draft','approve_draft','send_message','discard_draft','convert_to_followup_task'] as $needle) {
            self::assertStringContainsString($needle, $action);
        }
    }

    public function testMessagePageAndAssetsAreWired(): void
    {
        $page = $this->source('merchant-agent-messages.php');
        $view = $this->source('includes/merchant-agent-messages-view.php');
        $js = $this->source('assets/js/merchant-agent-messages.js');
        $css = $this->source('assets/css/merchant-agent-messages.css');
        foreach (['Agent Message Draft Outbox','merchant-agent-messages.css','merchant-agent-messages.js','data-merchant-view="agent_messages"'] as $needle) {
            self::assertStringContainsString($needle, $page . $view);
        }
        foreach (['data-merchant-agent-messages','data-agent-message-list','data-agent-message-status','data-message-filter="draft"','data-message-filter="approved"'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        foreach (['/api/merchant/agent-message-drafts.php','/api/merchant/agent-message-action.php','edit_draft','approve_draft','send_message','discard_draft'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        foreach (['.mg-agent-messages-hero','.mg-agent-messages-kpis','.mg-agent-message-card','.mg-agent-message-body'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testExistingAgentSurfacesLinkToMessageOutbox(): void
    {
        $automationView = $this->source('includes/merchant-automation-view.php');
        $monitorView = $this->source('includes/merchant-agent-monitor-view.php');
        $executionView = $this->source('includes/merchant-agent-execution-view.php');
        foreach (['/merchant-agent-messages.php','Message Outbox'] as $needle) {
            self::assertStringContainsString($needle, $automationView . $monitorView . $executionView);
        }
    }
}
