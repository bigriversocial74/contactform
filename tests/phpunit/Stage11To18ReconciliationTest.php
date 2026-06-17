<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage11To18ReconciliationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testStage14FeedUsesDescendingCursor(): void
    {
        $source=$this->read('api/social/_social.php');
        self::assertStringContainsString('fp.id<?',$source);
        self::assertStringNotContainsString('fp.id>?',$source);
    }

    public function testStage16ReconcilesRunsAfterFailedActions(): void
    {
        $worker=$this->read('scripts/process_agent_actions.php');
        $service=$this->read('api/agents/_workflow.php');
        self::assertStringContainsString('mg_agent_process_next_action(',$worker);
        self::assertStringContainsString('mg_agent_reconcile_run(',$service);
        self::assertStringContainsString('action_execution_failed',$service);
        self::assertStringContainsString('partially_completed',$service);
        self::assertStringContainsString("'failed'",$service);
    }

    public function testStage17PlansActionsThroughStage16Authority(): void
    {
        $worker=$this->read('scripts/process_swarm_tasks.php');
        $service=$this->read('api/agents/_swarm_workflow.php');
        self::assertStringContainsString('mg_swarm_route_next_task(',$worker);
        self::assertStringContainsString('mg_agent_create_run(',$service);
        self::assertStringContainsString('mg_agent_plan_run(',$service);
        self::assertStringContainsString('Executable swarm tasks require Stage 16 actions.',$service);
    }

    public function testStage17ReviewTasksHaveCompletionEndpoint(): void
    {
        $endpoint=$this->read('api/agents/swarm-review.php');
        $service=$this->read('api/agents/_swarm_workflow.php');
        self::assertStringContainsString("mg_require_permission('agent.swarms.resolve')",$endpoint);
        self::assertStringContainsString('mg_swarm_review_task(',$endpoint);
        self::assertStringContainsString("['approve','reject']",$service);
        self::assertStringContainsString('review_pending',$service);
        self::assertStringContainsString('FOR UPDATE',$service);
        self::assertStringContainsString('mg_swarm_reconcile_run(',$service);
    }
}
