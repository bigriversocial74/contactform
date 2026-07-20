<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentContextualChatActionCardsV1ContractTest extends TestCase
{
    public function testDeterministicRouterBuildsFocusedContextAndReviewableCards(): void
    {
        $root = dirname(__DIR__, 2);
        $router = file_get_contents($root . '/includes/task-agent-intent-router.php');
        self::assertIsString($router);
        foreach ([
            'function mg_task_agent_route','function mg_task_agent_contextual_response','function mg_task_agent_memory_preview_response','function mg_task_agent_model_context',
            'mg_task_agent_plan_payload','mg_task_agent_reminder_payload',"'action' => 'save_draft'","'action' => 'save_reminder'","'action' => 'save_memory'",
            "'action' => 'open_link'","'action' => 'seed_prompt'","'/discover.php?'","'type' => 'warning'","'approval_required' => true","'no_purchase_or_send' => true",
        ] as $marker) self::assertStringContainsString($marker, $router);
        self::assertStringNotContainsString('mg_anthropic_messages', $router);
        self::assertStringNotContainsString('mg_ai_credit_consume', $router);
    }

    public function testRuntimeCallsAiOnlyForExplicitSynthesisAndLogsTheReason(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/includes/multi-agent-runtime.php');
        $ai = file_get_contents($root . '/includes/task-agent-ai-synthesis.php');
        $shortlistRouter = file_get_contents($root . '/includes/task-agent-shortlist-router.php');
        foreach ([$runtime,$ai,$shortlistRouter] as $value) self::assertIsString($value);
        self::assertStringContainsString('task-agent-intent-router.php', $runtime);
        self::assertStringContainsString('task-agent-shortlist-router.php', $runtime);
        self::assertStringContainsString('mg_task_agent_shortlist_route', $runtime);
        self::assertStringContainsString('mg_task_agent_route', $runtime);
        self::assertStringContainsString('mg_task_agent_ai_synthesis', $runtime);
        self::assertStringContainsString('mg_task_agent_model_context', $ai);
        self::assertStringContainsString('mg_task_agent_sanitize_model_cards', $ai);
        self::assertStringContainsString('mg_ai_credit_preflight', $ai);
        self::assertStringContainsString('mg_ai_credit_consume', $ai);
        self::assertStringContainsString('max(350, min(900', $ai);
        self::assertStringContainsString('gift_comparison', file_get_contents($root . '/includes/task-agent-intent-router.php'));
    }

    public function testCanvasSupportsSafeWriteCardsAndInternalDiscoveryLinks(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/assets/js/multi-agent-runtime.js');
        $page = file_get_contents($root . '/agent.php');
        self::assertIsString($script);
        self::assertIsString($page);
        foreach (['data-save-agent-memory','data-agent-open-link',"action:'save_memory'","action:'save_draft'","action:'create_reminder'",'internalUrl','response_source === \'anthropic\'','ai_reason','ai_tokens_used'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.7.0', $page);
    }

    public function testCanonicalApiRemainsApprovalFirstAndOwnerScoped(): void
    {
        $api = file_get_contents(dirname(__DIR__, 2) . '/api/agents/runtime.php');
        self::assertIsString($api);
        foreach (['mg_agent_require_owned','mg_require_csrf_for_write','mg_personal_agent_create_plan','mg_personal_agent_create_reminder','mg_task_agent_memory_save',"'used_ai'=>false","'response_source'=>'system_action'"] as $marker) {
            self::assertStringContainsString($marker, $api);
        }
    }
}
