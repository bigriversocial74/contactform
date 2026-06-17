<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionSwarmBehaviorTest extends TestCase
{
    public function testSwarmRoutingReviewAndCompletionAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed swarm validation requires MG_DB_HOST.');
        }

        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_swarm_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);

        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('swarm_routing_review_completion_behavior',$result['suite']??null);
        foreach([
            'run_created','first_task_routed','stage16_delegated','review_pending','review_replay',
            'conflicting_review_rejected','dependency_released','second_task_completed','swarm_completed',
            'routing_replay_safe','audit_consistent','budget_consistent','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testWorkerAndReviewEndpointUseCanonicalSwarmWorkflowAuthority(): void
    {
        $worker=file_get_contents(dirname(__DIR__,2).'/scripts/process_swarm_tasks.php');
        $endpoint=file_get_contents(dirname(__DIR__,2).'/api/agents/swarm-review.php');
        $service=file_get_contents(dirname(__DIR__,2).'/api/agents/_swarm_workflow.php');
        self::assertIsString($worker);self::assertIsString($endpoint);self::assertIsString($service);
        self::assertStringContainsString('/api/agents/_swarm_workflow.php',$worker);
        self::assertStringContainsString('mg_swarm_route_next_task(',$worker);
        self::assertStringContainsString('mg_swarm_sync_workflow(',$worker);
        self::assertStringContainsString("require_once __DIR__ . '/_swarm_workflow.php'",$endpoint);
        self::assertStringContainsString('mg_swarm_review_task(',$endpoint);
        self::assertStringContainsString('mg_swarm_release_dependencies(',$service);
        self::assertStringContainsString('mg_swarm_reconcile_run(',$service);
        self::assertStringContainsString('Review decision conflicts with the recorded task outcome.',$service);
    }
}
