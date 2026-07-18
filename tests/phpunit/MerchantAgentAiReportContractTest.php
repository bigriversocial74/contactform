<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentAiReportContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function file(string $path): string
    {
        $content = file_get_contents($this->root . '/' . ltrim($path, '/'));
        self::assertIsString($content, 'Unable to read ' . $path);
        return $content;
    }

    public function testAiReportAndDrilldownsAreQuickDatabaseOnlyKeywords(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-report.php');
        self::assertStringContainsString('function mg_merchant_ai_report_command', $helper);
        self::assertStringContainsString('(details|alerts|recent)', $helper);
        self::assertStringContainsString('7|14|30|60|90|180|365', $helper);
        self::assertStringContainsString('function mg_merchant_ai_report_mode', $helper);
        self::assertStringContainsString("'database_only'=>true", $helper);
        self::assertStringContainsString("'used_ai'=>false", $helper);
        self::assertStringNotContainsString('mg_anthropic_messages(', $helper);
        self::assertStringNotContainsString('mg_merchant_agent_ai_begin_call', $helper);
    }

    public function testSummaryReconcilesOwnerCreditsProviderUsageAndLedger(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-report.php');
        self::assertStringContainsString('mg_ai_credit_snapshot(', $helper);
        self::assertStringContainsString('mg_merchant_agent_ai_credit_apply_package_gate(', $helper);
        self::assertStringContainsString('mg_merchant_agent_ai_status(', $helper);
        self::assertStringContainsString('FROM ai_credit_ledger', $helper);
        self::assertStringContainsString('FROM ai_usage_events e', $helper);
        self::assertStringContainsString('FROM security_logs', $helper);
        self::assertStringContainsString("LEFT(source_type,15)='merchant_agent_'", $helper);
        self::assertStringContainsString("entry_type='usage_debit'", $helper);
        self::assertStringContainsString('source_reference', $helper);
        self::assertStringContainsString("'token_difference'=>\$difference", $helper);
        self::assertStringContainsString("'balanced'=>\$balanced", $helper);
        self::assertStringContainsString('WHERE user_id=?', $helper);
    }

    public function testDetailsReturnExactProviderAndLedgerRecords(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-report.php');
        self::assertStringContainsString("'provider_records'=>array_map", $helper);
        self::assertStringContainsString("'recent_ledger'=>array_map", $helper);
        self::assertStringContainsString('LEFT JOIN ai_models m ON m.id=e.model_id', $helper);
        self::assertStringContainsString('LEFT JOIN ai_models m ON m.id=l.model_id', $helper);
        self::assertStringContainsString('function mg_merchant_ai_report_provider_body', $helper);
        self::assertStringContainsString('function mg_merchant_ai_report_ledger_body', $helper);
        self::assertStringContainsString("'title'=>'AI reconciliation details'", $helper);
        self::assertStringContainsString("'model_name'=>(string)", $helper);
        self::assertStringContainsString("'source_reference'=>(string)", $helper);
    }

    public function testAlertsReturnExactOwnerScopedAccountingEvents(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-report.php');
        self::assertStringContainsString("'merchant_agent.ai_credit_debit_failed'", $helper);
        self::assertStringContainsString("'merchant_agent.ai_preflight_state_missing'", $helper);
        self::assertStringContainsString("'merchant_agent.ai_call_context_missing'", $helper);
        self::assertStringContainsString('SELECT id,severity,event_type,request_id,message,context_json,created_at', $helper);
        self::assertStringContainsString("'recent_alerts'=>array_map", $helper);
        self::assertStringContainsString('function mg_merchant_ai_report_alert_body', $helper);
        self::assertStringContainsString("'title'=>'AI accounting alerts'", $helper);
        self::assertStringContainsString('WHERE user_id=?', $helper);
    }

    public function testRecentModeShowsModelSourceTokensAndReference(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-report.php');
        self::assertStringContainsString("'title'=>'Recent Merchant Agent AI usage'", $helper);
        self::assertStringContainsString('Missing response reference', $helper);
        self::assertStringContainsString('input +', $helper);
        self::assertStringContainsString('output =', $helper);
        self::assertStringContainsString('debited tokens', $helper);
        self::assertStringContainsString('Ref:', $helper);
    }

    public function testReportRendersAllModesInsideTheExistingChatFeed(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-report.php');
        self::assertStringContainsString('function mg_merchant_ai_report_chat_response', $helper);
        self::assertStringContainsString('mg_merchant_ai_report_mode($message)', $helper);
        self::assertStringContainsString('mg_ai_chat_record_message(', $helper);
        self::assertStringContainsString("'blocks'=>\$report['blocks']", $helper);
        self::assertStringContainsString("'model'=>'database-ai-report-v2'", $helper);
        self::assertStringContainsString("title=IF(title='Current chat','AI Report',title)", $helper);
        self::assertStringContainsString("'report_mode'=>\$mode", $helper);
        self::assertStringContainsString("'assistant_message'=>[", $helper);
        self::assertStringContainsString("'ai_report'=>\$report", $helper);
    }

    public function testChatEndpointRoutesEveryReportModeBeforeExternalAiContext(): void
    {
        $endpoint = $this->file('api/ai/merchant-agent-chat.php');
        self::assertStringContainsString('merchant-agent-ai-report.php', $endpoint);
        self::assertStringContainsString("mg_merchant_ai_report_is_keyword(\$input['message'] ?? '')", $endpoint);
        self::assertStringContainsString("'send_message','snapshot','ai_report','crm_search','contact_action'", $endpoint);
        self::assertStringContainsString("if (\$action === 'ai_report')", $endpoint);
        self::assertStringContainsString('mg_merchant_ai_report_chat_response($pdo, $user, $packageContext, $input)', $endpoint);

        $reportHandler = strpos($endpoint, "if (\$action === 'ai_report')");
        $aiContext = strpos($endpoint, 'mg_merchant_agent_ai_begin_call');
        self::assertNotFalse($reportHandler);
        self::assertNotFalse($aiContext);
        self::assertLessThan($aiContext, $reportHandler, 'AI Report must return before the external AI context starts.');

        $handlerSource = substr($endpoint, $reportHandler, strpos($endpoint, "if (\$action === 'crm_search')", $reportHandler) - $reportHandler);
        self::assertStringNotContainsString('merchant.ai.plan', $handlerSource);
        self::assertStringNotContainsString('merchant.ai.review', $handlerSource);
        self::assertStringNotContainsString('mg_merchant_agent_ai_begin_call', $handlerSource);
    }

    public function testAiUnavailableUiStillAllowsEveryDrilldownKeyword(): void
    {
        $ui = $this->file('assets/js/merchant-agent-ai-status.js');
        self::assertStringContainsString('details|alerts|recent', $ui);
        self::assertStringContainsString('isSystematic(text)', $ui);
        self::assertStringContainsString('root.dataset.merchantAgentAiCanGenerate', $ui);
    }

    public function testReportPreservesOwnerScopeCsrfAndRateLimit(): void
    {
        $endpoint = $this->file('api/ai/merchant-agent-chat.php');
        self::assertStringContainsString('mg_merchant_agent_require_owner_access($pdo)', $endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $endpoint);
        self::assertStringContainsString("mg_rate_limit('merchant.agent.ai_report.chat'", $endpoint);
        self::assertStringContainsString('$merchantOwnerId !== $actorId', $endpoint);
    }
}
