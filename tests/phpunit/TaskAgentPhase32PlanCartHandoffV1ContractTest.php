<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase32PlanCartHandoffV1ContractTest extends TestCase
{
    public function testSelectionAuthorityIsOwnerAgentPlanAndVersionScoped(): void
    {
        $root=dirname(__DIR__,2);
        $service=file_get_contents($root.'/includes/task-agent-plan-selection.php');
        $guard=file_get_contents($root.'/includes/task-agent-plan-selection-guard.php');
        foreach([$service,$guard] as $value)self::assertIsString($value);
        foreach([
            'mg_task_agent_select_shortlist_for_plan','mg_task_agent_remove_plan_selection','owner_user_id=? AND s.agent_id=?',
            "p.status IN ('draft','planned','ready')","cp.current_version_id=s.product_version_id","cpv.version_status='published'",
            "cpvl.availability_status='available'",'recipient_context_json','hash_equals','selected_product','selected_product_source',
            "status='selected'",'plan_id=?','selected_at=NOW()','multi_agent.plan_product_selected','multi_agent.plan_product_removed',
        ] as $marker)self::assertStringContainsString($marker,$service);
        self::assertStringContainsString('Remove this product from its gift plan before removing it from the shortlist.',$guard);
        self::assertStringContainsString('mg_task_agent_shortlist_add_without_overwriting_selection',$guard);
        self::assertStringNotContainsString('mg_anthropic_messages',$service);
        self::assertStringNotContainsString('mg_ai_credit_consume',$service);
    }

    public function testSelectionRoutingPrecedesDiscoveryAndAi(): void
    {
        $root=dirname(__DIR__,2);
        $runtime=file_get_contents($root.'/includes/multi-agent-runtime.php');
        $router=file_get_contents($root.'/includes/task-agent-plan-selection-router.php');
        foreach([$runtime,$router] as $value)self::assertIsString($value);
        foreach(['select_product','remove_selection','show_selection','select_plan_product_context_mismatch','approval_required'] as $marker)self::assertStringContainsString($marker,$router);
        $planRoute=strpos($runtime,'mg_task_agent_plan_selection_route');
        $shortlistRoute=strpos($runtime,'mg_task_agent_shortlist_route');
        $synthesis=strpos($runtime,'mg_task_agent_ai_synthesis');
        self::assertNotFalse($planRoute);self::assertNotFalse($shortlistRoute);self::assertNotFalse($synthesis);
        self::assertLessThan($shortlistRoute,$planRoute);
        self::assertLessThan($synthesis,$shortlistRoute);
    }

    public function testApiWritesOnlyPlanSelectionAndNeverCreatesCartOrCheckout(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/agents/runtime.php');
        self::assertIsString($api);
        foreach(['mg_agent_require_owned','mg_require_csrf_for_write',"$action==='select_plan_product'","$action==='remove_plan_product'",'mg_task_agent_select_shortlist_for_plan','mg_task_agent_remove_plan_selection',"'used_ai'=>false","'response_source'=>'system_action'"] as $marker)self::assertStringContainsString($marker,$api);
        foreach(['cart-items.php','order-checkout-session.php','mg_cart','stripe','action-center-send.php','microgift-claim.php'] as $forbidden)self::assertStringNotContainsString($forbidden,$api);
    }

    public function testCanvasRequiresExplicitPlanAndCartClicksAndUsesCanonicalCartApi(): void
    {
        $root=dirname(__DIR__,2);
        $script=file_get_contents($root.'/assets/js/task-agent-shortlist-runtime.js');
        $page=file_get_contents($root.'/agent.php');
        foreach([$script,$page] as $value)self::assertIsString($value);
        foreach(['data-plan-product-select','data-plan-product-remove','data-plan-cart-handoff',"action:'select_plan_product'","action:'remove_plan_product'",'/api/commerce/cart-items.php','product_version_id','quantity:Number','window.location.assign(\'/cart.php\')','Review product','Add to cart'] as $marker)self::assertStringContainsString($marker,$script);
        self::assertStringContainsString('No cart change and no AI credits used.',$script);
        foreach(['order-checkout-session.php','payments/webhook.php','action-center-send.php','microgift-claim.php'] as $forbidden)self::assertStringNotContainsString($forbidden,$script);
        self::assertStringContainsString('/assets/js/task-agent-shortlist-runtime.js?v=1.1.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-shortlist-v1.css?v=1.1.0',$page);
    }

    public function testModelReceivesOnlyCompactSelectedProductProjection(): void
    {
        $root=dirname(__DIR__,2);
        $service=file_get_contents($root.'/includes/task-agent-plan-selection.php');
        $router=file_get_contents($root.'/includes/task-agent-plan-selection-router.php');
        $runtime=file_get_contents($root.'/includes/multi-agent-runtime.php');
        foreach([$service,$router,$runtime] as $value)self::assertIsString($value);
        $start=strpos($service,'function mg_task_agent_plan_selection_for_model');
        self::assertNotFalse($start);
        $projection=substr($service,$start);
        self::assertStringContainsString('array_slice($selections,0,8)',$projection);
        self::assertStringContainsString('plan_title',$projection);
        self::assertStringContainsString('product_title',$projection);
        self::assertStringNotContainsString('contact_id',$projection);
        self::assertStringNotContainsString('recommendation_json',$projection);
        self::assertStringContainsString('mg_task_agent_plan_selection_model_context',$router);
        self::assertStringContainsString('mg_task_agent_plan_selection_model_context($context)',$runtime);
    }
}
