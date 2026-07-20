<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase36ProductionQaV1ContractTest extends TestCase
{
    public function testReleaseManifestAndMigrationOrder(): void
    {
        $root=dirname(__DIR__,2);
        $release=require $root.'/config/task_agent_phase3_release.php';
        $canonical=require $root.'/config/migrations.php';
        $expected=[
            '20260714_user_contact_lists_phase1.sql',
            'stage_19_ai_provider_models.sql',
            '20260714_personal_gifting_agent_phase2.sql',
            '20260714_personal_gifting_workflows_phase3.sql',
            '20260719_multi_agent_runtime_memory_v1.sql',
            '20260720_task_agent_phase3_shortlist_v1.sql',
        ];
        self::assertSame($expected,$release['required_migrations']);
        $last=-1;
        foreach($expected as $file){
            self::assertFileExists($root.'/database/'.$file);
            $index=array_search($file,$canonical['ordered_files'],true);
            self::assertNotFalse($index);
            self::assertGreaterThan($last,$index);
            $last=$index;
        }
    }

    public function testCompleteDeterministicRouteChain(): void
    {
        $runtime=file_get_contents(dirname(__DIR__,2).'/includes/multi-agent-runtime.php');
        self::assertIsString($runtime);
        $markers=[
            '$route = mg_task_agent_lifecycle_route',
            '?? mg_task_agent_order_tracking_route',
            '?? mg_task_agent_delivery_route',
            '?? mg_task_agent_plan_selection_route',
            '?? mg_task_agent_shortlist_route',
            '?? mg_task_agent_route',
            '$synthesis = mg_task_agent_ai_synthesis',
        ];
        $last=-1;
        foreach($markers as $marker){
            $position=strpos($runtime,$marker);
            self::assertNotFalse($position,$marker);
            self::assertGreaterThan($last,$position,$marker);
            $last=$position;
        }
    }

    public function testAllPhaseAuthoritiesAssetsAndContractsExist(): void
    {
        $root=dirname(__DIR__,2);
        foreach([
            'includes/task-agent-shortlist.php',
            'includes/task-agent-plan-selection.php',
            'includes/task-agent-delivery-preparation.php',
            'includes/task-agent-order-tracking.php',
            'includes/task-agent-lifecycle-tracking.php',
            'assets/js/task-agent-shortlist-runtime.js',
            'assets/js/task-agent-delivery-runtime.js',
            'assets/js/task-agent-order-tracking-runtime.js',
            'assets/js/task-agent-lifecycle-runtime.js',
            'tests/phpunit/TaskAgentPhase31DiscoveryShortlistV1ContractTest.php',
            'tests/phpunit/TaskAgentPhase32PlanCartHandoffV1ContractTest.php',
            'tests/phpunit/TaskAgentPhase33RecipientSendLaterDeliveryPrepV1ContractTest.php',
            'tests/phpunit/TaskAgentPhase34PurchaseConfirmationPppmTrackingV1ContractTest.php',
            'tests/phpunit/TaskAgentPhase35LifecycleHandoffV1ContractTest.php',
        ] as $file)self::assertFileExists($root.'/'.$file);
    }

    public function testReleaseAssetsAreLoaded(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/agent.php');
        $release=require $root.'/config/task_agent_phase3_release.php';
        self::assertIsString($page);
        foreach($release['runtime_assets'] as $asset)self::assertStringContainsString($asset,$page);
    }

    public function testPhaseModelProjectionsRemainCompact(): void
    {
        $root=dirname(__DIR__,2);
        foreach([
            'includes/task-agent-shortlist.php'=>'function mg_task_agent_shortlist_for_model',
            'includes/task-agent-plan-selection.php'=>'function mg_task_agent_plan_selection_for_model',
            'includes/task-agent-delivery-preparation.php'=>'function mg_task_agent_delivery_for_model',
            'includes/task-agent-order-tracking.php'=>'function mg_task_agent_order_tracking_for_model',
            'includes/task-agent-lifecycle-tracking.php'=>'function mg_task_agent_lifecycle_for_model',
        ] as $file=>$function){
            $source=file_get_contents($root.'/'.$file);
            self::assertIsString($source);
            $start=strpos($source,$function);
            self::assertNotFalse($start);
            self::assertStringContainsString('array_slice($items,0,8)',substr($source,$start));
        }
    }
}
