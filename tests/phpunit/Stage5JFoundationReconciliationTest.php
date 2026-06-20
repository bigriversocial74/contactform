<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class Stage5JFoundationReconciliationTest extends TestCase
{
    public function testSchemaRestoresOriginalStageFiveResources(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_5j_foundation_reconciliation.sql');
        self::assertIsString($sql);
        foreach(['carts','cart_items','checkout_drafts','order_fee_snapshots','order_status_history','receipts','order_audit_events'] as $table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
    }

    public function testStage5JRunnerAppliesCheckoutAuthorityAndFulfillmentBeforeFoundation(): void
    {
        $runner=file_get_contents(dirname(__DIR__,2).'/scripts/stage5j.php');
        self::assertIsString($runner);
        $authority=strpos($runner,'stage_v1c_checkout_session_intent_authority.sql');
        $compatibility=strpos($runner,'stage_3_commerce_microgift_fulfillment.sql');
        $foundation=strpos($runner,'stage_5j_foundation_reconciliation.sql');
        self::assertNotFalse($authority);
        self::assertNotFalse($compatibility);
        self::assertNotFalse($foundation);
        self::assertLessThan($compatibility,$authority);
        self::assertLessThan($foundation,$compatibility);
    }

    public function testCartAndDraftApisUseAuthenticatedOwnershipAndServerSnapshots(): void
    {
        $cart=file_get_contents(dirname(__DIR__,2).'/api/commerce/cart-items.php');
        $draft=file_get_contents(dirname(__DIR__,2).'/api/commerce/checkout-draft.php');
        self::assertIsString($cart);
        self::assertIsString($draft);
        self::assertStringContainsString('mg_require_api_user',$cart);
        self::assertStringContainsString('mg_resolve_published_product_version',$cart);
        self::assertStringContainsString('mg_cart_payload',$draft);
        self::assertStringContainsString('items_json',$draft);
    }

    public function testOrderCreationConsumesOneOpenDraftAndCreatesImmutableRecords(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/commerce/orders.php');
        $service=file_get_contents(dirname(__DIR__,2).'/api/commerce/_checkout.php');
        self::assertIsString($endpoint);
        self::assertIsString($service);
        self::assertStringContainsString("require_once __DIR__ . '/_checkout.php'",$endpoint);
        self::assertStringContainsString('mg_checkout_create_order(',$endpoint);
        foreach(['FOR UPDATE','checkout_drafts','commerce_orders','commerce_order_items','order_fee_snapshots','receipts','mg_order_history','mg_order_event'] as $needle){
            self::assertStringContainsString($needle,$service);
        }
        self::assertStringContainsString("status='converted'",$service);
        self::assertStringContainsString('Order idempotency key is already bound to a different checkout draft.',$service);
    }

    public function testPaymentSessionStartsFromAnExistingOrderAndOwnsOneIntent(): void
    {
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/payments/order-checkout-session.php');
        $service=file_get_contents(dirname(__DIR__,2).'/api/payments/_checkout_session.php');
        self::assertIsString($endpoint);
        self::assertIsString($service);
        self::assertStringContainsString('mg_payment_create_checkout_session(',$endpoint);
        self::assertStringContainsString('buyer_user_id=?',$service);
        self::assertStringContainsString("payment_status']!=='unpaid'",$service);
        self::assertStringContainsString('cs.payment_intent_id=pi.id',$service);
        self::assertStringContainsString('(public_id,order_id,payment_intent_id,provider_key,status',$service);
        self::assertStringContainsString('Payment idempotency key is already bound to another order.',$service);
        self::assertStringContainsString('An active checkout session already exists for this order.',$service);
        self::assertStringNotContainsString('product_version_id',$service);
    }

    public function testPaidOrderFinalizesReceiptAndAddsHistory(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/payments/_capture.php');
        self::assertIsString($source);
        self::assertStringContainsString('order_status_history',$source);
        self::assertStringContainsString('order_audit_events',$source);
        self::assertStringContainsString("status='finalized'",$source);
        self::assertStringContainsString('mg_payment_issue_order_pppm',$source);
        self::assertStringContainsString('Capture replay conflicts with the recorded provider payment.',$source);
    }

    public function testCustomerOrderAndReceiptReadsAreBuyerScoped(): void
    {
        $order=file_get_contents(dirname(__DIR__,2).'/api/commerce/order.php');
        $receipt=file_get_contents(dirname(__DIR__,2).'/api/commerce/receipt.php');
        self::assertIsString($order);
        self::assertIsString($receipt);
        self::assertStringContainsString('buyer_user_id=?',$order);
        self::assertStringContainsString('buyer_user_id=?',$receipt);
    }
}
