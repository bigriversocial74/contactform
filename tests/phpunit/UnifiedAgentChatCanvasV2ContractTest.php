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
        self::assertStringNotContainsString('data-personal-agent-new-chat', $sidebar);
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

    public function testAgentChatUsesOneColumnAndStickyComposer(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/assets/css/multi-agent-runtime.css');
        self::assertIsString($css);
        self::assertStringContainsString('.mg-agent-runtime-layout{display:flex;flex-direction:column', $css);
        self::assertStringNotContainsString('grid-template-columns:290px minmax(0,1fr)', $css);
        self::assertStringContainsString('.mg-agent-runtime-main{order:1', $css);
        self::assertStringContainsString('.mg-agent-runtime-rail{order:2', $css);
        self::assertStringContainsString('.mg-agent-runtime-composer{position:sticky;bottom:0', $css);
    }
}