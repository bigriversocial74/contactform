<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WorldStoreSidebarQuickKeywordsV1ContractTest extends TestCase
{
    public function testWorldAndStoreKeywordsRouteDirectly(): void
    {
        $root = dirname(__DIR__, 2);
        $catalog = file_get_contents($root . '/includes/agent-quick-actions.php');
        $sidebar = file_get_contents($root . '/includes/personal-agent-sidebar.php');
        $client = file_get_contents($root . '/assets/js/agent-sidebar-tools.js');

        self::assertIsString($catalog);
        self::assertIsString($sidebar);
        self::assertIsString($client);
        self::assertStringContainsString("['keyword'=>'world'", $catalog);
        self::assertStringContainsString("'href'=>'/world-canvas.php'", $catalog);
        self::assertStringContainsString("['keyword'=>'store'", $catalog);
        self::assertStringContainsString("'href'=>'/merchant-canvas.php'", $catalog);
        self::assertStringContainsString('data-agent-keyword-href', $sidebar);
        self::assertStringContainsString('window.location.assign(href)', $client);
    }

    public function testCanvasPagesUseChatEnabledInboxSidebar(): void
    {
        $root = dirname(__DIR__, 2);
        $world = file_get_contents($root . '/world-canvas.php');
        $store = file_get_contents($root . '/merchant-canvas.php');
        $standalone = file_get_contents($root . '/assets/js/merchant-agent-sidebar-history-standalone.js');
        $deepLinks = file_get_contents($root . '/assets/js/merchant-agent-chat-deep-links.js');

        foreach ([$world, $store, $standalone, $deepLinks] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('includes/personal-agent-sidebar.php', $world);
        self::assertStringContainsString('personal-agent-chat-history.js', $world);
        self::assertStringContainsString("$agent_sidebar_mode = 'personal'", $world);

        self::assertStringContainsString('includes/personal-agent-sidebar.php', $store);
        self::assertStringContainsString('merchant-agent-sidebar-history-standalone.js', $store);
        self::assertStringContainsString("$agent_sidebar_mode = 'merchant'", $store);
        self::assertStringContainsString('/api/ai/merchant-agent-chat.php', $standalone);
        self::assertStringContainsString('/merchant-agent-chat.php?thread=', $standalone);
        self::assertStringContainsString("params.get('thread')", $deepLinks);
        self::assertStringContainsString("params.get('new') === '1'", $deepLinks);
    }

    public function testSidebarLabelsAndDateHeadingsAreRemoved(): void
    {
        $root = dirname(__DIR__, 2);
        $sidebar = file_get_contents($root . '/includes/personal-agent-sidebar.php');
        $cleanup = file_get_contents($root . '/assets/css/personal-agent-sidebar-cleanup.css');

        self::assertIsString($sidebar);
        self::assertIsString($cleanup);
        self::assertStringNotContainsString('mg-personal-chat-history-label', $sidebar);
        self::assertStringNotContainsString('Personal chats', $sidebar);
        self::assertStringNotContainsString('>Private<', $sidebar);
        self::assertStringContainsString('.mg-personal-chat-group>h3{display:none!important}', $cleanup);
    }
}
