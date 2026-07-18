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

    public function testAiReportIsAQuickDatabaseOnlyKeyword(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-report.php');
        self::assertStringContainsString('function mg_merchant_ai_report_is_keyword', $helper);
        self::assertStringContainsString('(?:\\/?ai report)', $helper);
        self::assertStringContainsString('7|14|30|60|90|180|365', $helper);
        self::assertStringContainsString("'database_only'=>true", $helper);
        self::assertStringContainsString("'used_ai'=>false", $helper);
        self::assertStringNotContainsString('mg_anthropic_messages(', $helper);
        self::assertStringNotContainsString('mg_merchant_agent_ai_begin_call', $helper);
    }

    public function testReportReconcilesOwnerCreditsProviderUsageAndLedger(): void
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
        self::assertStringContainsString("array_key_exists('available_tokens', \$credits)", $helper);
        self::assertStringContainsString("array_key_exists('package_tokens_remaining', \$credits)", $helper);
    }

    public function testReportRendersInsideTheExistingChatFeed(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-report.php');
        self::assertStringContainsString('function mg_merchant_ai_report_chat_response', $helper);
        self::assertStringContainsString('mg_ai_chat_record_message(', $helper);
        self::assertStringContainsString("'blocks'=>\$report['blocks']", $helper);
        self::assertStringContainsString("'model'=>'database-ai-report-v1'", $helper);
        self::assertStringContainsString("title=IF(title='Current chat','AI Report',title)", $helper);
        self::assertStringContainsString("'assistant_message'=>[", $helper);
        self::assertStringContainsString("'ai_report'=>\$report", $helper);
    }

    public function testChatEndpointRoutesReportBeforeAnyExternalAiContext(): void
    {
        $endpoint = $this->file('api/ai/merchant-agent-chat.php');
        self::assertStringContainsString("merchant-agent-ai-report.php", $endpoint);
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

    public function testAiUnavailableUiStillAllowsTheKeyword(): void
    {
        $ui = $this->file('assets/js/merchant-agent-ai-status.js');
        self::assertStringContainsString('\\/?ai report', $ui);
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
