<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AiCreditReconciliationIncidentQueueContractTest extends TestCase
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

    public function testMigrationAddsDurableEvidenceRunsIncidentsActionsAndPermissions(): void
    {
        $sql = $this->file('database/20260718_ai_credit_reconciliation_incidents.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ai_credit_provider_responses', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ai_credit_reconciliation_runs', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ai_credit_reconciliation_incidents', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ai_credit_reconciliation_actions', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_ai_credit_reconciliation_incidents_key (incident_key)', $sql);
        self::assertStringContainsString("ENUM('open','under_review','resolved','dismissed')", $sql);
        self::assertStringContainsString('admin.ai_credit_incidents.view', $sql);
        self::assertStringContainsString('admin.ai_credit_incidents.manage', $sql);
        self::assertStringContainsString('admin.ai_credit_incidents.retry', $sql);

        $manifest = $this->file('config/migrations.php');
        self::assertStringContainsString("'20260718_ai_credit_reconciliation_incidents.sql'", $manifest);
        self::assertLessThan(
            strpos($manifest, "'stage_19_merchant_market_snapshots.sql'"),
            strpos($manifest, "'20260718_ai_credit_reconciliation_incidents.sql'")
        );
    }

    public function testProviderResponsesAreCapturedBeforeDebitAndMarkedAfterAccounting(): void
    {
        $hook = $this->file('includes/ai/merchant-agent-credit-response.php');
        self::assertStringContainsString("require_once __DIR__ . '/ai-credit-reconciliation.php'", $hook);
        self::assertStringContainsString('mg_ai_reconciliation_capture_provider_response(', $hook);
        self::assertStringContainsString('mg_ai_reconciliation_mark_provider_accounting(', $hook);
        self::assertStringContainsString('mg_ai_credit_consume(', $hook);

        $capture = strpos($hook, 'mg_ai_reconciliation_capture_provider_response(');
        $debit = strpos($hook, 'mg_ai_credit_consume(');
        $accounted = strpos($hook, "mg_ai_reconciliation_mark_provider_accounting(\$pdo, \$userId, 'anthropic', \$responseId, true");
        self::assertNotFalse($capture);
        self::assertNotFalse($debit);
        self::assertNotFalse($accounted);
        self::assertLessThan($debit, $capture, 'Durable provider evidence must be captured before the debit attempt.');
        self::assertLessThan($accounted, $debit, 'Provider evidence must be marked accounted only after credit consumption.');
    }

    public function testReconciliationDetectsDeduplicatesReopensAndAutoResolvesIncidents(): void
    {
        $service = $this->file('includes/ai/ai-credit-reconciliation.php');
        self::assertStringContainsString('function mg_ai_reconciliation_run', $service);
        self::assertStringContainsString('function mg_ai_reconciliation_scan_user', $service);
        self::assertStringContainsString('function mg_ai_reconciliation_upsert_incident', $service);
        self::assertStringContainsString('mg_ai_reconciliation_incident_key', $service);
        self::assertStringContainsString("'provider_without_ledger'", $service);
        self::assertStringContainsString("'ledger_without_provider'", $service);
        self::assertStringContainsString("'token_mismatch'", $service);
        self::assertStringContainsString("'missing_response_reference'", $service);
        self::assertStringContainsString("'credit_debit_failed'", $service);
        self::assertStringContainsString("'preflight_state_missing'", $service);
        self::assertStringContainsString("'call_context_missing'", $service);
        self::assertStringContainsString('reopened_by_detection', $service);
        self::assertStringContainsString('auto_resolved', $service);
        self::assertStringContainsString('occurrence_count=occurrence_count+1', $service);
        self::assertStringContainsString("LEFT(l.source_type,15)='merchant_agent_'", $service);
        self::assertStringContainsString('WHERE user_id=?', $service);
    }

    public function testControlledRetryUsesOriginalReferenceAndExistingIdempotentCreditConsume(): void
    {
        $service = $this->file('includes/ai/ai-credit-reconciliation.php');
        self::assertStringContainsString('function mg_ai_reconciliation_retry_debit', $service);
        self::assertStringContainsString('Durable provider response evidence was not found', $service);
        self::assertStringContainsString('mg_ai_credit_consume(', $service);
        self::assertStringContainsString("'reconciliation_retry'=>true", $service);
        self::assertStringContainsString("'retry_debit'", $service);
        self::assertStringContainsString('mg_ai_reconciliation_run(', $service);

        $credits = $this->file('includes/ai/user-credit-service.php');
        self::assertStringContainsString("implode(':',['usage',\$userId,\$providerKey,\$sourceType,\$sourceReference])", $credits);
        self::assertStringContainsString('SELECT id FROM ai_credit_ledger WHERE idempotency_key=? LIMIT 1', $credits);
    }

    public function testAdminApiAndPageRequirePermissionsCsrfRateLimitsAndAuditHistory(): void
    {
        $api = $this->file('api/admin/ai-credit-incidents.php');
        self::assertStringContainsString('mg_require_api_user()', $api);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
        self::assertStringContainsString("mg_rate_limit('admin.ai_credit_incidents.read'", $api);
        self::assertStringContainsString("mg_rate_limit('admin.ai_credit_incidents.write'", $api);
        self::assertStringContainsString('admin.ai_credit_incidents.view', $api);
        self::assertStringContainsString('admin.ai_credit_incidents.manage', $api);
        self::assertStringContainsString('admin.ai_credit_incidents.retry', $api);
        self::assertStringContainsString("'run_reconciliation'", $api);
        self::assertStringContainsString("'retry_debit'", $api);

        $page = $this->file('admin/ai-credit-incidents.php');
        self::assertStringContainsString("mg_require_admin_page_permission('admin.ai_credit_incidents')", $page);
        self::assertStringContainsString('data-ai-credit-incidents', $page);
        self::assertStringContainsString('data-run-reconciliation', $page);
        self::assertStringContainsString('data-incident-list', $page);
        self::assertStringContainsString('data-incident-detail', $page);

        $js = $this->file('assets/js/admin-ai-credit-incidents.js');
        self::assertStringContainsString('/api/admin/ai-credit-incidents.php', $js);
        self::assertStringContainsString("action:'run_reconciliation'", $js);
        self::assertStringContainsString("'retry_debit'", $js);
        self::assertStringContainsString("'resolve'", $js);
        self::assertStringContainsString("'dismiss'", $js);
        self::assertStringNotContainsString('assigned_admin_user_id=0', $js);
    }

    public function testScheduledRunnerIsCliOnlyAndDatabaseOnly(): void
    {
        $runner = $this->file('scripts/run_ai_credit_reconciliation.php');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $runner);
        self::assertStringContainsString('mg_ai_reconciliation_run(', $runner);
        self::assertStringContainsString("'trigger_source'=>\$trigger", $runner);
        self::assertStringNotContainsString('mg_anthropic_messages(', $runner);

        $service = $this->file('includes/ai/ai-credit-reconciliation.php');
        self::assertStringNotContainsString('mg_anthropic_messages(', $service);
        self::assertStringNotContainsString('mg_merchant_agent_ai_begin_call(', $service);
    }

    public function testMerchantAlertsUseOwnerScopedIncidentQueueBeforeExternalAi(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-ai-incident-alerts.php');
        self::assertStringContainsString('mg_ai_reconciliation_user_incidents($pdo, $merchantId', $helper);
        self::assertStringContainsString("'database_only'=>true", $helper);
        self::assertStringContainsString("'used_ai'=>false", $helper);
        self::assertStringContainsString('mg_ai_chat_record_message(', $helper);
        self::assertStringNotContainsString('mg_anthropic_messages(', $helper);

        $endpoint = $this->file('api/ai/merchant-agent-chat.php');
        self::assertStringContainsString('merchant-agent-ai-incident-alerts.php', $endpoint);
        self::assertStringContainsString("\$reportMode === 'alerts' && mg_ai_reconciliation_schema_ready(\$pdo)", $endpoint);
        self::assertStringContainsString('mg_merchant_ai_incident_alert_chat_response($pdo, $user, $input)', $endpoint);
        $alerts = strpos($endpoint, 'mg_merchant_ai_incident_alert_chat_response');
        $external = strpos($endpoint, 'mg_merchant_agent_ai_begin_call');
        self::assertNotFalse($alerts);
        self::assertNotFalse($external);
        self::assertLessThan($external, $alerts, 'AI Report Alerts must return before any external model context starts.');
    }

    public function testOperationsDocumentationIncludesMigrationCronQueueAndRetrySafety(): void
    {
        $docs = $this->file('docs/operations/ai-credit-reconciliation.md');
        self::assertStringContainsString('database/20260718_ai_credit_reconciliation_incidents.sql', $docs);
        self::assertStringContainsString('scripts/run_ai_credit_reconciliation.php', $docs);
        self::assertStringContainsString('0 * * * *', $docs);
        self::assertStringContainsString('/admin/ai-credit-incidents.php', $docs);
        self::assertStringContainsString('idempotency', strtolower($docs));
        self::assertStringContainsString('AI Report Alerts', $docs);
    }
}
