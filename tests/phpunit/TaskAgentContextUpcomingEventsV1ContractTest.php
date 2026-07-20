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
        $router = file_get_contents($root . '/includes/task-agent-intent-router.php');
        $api = file_get_contents($root . '/api/agents/runtime.php');
        $workspace = file_get_contents($root . '/includes/personal-agent/multi-agent-workspace.php');
        $script = file_get_contents($root . '/assets/js/multi-agent-runtime.js');
        $page = file_get_contents($root . '/agent.php');

        foreach ([$service, $runtime, $router, $api, $workspace, $script, $page] as $value) {
            self::assertIsString($value);
        }

        foreach (['mg_personal_agent_contacts', 'mg_personal_agent_upcoming_dates', 'mg_personal_agent_plans', 'mg_personal_agent_reminders'] as $function) {
            self::assertStringContainsString($function, $service);
        }
        self::assertStringContainsString("'source'=>'system'", preg_replace('/\s+/', '', $service));
        self::assertStringContainsString("'used_ai'=>false", preg_replace('/\s+/', '', $service));
        self::assertStringNotContainsString('mg_anthropic_messages', $service);
        self::assertStringNotContainsString('mg_ai_credit_consume', $service);

        $routePosition = strpos($runtime, 'mg_task_agent_route');
        $providerPosition = strpos($runtime, 'mg_anthropic_messages');
        self::assertNotFalse($routePosition);
        self::assertNotFalse($providerPosition);
        self::assertLessThan($providerPosition, $routePosition);
        self::assertStringContainsString('mg_task_agent_system_response', $router);
        self::assertStringContainsString("'response_source'=>'system_query'", preg_replace('/\s+/', '', $runtime));

        self::assertStringContainsString("'context_snapshot'", $api);
        self::assertStringContainsString("'used_ai_for_context'=>false", preg_replace('/\s+/', '', $api));
        self::assertStringContainsString('data-task-agent-context', $workspace);
        self::assertStringContainsString('No AI credits used', $workspace);
        self::assertStringContainsString('renderContext(data.context_snapshot || null)', $script);
        self::assertStringContainsString('Answered from Microgifter data. No AI credits used.', $script);
        self::assertStringContainsString('/assets/css/task-agent-context-v1.css?v=1.0.0', $page);
        self::assertStringContainsString('/assets/js/multi-agent-runtime.js?v=1.6.0', $page);
    }
}
