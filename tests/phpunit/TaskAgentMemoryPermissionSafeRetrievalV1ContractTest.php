<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentMemoryPermissionSafeRetrievalV1ContractTest extends TestCase
{
    public function testMemoryAuthoritySanitizesAndScopesEveryOperation(): void
    {
        $root = dirname(__DIR__, 2);
        $memory = file_get_contents($root . '/includes/task-agent-memory.php');
        $runtime = file_get_contents($root . '/includes/multi-agent-runtime.php');
        $router = file_get_contents($root . '/includes/task-agent-intent-router.php');
        $api = file_get_contents($root . '/api/agents/runtime.php');
        foreach ([$memory, $runtime, $router, $api] as $value) self::assertIsString($value);

        foreach (['mg_task_agent_memory_sensitive', 'mg_task_agent_memory_sanitize', 'mg_task_agent_memory_list', 'mg_task_agent_memory_save', 'mg_task_agent_memory_archive', 'mg_task_agent_memory_search', 'mg_task_agent_memory_for_model'] as $marker) {
            self::assertStringContainsString($marker, $memory);
        }
        self::assertStringContainsString('owner_user_id=? AND agent_id=?', $memory);
        self::assertStringContainsString("status='archived'", $memory);
        self::assertStringContainsString('Sensitive credentials and private contact details cannot be stored in Agent Memory.', $memory);
        self::assertStringContainsString('mg_task_agent_memory_save', $api);
        self::assertStringContainsString("\$action==='archive_memory'", $api);
    }

    public function testDeterministicRetrievalRunsBeforeAnthropicAndUsesSafeContext(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/includes/multi-agent-runtime.php');
        $router = file_get_contents($root . '/includes/task-agent-intent-router.php');
        $memory = file_get_contents($root . '/includes/task-agent-memory.php');
        foreach ([$runtime, $router, $memory] as $value) self::assertIsString($value);

        self::assertStringContainsString('mg_task_agent_memory_system_response', $router);
        $routePosition = strpos($runtime, 'mg_task_agent_route');
        $providerPosition = strpos($runtime, 'mg_anthropic_messages');
        self::assertNotFalse($routePosition);
        self::assertNotFalse($providerPosition);
        self::assertLessThan($providerPosition, $routePosition);
        self::assertStringContainsString('mg_task_agent_model_context', $router);
        self::assertStringContainsString('Permission-safe focused context JSON', $runtime);
        self::assertStringContainsString("'used_ai'=>false", preg_replace('/\s+/', '', $memory));
        self::assertStringNotContainsString('mg_anthropic_messages', $memory);
        self::assertStringNotContainsString('mg_ai_credit_consume', $memory);
    }

    public function testUserCanArchiveMemoryFromTheAgentCanvas(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/assets/js/multi-agent-runtime.js');
        $page = file_get_contents($root . '/agent.php');
        self::assertIsString($script);
        self::assertIsString($page);
        self::assertStringContainsString('data-archive-agent-memory', $script);
        self::assertStringContainsString("action:'archive_memory'", $script);
        self::assertStringContainsString('No AI credits used.', $script);
        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.6.0', $page);
    }
}
