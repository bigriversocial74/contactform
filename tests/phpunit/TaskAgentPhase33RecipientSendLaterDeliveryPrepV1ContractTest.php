<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase33RecipientSendLaterDeliveryPrepV1ContractTest extends TestCase
{
    public function testDeliveryAuthorityReusesCanonicalPrepareOnlyWorkflows(): void
    {
        $root=dirname(__DIR__,2);
        $service=file_get_contents($root.'/includes/task-agent-delivery-preparation.php');
        self::assertIsString($service);
        foreach([
            "require_once __DIR__ . '/personal-agent/workflows.php'",
            'user_gifting_schedules','user_recipient_data_requests','multi_agent_shortlist_items',
            'mg_personal_workflows_create_schedule','mg_personal_workflows_update_schedule',
            'mg_personal_workflows_create_data_request','execution_mode','prepare_only','approval_required',
            'multi_agent.delivery_schedule_created','multi_agent.delivery_schedule_updated','multi_agent.recipient_permission_requested',
        ] as $marker)self::assertStringContainsString($marker,$service);
        self::assertStringNotContainsString('mg_anthropic_messages',$service);
        self::assertStringNotContainsString('mg_ai_credit_consume',$service);
    }

    public function testPlanProductRecipientAndScheduleAreOwnerAndAgentScoped(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-delivery-preparation.php');
        self::assertIsString($service);
        foreach([
            's.agent_id=?', 'p.owner_user_id=?', "s.status='selected'",
            "p.status IN ('draft','planned','ready')", "cp.status='published'", "cpv.version_status='published'",
            "cpvl.availability_status='available'", 'ms.agent_id=?', "ms.status='selected'",
            "status<>'cancelled'", 'FOR UPDATE',
        ] as $marker)self::assertStringContainsString($marker,$service);
    }

    public function testReadinessExposesBooleansAndNeverAddressValues(): void
    {
        $service=file_get_contents(dirname(__DIR__,2).'/includes/task-agent-delivery-preparation.php');
        self::assertIsString($service);
        $projectionStart=strpos($service,'function mg_task_agent_delivery_record_projection');
        $modelStart=strpos($service,'function mg_task_agent_delivery_for_model');
        self::assertNotFalse($projectionStart);
        self::assertNotFalse($modelStart);
        $projection=substr($service,$projectionStart,$modelStart-$projectionStart);
        $model=substr($service,$modelStart);
        self::assertStringContainsString("'delivery_address_available'=>(bool)",$projection);
        self::assertStringContainsString("'gift_preferences_available'=>(bool)",$projection);
        self::assertStringContainsString("'address_value_exposed'=>false",$projection);
        foreach(['address_line_1','address_line_2','postal_code','phone','email'] as $privateField) {
            self::assertStringNotContainsString($privateField,$model);
        }
        self::assertStringContainsString('array_slice($items,0,8)',$model);
    }

    public function testDeterministicDeliveryRouteRunsBeforePlanDiscoveryAndAi(): void
    {
        $root=dirname(__DIR__,2);
        $runtime=file_get_contents($root.'/includes/multi-agent-runtime.php');
        $router=file_get_contents($root.'/includes/task-agent-delivery-router.php');
        foreach([$runtime,$router] as $value)self::assertIsString($value);
        $delivery=strpos($runtime,'$route = mg_task_agent_delivery_route');
        $plan=strpos($runtime,'?? mg_task_agent_plan_selection_route');
        $shortlist=strpos($runtime,'?? mg_task_agent_shortlist_route');
        $ai=strpos($runtime,'$synthesis = mg_task_agent_ai_synthesis');
        self::assertNotFalse($delivery);self::assertNotFalse($plan);self::assertNotFalse($shortlist);self::assertNotFalse($ai);
        self::assertLessThan($plan,$delivery);
        self::assertLessThan($shortlist,$plan);
        self::assertLessThan($ai,$shortlist);
        foreach(['show_delivery','request_information','manage_schedule','create_schedule','mg_task_agent_delivery_parse_datetime'] as $marker)self::assertStringContainsString($marker,$router);
        self::assertStringNotContainsString('mg_anthropic_messages',$router);
    }

    public function testRuntimeApiRequiresOwnershipCsrfAndExplicitActions(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/agents/runtime.php');
        self::assertIsString($api);
        foreach([
            'mg_agent_require_owned','mg_require_csrf_for_write',
            "action==='create_delivery_schedule'", "action==='update_delivery_schedule'", "action==='create_recipient_request'",
            'mg_task_agent_delivery_create_schedule','mg_task_agent_delivery_update_schedule','mg_task_agent_delivery_create_recipient_request',
            "'used_ai'=>false", "'response_source'=>'system_action'",
        ] as $marker)self::assertStringContainsString($marker,$api);
        foreach(['order-checkout-session.php','payments/webhook.php','action-center-send.php','microgift-claim.php'] as $forbidden)self::assertStringNotContainsString($forbidden,$api);
    }

    public function testCanvasRequiresExplicitClicksAndContainsNoCommerceExecution(): void
    {
        $root=dirname(__DIR__,2);
        $script=file_get_contents($root.'/assets/js/task-agent-delivery-runtime.js');
        $page=file_get_contents($root.'/agent.php');
        foreach([$script,$page] as $value)self::assertIsString($value);
        foreach([
            'data-delivery-schedule-create','data-delivery-schedule-update','data-delivery-schedule-action','data-recipient-request-create',
            "action: 'create_delivery_schedule'", "action: 'update_delivery_schedule'", "action: 'create_recipient_request'",
            'No gift was sent','No commerce was executed','Recipient-controlled sharing','Approval required',
        ] as $marker)self::assertStringContainsString($marker,$script);
        foreach(['cart-items.php','order-checkout-session.php','action-center-send.php','microgift-claim.php','address_line_1','postal_code'] as $forbidden)self::assertStringNotContainsString($forbidden,$script);
        self::assertStringContainsString('/assets/js/task-agent-delivery-runtime.js?v=1.0.0',$page);
        self::assertStringContainsString('/assets/css/task-agent-delivery-v1.css?v=1.0.0',$page);
    }

    public function testPhaseAddsNoNewMigrationAndDocumentsExistingPrerequisites(): void
    {
        $root=dirname(__DIR__,2);
        self::assertFileExists($root.'/database/20260714_personal_gifting_workflows_phase3.sql');
        self::assertFileExists($root.'/database/20260720_task_agent_phase3_shortlist_v1.sql');
        self::assertFileDoesNotExist($root.'/database/20260720_task_agent_phase3_3_delivery_preparation_v1.sql');
    }
}
