<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionAgentExecutionBehaviorTest extends TestCase
{
    public function testAgentApprovalExecutionAndFailureReconciliationAgainstRealDatabase(): void
    {
        if((string)getenv('MG_DB_HOST')===''){
            self::markTestSkipped('Database-backed agent execution validation requires MG_DB_HOST.');
        }

        $root=dirname(__DIR__,2);
        $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/validate_agent_execution_behavior.php').' 2>&1';
        $output=[];$exitCode=0;
        exec($command,$output,$exitCode);
        $raw=implode("\n",$output);
        self::assertSame(0,$exitCode,$raw);

        $result=json_decode($raw,true);
        self::assertIsArray($result,$raw);
        self::assertSame('agent_approval_execution_reconciliation_behavior',$result['suite']??null);
        foreach([
            'approval_required','approval_replay','conflicting_approval_rejected','execution_completed',
            'execution_replay_safe','alert_created_once','forced_failure_rolled_back',
            'failure_reconciled','audit_consistent','fixtures_clean',
        ] as $key){
            self::assertTrue((bool)($result[$key]??false),$key.' failed: '.$raw);
        }
    }

    public function testEndpointsAndWorkerUseCanonicalWorkflowAuthority(): void
    {
        $approval=file_get_contents(dirname(__DIR__,2).'/api/agents/approvals.php');
        $worker=file_get_contents(dirname(__DIR__,2).'/scripts/process_agent_actions.php');
        $service=file_get_contents(dirname(__DIR__,2).'/api/agents/_workflow.php');
        self::assertIsString($approval);self::assertIsString($worker);self::assertIsString($service);
        self::assertStringContainsString("require_once __DIR__ . '/_workflow.php'",$approval);
        self::assertStringContainsString('mg_agent_decide_approval(',$approval);
        self::assertStringContainsString("/api/agents/_workflow.php",$worker);
        self::assertStringContainsString('mg_agent_process_next_action(',$worker);
        self::assertStringContainsString('SAVEPOINT agent_action_execution',$service);
        self::assertStringContainsString('ROLLBACK TO SAVEPOINT agent_action_execution',$service);
        self::assertStringContainsString('Approval decision conflicts with the recorded decision.',$service);
    }
}
