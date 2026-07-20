<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase34PurchaseConfirmationPppmTrackingV1ContractTest extends TestCase
{
    public function testTrackingAuthorityUsesBuyerOwnedExactProductVersionMatches(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-order-tracking.php');
        self::assertIsString($service);
        foreach([
            'o.buyer_user_id=?','s.owner_user_id=o.buyer_user_id','s.agent_id=?',"s.status='selected'",'s.plan_id IS NOT NULL',
            's.product_version_id=oi.product_version_id','cpv.public_id product_version_id','match_basis','exact_product_version',
            'mg_order_issuance_summary','commerce_orders','commerce_order_items','receipts','pppm_items','microgift_instances','microgift_inbox_items',
        ] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testTrackingProjectionContainsCanonicalPaymentReceiptAndIssuanceState(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-order-tracking.php');
        self::assertIsString($service);
        foreach([
            'payment_status','fulfillment_status','receipt_number','receipt_status','expected_units','pppm_items','microgifts','inbox_items','issued_units','missing','complete','state',
            '/checkout-success.php?order=','/account/orders.php','/account-commerce.php','/inbox.php',
        ] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testAuthorityAndRouterAreReadOnlyAndZeroAi(): void
    {
        $root=dirname(__DIR__,2);
        $service=file_get_contents($root.'/includes/task-agent-order-tracking.php');
        $router=file_get_contents($root.'/includes/task-agent-order-tracking-router.php');
        foreach([$service,$router] as $value)self::assertIsString($value);
        foreach(['INSERT INTO','UPDATE commerce_orders','DELETE FROM','mg_order_issuance_reconcile','mg_checkout_create_order','mg_payment','mg_refund','mg_anthropic_messages','mg_ai_credit_consume'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$service);
            self::assertStringNotContainsString($forbidden,$router);
        }
        self::assertStringContainsString("'response_source'=>'system_query'",$router);
        self::assertStringContainsString("'read_only'=>true",$service);
    }

    public function testOrderTrackingRoutePrecedesDeliveryPlanningAndAi(): void
    {
        $root=dirname(__DIR__,2);
        $runtime=file_get_contents($root.'/includes/multi-agent-runtime.php');
        $router=file_get_contents($root.'/includes/task-agent-order-tracking-router.php');
        foreach([$runtime,$router] as $value)self::assertIsString($value);
        $order=strpos($runtime,'?? mg_task_agent_order_tracking_route');
        $delivery=strpos($runtime,'?? mg_task_agent_delivery_route');
        $plan=strpos($runtime,'?? mg_task_agent_plan_selection_route');
        $ai=strpos($runtime,'$synthesis = mg_task_agent_ai_synthesis');
        self::assertNotFalse($order);self::assertNotFalse($delivery);self::assertNotFalse($plan);self::assertNotFalse($ai);
        self::assertLessThan($delivery,$order);
        self::assertLessThan($plan,$delivery);
        self::assertLessThan($ai,$plan);
        self::assertStringContainsString('show_order_tracking',$router);
        self::assertStringContainsString('order_tracking_not_found',$router);
    }

    public function testModelContextIsCompactAndContainsNoInternalIdentifiers(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-order-tracking.php');
        self::assertIsString($service);
        $start=strpos($service,'function mg_task_agent_order_tracking_for_model');
        self::assertNotFalse($start);
        $model=substr($service,$start);
        self::assertStringContainsString('array_slice($items,0,8)',$model);
        foreach(['order_internal_id','buyer_user_id','actor_user_id','source_reference','idempotency_key','receipt_id','shortlist_id','plan_id','product_version_id'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$model);
        }
        foreach(['payment_status','fulfillment_status','issuance_state','issued_units','pppm_items','microgifts','inbox_items'] as $marker){
            self::assertStringContainsString($marker,$model);
        }
    }

    public function testCanvasUsesSafeInternalLinksAndNoMutationRequests(): void
    {
        $root=dirname(__DIR__,2);
        $script=file_get_contents($root.'/assets/js/task-agent-order-tracking-runtime.js');
        $page=file_get_contents($root.'/agent.php');
        foreach([$script,$page] as $value)self::assertIsString($value);
        foreach(['purchase_tracking','Open order confirmation','Open Inbox','View orders','Commerce center','Read only','internalUrl'] as $marker)self::assertStringContainsString($marker,$script);
        foreach(['fetch(','method:',"action: '",'cart-items.php','checkout-session.php','reconcile_issuance','refund_order','/api/payments/','action-center-send.php','microgift-claim.php'] as $forbidden)self::assertStringNotContainsString($forbidden,$script);
        self::assertStringContainsString('/assets/js/task-agent-order-tracking-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-order-tracking-v1.css?v=1.0.0',$page);
    }

    public function testAgentApiExposesTrackingOnlyThroughReadContext(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/agents/runtime.php');
        self::assertIsString($api);
        self::assertStringContainsString("'order_tracking'=>\$context['order_tracking']??[]",$api);
        self::assertStringContainsString("'order_tracking_schema_ready'=>\$context['order_tracking_schema_ready']??false",$api);
        foreach(["action==='capture_payment'","action==='reconcile_issuance'","action==='refund_order'","action==='claim_gift'","action==='redeem_gift'"] as $forbidden)self::assertStringNotContainsString($forbidden,$api);
    }
}
