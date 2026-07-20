<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MultiAgentRuntimeMemoryV1ContractTest extends TestCase
{
    public function testAgentOwnedRuntimeSchemaIsIsolatedFromDefaultPersonalAgentTables(): void
    {
        $root = dirname(__DIR__, 2);
        $sql = file_get_contents($root . '/database/20260719_multi_agent_runtime_memory_v1.sql');
        self::assertIsString($sql);
        foreach (['multi_agent_threads','multi_agent_messages','multi_agent_memory','multi_agent_onboarding','multi_agent_drafts'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_multi_agent_memory_agent_key (agent_id,memory_key)', $sql);
        self::assertStringContainsString("'agent.specialized.use'", $sql);
    }

    public function testRuntimeUsesOwnedAgentsSafeMemoryIntentRoutingAndAiCredits(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/includes/multi-agent-runtime.php');
        $memory = file_get_contents($root . '/includes/task-agent-memory.php');
        $router = file_get_contents($root . '/includes/task-agent-intent-router.php');
        $api = file_get_contents($root . '/api/agents/runtime.php');
        foreach ([$service,$memory,$router,$api] as $value) self::assertIsString($value);

        foreach (['mg_agent_require_owned','mg_require_csrf_for_write','mg_multi_agent_runtime_chat'] as $marker) {
            self::assertStringContainsString($marker, $api);
        }
        foreach (['mg_task_agent_memory_save','mg_task_agent_memory_list','mg_task_agent_memory_archive'] as $marker) {
            self::assertStringContainsString($marker, $api);
            self::assertStringContainsString('function ' . $marker, $memory);
        }
        self::assertStringContainsString('owner_user_id', $memory);
        self::assertStringContainsString('agent_id', $memory);
        self::assertStringContainsString('mg_task_agent_memory_for_model', $service);
        self::assertStringContainsString('mg_task_agent_memory_system_response', $router);
        self::assertStringContainsString('mg_task_agent_route', $service);
        self::assertStringContainsString('$aiReason !== \'\'', $service);
        self::assertStringContainsString('mg_ai_credit_preflight', $service);
        self::assertStringContainsString('mg_ai_credit_consume', $service);
        self::assertStringContainsString('\'ai_reason\'=>$aiReason', $service);
        self::assertStringContainsString('user approval', $service);
    }

    public function testSelectedAgentUsesOnePersistentChatCanvasWithTabbedManagement(): void
    {
        $root = dirname(__DIR__, 2);
        $workspace = file_get_contents($root . '/includes/personal-agent/multi-agent-workspace.php');
        $script = file_get_contents($root . '/assets/js/multi-agent-runtime.js');
        $layout = file_get_contents($root . '/assets/css/task-agent-single-chat-v1.css');
        $page = file_get_contents($root . '/agent.php');
        foreach ([$workspace,$script,$layout,$page] as $value) self::assertIsString($value);

        foreach (['data-agent-runtime-messages','data-agent-runtime-composer','data-agent-onboarding-form','data-agent-memory-list','data-agent-manage-open','data-agent-manage-tab="manage"','data-agent-manage-tab="settings"','data-agent-action="duplicate"'] as $marker) {
            self::assertStringContainsString($marker, $workspace);
        }
        self::assertStringNotContainsString('data-agent-thread-list', $workspace);
        self::assertStringNotContainsString('data-agent-new-thread', $workspace);
        foreach (['/api/agents/runtime.php',"'chat'","'onboarding'","'save_draft'","'save_memory'","'archive_memory'",'stopImmediatePropagation'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
        self::assertStringContainsString('.mg-agent-runtime-composer', $layout);
        self::assertStringContainsString('position:relative!important', $layout);
        self::assertStringContainsString('display:grid!important', $layout);
        self::assertStringContainsString('safe-area-inset-bottom', $layout);
        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.6.0', $page);
        self::assertStringContainsString('/assets/css/task-agent-single-chat-v1.css?v=1.0.0', $page);
    }
}
