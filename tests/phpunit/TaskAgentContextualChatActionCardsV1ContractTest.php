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
            'function mg_task_agent_route',
            'function mg_task_agent_contextual_response',
            'function mg_task_agent_memory_preview_response',
            'function mg_task_agent_model_context',
            'mg_task_agent_plan_payload',
            'mg_task_agent_reminder_payload',
            "'action' => 'save_draft'",
            "'action' => 'save_reminder'",
            "'action' => 'save_memory'",
            "'action' => 'open_link'",
            "'action' => 'seed_prompt'",
            "'/discover.php?'",
            "'type' => 'warning'",
            "'approval_required' => true",
            "'no_purchase_or_send' => true",
        ] as $marker) {
            self::assertStringContainsString($marker, $router);
        }

        self::assertStringNotContainsString('mg_anthropic_messages', $router);
        self::assertStringNotContainsString('mg_ai_credit_consume', $router);
    }

    public function testRuntimeCallsAiOnlyForExplicitSynthesisAndLogsTheReason(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/includes/multi-agent-runtime.php');
        $router = file_get_contents($root . '/includes/task-agent-intent-router.php');
        self::assertIsString($runtime);
        self::assertIsString($router);

        self::assertStringContainsString("require_once __DIR__.'/task-agent-intent-router.php'", $runtime);
        self::assertStringContainsString('mg_task_agent_route($message,$context,$template)', $runtime);
        self::assertStringContainsString('if (!$result && $aiReason !== \'\')', $runtime);
        self::assertStringContainsString('mg_task_agent_model_context($message,$context)', $runtime);
        self::assertStringContainsString('mg_task_agent_sanitize_model_cards', $runtime);
        self::assertStringContainsString('\'ai_reason\'=>$aiReason', $runtime);
        self::assertStringContainsString('\'used_ai\'=>$modelKey !== \'\'', $runtime);
        self::assertStringContainsString('\'ai_tokens_total\'=>$tokens[\'total\']', $runtime);
        self::assertStringContainsString('max(350,min(900', $runtime);
        self::assertStringContainsString('array_slice($context[\'memory_for_model\'] ?? [], 0, 12)', $router);
    }

    public function testCanvasSupportsSafeWriteCardsAndInternalDiscoveryLinks(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/assets/js/multi-agent-runtime.js');
        $page = file_get_contents($root . '/agent.php');
        self::assertIsString($script);
        self::assertIsString($page);

        foreach ([
            'data-save-agent-memory',
            'data-agent-open-link',
            "action:'save_memory'",
            "action:'save_draft'",
            "action:'create_reminder'",
            'internalUrl',
            "data.response_source === 'anthropic'",
            'data.ai_reason',
            'data.ai_tokens_used',
        ] as $marker) {
            self::assertStringContainsString($marker, $script);
        }

        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.6.0', $page);
    }

    public function testCanonicalApiRemainsApprovalFirstAndOwnerScoped(): void
    {
        $root = dirname(__DIR__, 2);
        $api = file_get_contents($root . '/api/agents/runtime.php');
        self::assertIsString($api);
        foreach ([
            'mg_agent_require_owned',
            'mg_require_csrf_for_write',
            'mg_personal_agent_create_plan',
            'mg_personal_agent_create_reminder',
            'mg_task_agent_memory_save',
            "'used_ai'=>false",
            "'response_source'=>'system_action'",
        ] as $marker) {
            self::assertStringContainsString($marker, $api);
        }
    }
}
