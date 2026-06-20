<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class V1StageCPurchaseIssuanceContractTest extends TestCase
{
    private function source(string $path): string
    {
        $value=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($value,$path);
        return $value;
    }

    public function testCheckoutSessionOwnsOnePaymentIntent(): void
    {
        $migration=$this->source('database/stage_v1c_checkout_session_intent_authority.sql');
        $service=$this->source('api/payments/_checkout_session.php');
        $manifest=require dirname(__DIR__,2).'/config/migrations.php';
        self::assertContains('stage_v1c_checkout_session_intent_authority.sql',$manifest['ordered_files']);
        self::assertStringContainsString("COLUMN_NAME = 'payment_intent_id'",$migration);
        self::assertStringContainsString('ADD COLUMN payment_intent_id BIGINT UNSIGNED NULL AFTER order_id',$migration);
        self::assertStringContainsString('fk_checkout_sessions_payment_intent',$migration);
        self::assertStringContainsString('LEFT JOIN checkout_sessions cs ON cs.payment_intent_id=pi.id',$service);
        self::assertStringContainsString('INNER JOIN payment_intents pi ON pi.id=cs.payment_intent_id',$service);
        self::assertStringContainsString('(public_id,order_id,payment_intent_id,provider_key,status',$service);
    }

    public function testSandboxConfirmationRejectsClosedOrExpiredSessions(): void
    {
        $endpoint=$this->source('api/payments/sandbox-confirm.php');
        self::assertStringContainsString('pi.id=cs.payment_intent_id AND pi.order_id=o.id',$endpoint);
        self::assertStringContainsString("!in_array((string)\$row['session_status'],['created','open'],true)",$endpoint);
        self::assertStringContainsString("strtotime((string)\$row['expires_at'])<=time()",$endpoint);
        self::assertStringContainsString("SET status='expired'",$endpoint);
        self::assertStringContainsString('Payment intent does not match the checkout order.',$endpoint);
        self::assertStringContainsString('microgift_issued_count',$endpoint);
    }

    public function testExpiredCheckoutCanResumeTheSameUnpaidOrder(): void
    {
        $session=$this->source('api/payments/session.php');
        $client=$this->source('assets/js/checkout.js');
        self::assertStringContainsString("\$session['session_status']='expired'",$session);
        self::assertStringContainsString("\$session['can_confirm']",$session);
        self::assertStringContainsString('data-checkout-restart',$client);
        self::assertStringContainsString("'/api/payments/order-checkout-session.php'",$client);
        self::assertStringContainsString('order_id:session.order_id',$client);
        self::assertStringContainsString('Your unpaid order is preserved',$client);
    }

    public function testPppmAndMicrogiftIssuerAuthorityIsTheMerchant(): void
    {
        $fulfillment=$this->source('api/payments/_fulfillment.php');
        self::assertStringContainsString("\$issuerUserId=(int)\$order['merchant_user_id']",$fulfillment);
        self::assertStringContainsString("\$requestStmt->execute([\$requestPublicId,(int)\$source['id'],\$sourceEventId,\$issuerUserId,\$issuerUserId",$fulfillment);
        self::assertStringContainsString("mg_microgift_issue(\$pdo,(int)\$order['merchant_user_id']",$fulfillment);
        self::assertStringContainsString("'recipient_user_id'=>(int)\$order['buyer_user_id']",$fulfillment);
    }

    public function testPurchaseCreatesBuyerInboxWithoutMerchantSentProjection(): void
    {
        $fulfillment=$this->source('api/payments/_fulfillment.php');
        $behavior=$this->source('scripts/validate_checkout_capture_behavior.php');
        self::assertStringContainsString('mg_action_center_receive(',$fulfillment);
        self::assertStringNotContainsString('mg_action_center_project_lifecycle($pdo,$instance)',$fulfillment);
        self::assertStringContainsString('A merchant Sent item was incorrectly created for a customer purchase.',$behavior);
        self::assertStringContainsString("'buyer_inbox_only'=>false",$behavior);
    }

    public function testOrderConfirmationProvesOneToOneIssuance(): void
    {
        $summary=$this->source('api/commerce/_order_issuance_summary.php');
        $endpoint=$this->source('api/commerce/order-confirmation.php');
        $client=$this->source('assets/js/order-success.js');
        self::assertStringContainsString("'expected_units'=>\$expectedUnits",$summary);
        self::assertStringContainsString("'pppm_items'=>\$pppmItems",$summary);
        self::assertStringContainsString("'microgifts'=>\$microgiftItems",$summary);
        self::assertStringContainsString("'inbox_items'=>\$inboxItems",$summary);
        self::assertStringContainsString("'issuance'=>mg_order_issuance_summary",$endpoint);
        self::assertStringContainsString('FROM order_audit_events',$endpoint);
        self::assertStringContainsString('status_domain AS domain',$endpoint);
        self::assertStringNotContainsString('FROM order_events',$endpoint);
        self::assertStringContainsString('Gifts issued',$client);
        self::assertStringContainsString('Each purchased quantity creates one permanent PPPM item',$client);
    }
}
