<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase35LifecycleHandoffV1ContractTest extends TestCase
{
    public function testLifecycleAuthorityUsesCanonicalActionCenterContract(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-lifecycle-tracking.php');
        self::assertIsString($service);
        foreach([
            "require_once dirname(__DIR__) . '/api/account/_action_center_contract.php'",
            'mg_action_center_select_sql','mg_action_center_public_item','mg_action_center_contract_items',
            'microgift_inbox_items','microgift_instances','commerce_order_items','commerce_orders','multi_agent_shortlist_items',
        ] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testLifecycleItemsAreUserAgentAndExactPurchaseScoped(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-lifecycle-tracking.php');
        self::assertIsString($service);
        foreach([
            'ac.user_id=?','co.buyer_user_id=?','s.owner_user_id=co.buyer_user_id','s.agent_id=?',
            "s.status='selected'",'s.plan_id IS NOT NULL','s.product_version_id=coi.product_version_id','coi.id=i.commerce_order_item_id',
            'ac.archived_at IS NULL',
        ] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testProjectionContainsCanonicalLifecycleStateAndCapabilities(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-lifecycle-tracking.php');
        self::assertIsString($service);
        foreach([
            'folder','gift','participants','merchant','activity','redemption','capabilities','capability_reasons',
            'sent_at','claimed_at','redeemed_at','resend_count','follow_up_count',
            "'send'=>", "'claim'=>", "'redeem'=>", "'follow_up'=>", "'message'=>",
            'handoff_required','/inbox.php?folder=','/sent.php','/claimed.php',
        ] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testAuthorityAndRouterAreReadOnlyAndZeroAi(): void
    {
        $root=dirname(__DIR__,2);
        $service=file_get_contents($root.'/includes/task-agent-lifecycle-tracking.php');
        $router=file_get_contents($root.'/includes/task-agent-lifecycle-router.php');
        foreach([$service,$router] as $value)self::assertIsString($value);
        foreach([
            'INSERT INTO','UPDATE microgift_','DELETE FROM','action-center-send.php','action-center-claim.php',
            'microgift-claim.php','claim_code','redemption_code','mg_anthropic_messages','mg_ai_credit_consume',
        ] as $forbidden){
            self::assertStringNotContainsString($forbidden,$service);
            self::assertStringNotContainsString($forbidden,$router);
        }
        self::assertStringContainsString("'read_only'=>true",$service);
        self::assertStringContainsString("'handoff_required'=>true",$service);
        self::assertStringContainsString("'response_source'=>'system_query'",$router);
    }

    public function testLifecycleRoutePrecedesOrderDeliveryPlanningAndAi(): void
    {
        $root=dirname(__DIR__,2);
        $runtime=file_get_contents($root.'/includes/multi-agent-runtime.php');
        $router=file_get_contents($root.'/includes/task-agent-lifecycle-router.php');
        foreach([$runtime,$router] as $value)self::assertIsString($value);
        $lifecycle=strpos($runtime,'$route=mg_task_agent_lifecycle_route');
        $order=strpos($runtime,'??mg_task_agent_order_tracking_route');
        $delivery=strpos($runtime,'??mg_task_agent_delivery_route');
        $ai=strpos($runtime,'$synthesis=mg_task_agent_ai_synthesis');
        self::assertNotFalse($lifecycle);self::assertNotFalse($order);self::assertNotFalse($delivery);self::assertNotFalse($ai);
        self::assertLessThan($order,$lifecycle);
        self::assertLessThan($delivery,$order);
        self::assertLessThan($ai,$delivery);
        self::assertStringContainsString('show_lifecycle_tracking',$router);
        self::assertStringContainsString('lifecycle_tracking_not_found',$router);
    }

    public function testModelContextIsCompactAndExcludesPrivateActionCredentials(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-lifecycle-tracking.php');
        self::assertIsString($service);
        $start=strpos($service,'function mg_task_agent_lifecycle_for_model');
        self::assertNotFalse($start);
        $model=substr($service,$start);
        self::assertStringContainsString('array_slice($items,0,8)',$model);
        foreach(['action_item_id','instance_id','claim_code','redemption_code','source_reference','owner_user_id','recipient_id','sender_id'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$model);
        }
        foreach(['gift_state','sent_at','claimed_at','redeemed_at','resend_count','follow_up_count','capabilities','handoff_required'] as $marker){
            self::assertStringContainsString($marker,$model);
        }
    }

    public function testCanvasUsesSafeInternalHandoffOnly(): void
    {
        $root=dirname(__DIR__,2);
        $script=file_get_contents($root.'/assets/js/task-agent-lifecycle-runtime.js');
        $page=file_get_contents($root.'/agent.php');
        foreach([$script,$page] as $value)self::assertIsString($value);
        foreach(['gift_lifecycle_tracking','Open Action Center','Read only','internalUrl','Send or regift','Claim','Redeem','Follow up','Message'] as $marker){
            self::assertStringContainsString($marker,$script);
        }
        foreach(['fetch(','method:',"action: '",'action-center-send.php','action-center-claim.php','microgift-claim.php','claim_code','redemption_code'] as $forbidden){
            self::assertStringNotContainsString($forbidden,$script);
        }
        self::assertStringContainsString('/assets/js/task-agent-lifecycle-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-lifecycle-v1.css?v=1.0.0',$page);
    }

    public function testAgentApiExposesLifecycleOnlyThroughReadContext(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/agents/runtime.php');
        self::assertIsString($api);
        self::assertStringContainsString("'lifecycle_tracking'=>\$context['lifecycle_tracking']??[]",$api);
        self::assertStringContainsString("'lifecycle_schema_ready'=>\$context['lifecycle_schema_ready']??false",$api);
        foreach(["action==='send_gift'","action==='regift'","action==='claim_gift'","action==='redeem_gift'","action==='follow_up_gift'","action==='message_sender'"] as $forbidden){
            self::assertStringNotContainsString($forbidden,$api);
        }
    }
}
