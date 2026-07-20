<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaskAgentContextUpcomingEventsV1ContractTest extends TestCase
{
    public function testDatabaseFirstContextContract(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root . '/includes/task-agent-context.php');
        $runtime = file_get_contents($root . '/includes/multi-agent-runtime.php');
        $api = file_get_contents($root . '/api/agents/runtime.php');
        $workspace = file_get_contents($root . '/includes/personal-agent/multi-agent-workspace.php');
        $script = file_get_contents($root . '/assets/js/multi-agent-runtime.js');
        $page = file_get_contents($root . '/agent.php');

        self::assertIsString($service);
        self::assertIsString($runtime);
        self::assertIsString($api);
        self::assertIsString($workspace);
        self::assertIsString($script);
        self::assertIsString($page);

        foreach (['mg_personal_agent_contacts', 'mg_personal_agent_upcoming_dates', 'mg_personal_agent_plans', 'mg_personal_agent_reminders'] as $function) {
            self::assertStringContainsString($function, $service);
        }
        self::assertStringContainsString("'source' => 'system'", $service);
        self::assertStringContainsString("'used_ai' => false", $service);
        self::assertStringNotContainsString('mg_anthropic_messages', $service);
        self::assertStringNotContainsString('mg_ai_credit_consume', $service);

        $systemPosition = strpos($runtime, 'mg_task_agent_system_response');
        $providerPosition = strpos($runtime, 'mg_anthropic_messages');
        self::assertNotFalse($systemPosition);
        self::assertNotFalse($providerPosition);
        self::assertLessThan($providerPosition, $systemPosition);
        self::assertStringContainsString("responseSource='system_query'", $runtime);

        self::assertStringContainsString("'context_snapshot'", $api);
        self::assertStringContainsString("'used_ai_for_context'=>false", $api);
        self::assertStringContainsString('data-task-agent-context', $workspace);
        self::assertStringContainsString('No AI credits used', $workspace);
        self::assertStringContainsString('renderContext(data.context_snapshot || null)', $script);
        self::assertStringContainsString('Answered from Microgifter data. No AI credits used.', $script);
        self::assertStringContainsString('/assets/css/task-agent-context-v1.css?v=1.0.0', $page);
        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.3.0', $page);
    }
}
