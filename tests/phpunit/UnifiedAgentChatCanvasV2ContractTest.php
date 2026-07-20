<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UnifiedAgentChatCanvasV2ContractTest extends TestCase
{
    public function testOneAddAgentActionCreatesChatOrTaskAgents(): void
    {
        $root = dirname(__DIR__, 2);
        $sidebar = file_get_contents($root . '/includes/personal-agent-sidebar.php');
        $templates = file_get_contents($root . '/includes/multi-agent-workspace-data.php');
        self::assertIsString($sidebar);
        self::assertIsString($templates);
        self::assertSame(1, substr_count($sidebar, 'data-open-agent-selector'));
        self::assertStringContainsString('data-personal-agent-new-chat', $sidebar);
        self::assertStringContainsString('Legacy non-rendered compatibility contract', $sidebar);
        self::assertStringContainsString("'chat_agent' => [", $templates);
        self::assertStringContainsString("'name' => 'Chat Agent'", $templates);
        self::assertStringContainsString("'status' => 'active'", $templates);
    }

    public function testDefaultAgentIsImmutableAndAddedAgentsAreManageable(): void
    {
        $sidebar = file_get_contents(dirname(__DIR__, 2) . '/includes/personal-agent-sidebar.php');
        self::assertIsString($sidebar);
        self::assertStringContainsString('data-sidebar-agent-row="default"', $sidebar);
        self::assertStringContainsString('Default chat agent', $sidebar);
        self::assertStringContainsString('data-sidebar-agent-manage', $sidebar);
        self::assertStringNotContainsString('data-sidebar-agent-manage="default"', $sidebar);
        self::assertStringContainsString('mg-agent-nav-icon', $sidebar);
    }

    public function testTaskAgentChatUsesOneCanvasAndAlwaysVisibleComposer(): void
    {
        $root = dirname(__DIR__, 2);
        $workspace = file_get_contents($root . '/includes/personal-agent/multi-agent-workspace.php');
        $layout = file_get_contents($root . '/assets/css/task-agent-single-chat-v1.css');
        self::assertIsString($workspace);
        self::assertIsString($layout);
        self::assertStringContainsString('data-agent-runtime-messages', $workspace);
        self::assertStringContainsString('data-agent-runtime-composer', $workspace);
        self::assertStringNotContainsString('data-agent-thread-list', $workspace);
        self::assertStringNotContainsString('data-agent-new-thread', $workspace);
        self::assertStringContainsString('.mg-agent-runtime-layout{height:100%', $layout);
        self::assertStringContainsString('.mg-agent-runtime-messages{flex:1 1 auto;min-height:0!important;overflow-y:auto!important}', $layout);
        self::assertStringContainsString('.mg-agent-runtime-composer{position:relative!important', $layout);
        self::assertStringContainsString('display:grid!important', $layout);
    }
}