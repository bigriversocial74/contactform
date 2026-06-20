<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class Stage5IFinancialOperationsTest extends TestCase
{
    public function testSchemaDefinesCommerceAndFinancialTables(): void
    {
        $sql = file_get_contents(dirname(__DIR__,2).'/database/stage_5i_payments_checkout_reconciliation.sql');
        self::assertIsString($sql);
        foreach (['commerce_orders','commerce_order_items','checkout_sessions','payment_intents','payment_transactions','payment_refunds','payment_disputes','merchant_payouts','financial_ledger_entries','payment_webhook_events','financial_reconciliation_runs'] as $table) {
            self::assertStringContainsString($table, $sql);
        }
    }

    public function testCheckoutUsesPublishedServerSideProductSnapshots(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/api/payments/checkout-session.php');
        self::assertIsString($source);
        self::assertStringContainsString("v.version_status='published'", $source);
        self::assertStringContainsString("p.status='published'", $source);
        self::assertStringContainsString('unit_value_cents', $source);
        self::assertStringContainsString('idempotency_key', $source);
    }

    public function testPaidOrdersIssuePppmWithoutMergingIdentities(): void
    {
        $fulfillment = file_get_contents(dirname(__DIR__,2).'/api/payments/_fulfillment.php');
        $capture = file_get_contents(dirname(__DIR__,2).'/api/payments/_capture.php');
        self::assertIsString($fulfillment);
        self::assertIsString($capture);
        self::assertStringContainsString('pppm_issuance_requests', $fulfillment);
        self::assertStringContainsString('pppm_items', $fulfillment);
        self::assertStringContainsString('pppm_issuance_request_id', $fulfillment);
        self::assertStringContainsString('customer_purchase', $fulfillment);
        self::assertStringContainsString('mg_payment_issue_order_pppm', $capture);
    }

    public function testWebhookRequiresSignatureAndCanProcessPaidOrders(): void
    {
        $endpoint = file_get_contents(dirname(__DIR__,2).'/api/payments/webhook.php');
        $service = file_get_contents(dirname(__DIR__,2).'/api/payments/_webhook.php');
        self::assertIsString($endpoint);
        self::assertIsString($service);
        self::assertStringContainsString('mg_payment_verify_signature', $endpoint);
        self::assertStringContainsString('HTTP_STRIPE_SIGNATURE', $endpoint);
        self::assertStringContainsString('mg_payment_process_webhook_event', $endpoint);
        self::assertStringContainsString('provider_event_id', $service);
        self::assertStringContainsString('payload_hash', $service);
        self::assertStringContainsString('mg_finance_record_paid_order', $service);
    }

    public function testRefundsAreMerchantScopedAndIdempotent(): void
    {
        $endpoint = file_get_contents(dirname(__DIR__,2).'/api/merchant/refund.php');
        $service = file_get_contents(dirname(__DIR__,2).'/api/payments/_refund.php');
        self::assertIsString($endpoint);
        self::assertIsString($service);
        self::assertStringContainsString("mg_require_permission('merchant.refunds.manage')", $endpoint);
        self::assertStringContainsString('mg_finance_refund_order(', $endpoint);
        self::assertStringContainsString('o.merchant_user_id=?', $service);
        self::assertStringContainsString('idempotency_key', $service);
        self::assertStringContainsString('Idempotency key conflicts with an existing refund.', $service);
        self::assertStringContainsString('Refund exceeds the remaining paid amount.', $service);
    }

    public function testFinancialDashboardAndReconciliationAreMerchantScoped(): void
    {
        foreach (['financial-dashboard.php','reconciliation.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__,2).'/api/merchant/'.$file);
            self::assertIsString($source);
            self::assertStringContainsString('merchant_user_id=?', $source);
        }
    }

    public function testPaymentAndPppmIdentitiesRemainSeparate(): void
    {
        $sql = file_get_contents(dirname(__DIR__,2).'/database/stage_5i_payments_checkout_reconciliation.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('pppm_issuance_request_id', $sql);
        self::assertStringNotContainsString('payment_intent_id CHAR(36) NOT NULL PRIMARY KEY', $sql);
    }

    public function testMerchantPaymentPageUsesSharedShell(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/merchant-payments.php');
        self::assertIsString($page);
        self::assertStringContainsString('includes/merchant-workspace.php',$page);
    }
}
