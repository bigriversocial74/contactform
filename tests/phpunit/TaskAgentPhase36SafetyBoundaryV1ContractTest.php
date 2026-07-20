<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase36SafetyBoundaryV1ContractTest extends TestCase
{
    public function testAgentApiDoesNotOwnPaymentOrCheckoutCompletion(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/agents/runtime.php');
        self::assertIsString($api);
        foreach([
            "action==='capture_payment'",
            "action==='start_checkout'",
            "action==='complete_order'",
            "action==='refund_order'",
        ] as $marker)self::assertStringNotContainsString($marker,$api);
    }

    public function testAgentApiDoesNotOwnGiftLifecycleMutations(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/agents/runtime.php');
        self::assertIsString($api);
        foreach([
            "action==='send_gift'",
            "action==='claim_gift'",
            "action==='redeem_gift'",
            "action==='follow_up_gift'",
        ] as $marker)self::assertStringNotContainsString($marker,$api);
    }

    public function testPrivateLifecycleCredentialsStayOutOfModelContext(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-lifecycle-tracking.php');
        self::assertIsString($source);
        $start=strpos($source,'function mg_task_agent_lifecycle_for_model');
        self::assertNotFalse($start);
        $model=substr($source,$start);
        foreach(['claim_code','redemption_code','source_reference','action_item_id'] as $marker){
            self::assertStringNotContainsString($marker,$model);
        }
    }

    public function testLifecycleAndPurchaseCanvasesAreReadOnly(): void
    {
        $root=dirname(__DIR__,2);
        foreach([
            'assets/js/task-agent-order-tracking-runtime.js',
            'assets/js/task-agent-lifecycle-runtime.js',
        ] as $file){
            $source=file_get_contents($root.'/'.$file);
            self::assertIsString($source);
            self::assertStringNotContainsString('fetch(',$source);
            self::assertStringContainsString('Read only',$source);
            self::assertStringContainsString('internalUrl',$source);
        }
    }

    public function testDeliveryPreparationDoesNotExposeAddressValues(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-delivery-preparation.php');
        self::assertIsString($source);
        $start=strpos($source,'function mg_task_agent_delivery_for_model');
        self::assertNotFalse($start);
        $model=substr($source,$start);
        foreach(['address_line_1','address_line_2','postal_code','phone','email'] as $marker){
            self::assertStringNotContainsString($marker,$model);
        }
        self::assertStringContainsString('delivery_address_available',$model);
    }

    public function testOnlyReviewableAgentWritesRemain(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/agents/runtime.php');
        self::assertIsString($api);
        foreach([
            "action==='add_shortlist'",
            "action==='select_plan_product'",
            "action==='create_delivery_schedule'",
            'mg_require_csrf_for_write',
        ] as $marker)self::assertStringContainsString($marker,$api);
    }
}
