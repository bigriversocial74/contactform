<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentContextualChatActionCardsV1ContractTest extends TestCase
{
    public function testDeterministicRouterOwnsContextAndReviewableActions(): void
    {
        $root = dirname(__DIR__, 2);
        $router = file_get_contents($root . '/includes/task-agent-intent-router.php');
        self::assertIsString($router);
        foreach (['function mg_task_agent_route','function mg_task_agent_contextual_response','function mg_task_agent_memory_preview_response','function mg_task_agent_model_context','mg_task_agent_plan_payload','mg_task_agent_reminder_payload','save_draft','save_reminder','save_memory','open_link','seed_prompt','approval_required','no_purchase_or_send'] as $marker) {
            self::assertStringContainsString($marker, $router);
        }
        self::assertStringNotContainsString('mg_anthropic_messages', $router);
        self::assertStringNotContainsString('mg_ai_credit_consume', $router);
    }

    public function testDeterministicRuntimePrecedesExplicitAiSynthesis(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/includes/multi-agent-runtime.php');
        $ai = file_get_contents($root . '/includes/task-agent-ai-synthesis.php');
        $router = file_get_contents($root . '/includes/task-agent-intent-router.php');
        foreach ([$runtime, $ai, $router] as $value) self::assertIsString($value);

        $planRoute = strpos($runtime, 'mg_task_agent_plan_selection_route');
        $shortlistRoute = strpos($runtime, 'mg_task_agent_shortlist_route');
        $generalRoute = strpos($runtime, 'mg_task_agent_route');
        $synthesis = strpos($runtime, 'mg_task_agent_ai_synthesis');
        foreach ([$planRoute,$shortlistRoute,$generalRoute,$synthesis] as $position) self::assertNotFalse($position);
        self::assertLessThan($shortlistRoute, $planRoute);
        self::assertLessThan($generalRoute, $shortlistRoute);
        self::assertLessThan($synthesis, $generalRoute);

        foreach (['mg_multi_agent_runtime_model_context','mg_task_agent_sanitize_model_cards','mg_ai_credit_preflight','mg_ai_credit_consume','Permission-safe focused context JSON'] as $marker) {
            self::assertStringContainsString($marker, $ai);
        }
        foreach (['personal_message_synthesis','gift_comparison','recommendation_synthesis'] as $reason) self::assertStringContainsString($reason, $router);
    }

    public function testCanvasKeepsWritesUserControlledAndLinksInternal(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/assets/js/multi-agent-runtime.js');
        $page = file_get_contents($root . '/agent.php');
        self::assertIsString($script);
        self::assertIsString($page);
        foreach (['data-save-agent-memory','data-save-agent-draft','data-save-agent-reminder','data-agent-open-link','internalUrl','response_source','ai_reason','ai_tokens_used','stopImmediatePropagation'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.7.0', $page);
        self::assertStringContainsString('/assets/js/task-agent-shortlist-runtime.js?v=1.1.0', $page);
    }

    public function testCanonicalApiRemainsOwnerScopedCsrfProtectedAndApprovalFirst(): void
    {
        $api = file_get_contents(dirname(__DIR__, 2) . '/api/agents/runtime.php');
        self::assertIsString($api);
        foreach (['mg_agent_require_owned','mg_require_csrf_for_write','mg_personal_agent_create_plan','mg_personal_agent_create_reminder','mg_task_agent_memory_save','used_ai','response_source','system_action'] as $marker) {
            self::assertStringContainsString($marker, $api);
        }
    }
}
