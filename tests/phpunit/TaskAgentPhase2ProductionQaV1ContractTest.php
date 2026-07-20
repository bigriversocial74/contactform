<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentPhase2ProductionQaV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, 'Unable to read ' . $path);
        return $source;
    }

    private function compact(string $source): string { return (string)preg_replace('/\s+/', '', $source); }

    public function testCompleteDataToReviewableActionChainIsPresent(): void
    {
        $context=$this->source('includes/task-agent-context.php');$router=$this->source('includes/task-agent-intent-router.php');$runtime=$this->source('includes/multi-agent-runtime.php');$api=$this->source('api/agents/runtime.php');$script=$this->source('assets/js/multi-agent-runtime.js');
        foreach(['mg_personal_agent_contacts','mg_personal_agent_upcoming_dates','mg_personal_agent_plans','mg_personal_agent_reminders'] as $marker) self::assertStringContainsString($marker,$context);
        foreach(['mg_task_agent_contextual_response','mg_task_agent_memory_preview_response','mg_task_agent_plan_payload','mg_task_agent_reminder_payload','/discover.php?'] as $marker) self::assertStringContainsString($marker,$router);
        foreach(['mg_task_agent_route','mg_multi_agent_runtime_store','mg_multi_agent_runtime_fallback'] as $marker) self::assertStringContainsString($marker,$runtime);
        foreach(['mg_agent_require_owned','mg_require_csrf_for_write','mg_personal_agent_create_plan','mg_personal_agent_create_reminder','mg_task_agent_memory_save','mg_personal_agent_update_reminder_status'] as $marker) self::assertStringContainsString($marker,$api);
        foreach(["action:'save_draft'","action:'create_reminder'","action:'update_reminder_status'","action:'save_memory'","action:'archive_memory'",'data-agent-open-link'] as $marker) self::assertStringContainsString($marker,$script);
    }

    public function testDeterministicRoutingPrecedesAndUsuallyAvoidsPaidAi(): void
    {
        $runtime=$this->source('includes/multi-agent-runtime.php');$router=$this->source('includes/task-agent-intent-router.php');$context=$this->source('includes/task-agent-context.php');$memory=$this->source('includes/task-agent-memory.php');$ai=$this->source('includes/task-agent-ai-synthesis.php');
        $routePosition=strpos($runtime,'mg_task_agent_shortlist_route');$synthesisPosition=strpos($runtime,'mg_task_agent_ai_synthesis');self::assertNotFalse($routePosition);self::assertNotFalse($synthesisPosition);self::assertLessThan($synthesisPosition,$routePosition);
        self::assertStringContainsString('mg_task_agent_memory_system_response',$router);self::assertStringContainsString('mg_task_agent_system_response',$router);self::assertStringContainsString('mg_task_agent_ai_reason',$router);
        $compact=$this->compact($runtime);self::assertStringContainsString('if(!$result&&$aiReason!==\'\')',$compact);self::assertStringContainsString('mg_multi_agent_runtime_messages($pdo,$userId,(int)$agent[\'id\'],(int)$thread[\'id\'],8)',$compact);
        self::assertStringContainsString('max(350, min(900',$ai);
        self::assertStringNotContainsString('mg_anthropic_messages',$context);self::assertStringNotContainsString('mg_ai_credit_consume',$context);self::assertStringNotContainsString('mg_anthropic_messages',$memory);self::assertStringNotContainsString('mg_ai_credit_consume',$memory);
    }

    public function testAiContextAndCardsCannotPerformAutonomousWrites(): void
    {
        $runtime=$this->source('includes/multi-agent-runtime.php');$router=$this->source('includes/task-agent-intent-router.php');$api=$this->source('api/agents/runtime.php');$memory=$this->source('includes/task-agent-memory.php');$ai=$this->source('includes/task-agent-ai-synthesis.php');
        foreach(['Never reveal secrets','All write actions are handled by deterministic server controls after user approval.','Do not purchase, publish, message, schedule, charge, claim, redeem, transfer'] as $marker) self::assertStringContainsString($marker,$ai);
        foreach(['mg_task_agent_model_context','mg_task_agent_sanitize_model_cards',"['none','seed_prompt']",'no_purchase_or_send','approval_required'] as $marker) self::assertStringContainsString($marker,$router);
        foreach(['password','api[_ -]?key','claim[_ -]?code','card[_ -]?number','private[_ -]?key','email address','phone number'] as $marker) self::assertStringContainsString($marker,$memory);
        self::assertStringContainsString("'used_ai'=>false",$this->compact($api));self::assertStringContainsString("'response_source'=>'system_action'",$this->compact($api));
        self::assertStringContainsString("'ai_reason' => \$aiReason",$runtime);self::assertStringContainsString("'ai_tokens_total' => \$tokens['total']",$runtime);
    }

    public function testOnePersistentCanvasExposesEveryReviewControl(): void
    {
        $workspace=$this->source('includes/personal-agent/multi-agent-workspace.php');$script=$this->source('assets/js/multi-agent-runtime.js');$page=$this->source('agent.php');
        foreach(['data-agent-runtime-messages','data-agent-runtime-composer','data-task-agent-context','data-agent-memory-list','data-agent-manage-open'] as $marker) self::assertStringContainsString($marker,$workspace);
        self::assertStringNotContainsString('data-agent-thread-list',$workspace);self::assertStringNotContainsString('data-agent-new-thread',$workspace);
        foreach(['data-save-agent-draft','data-save-agent-reminder','data-reminder-status','data-save-agent-memory','data-archive-agent-memory','data-agent-open-link'] as $marker) self::assertStringContainsString($marker,$script);
        self::assertStringContainsString('Answered from Microgifter data. No AI credits used.',$script);self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.7.0',$page);self::assertStringContainsString('/assets/css/task-agent-context-v1.css?v=1.0.0',$page);
    }

    public function testEveryPhaseContractRemainsPartOfTheProductionGate(): void
    {
        foreach(['tests/phpunit/TaskAgentContextUpcomingEventsV1ContractTest.php','tests/phpunit/TaskAgentGiftPlanningDraftsV1ContractTest.php','tests/phpunit/TaskAgentRemindersV1ContractTest.php','tests/phpunit/TaskAgentMemoryPermissionSafeRetrievalV1ContractTest.php','tests/phpunit/TaskAgentContextualChatActionCardsV1ContractTest.php','tests/phpunit/MultiAgentRuntimeMemoryV1ContractTest.php','tests/phpunit/UnifiedAgentChatCanvasV2ContractTest.php'] as $path) self::assertFileExists($this->root.'/'.$path);
    }
}
