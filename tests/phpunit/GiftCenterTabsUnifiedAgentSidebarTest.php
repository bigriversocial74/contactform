<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftCenterTabsUnifiedAgentSidebarTest extends TestCase
{
    public function testGiftPagesRestoreInboxSentAndClaimedHeaderTabs(): void
    {
        $root = dirname(__DIR__, 2);
        $header = file_get_contents($root . '/includes/header-components/app-header.php');
        self::assertIsString($header);
        self::assertStringContainsString("\$gift_center_tabs = ['inbox', 'sent', 'claimed'];", $header);
        self::assertStringContainsString('data-gift-center-tabs', $header);
        self::assertStringContainsString('href="/inbox.php"', $header);
        self::assertStringContainsString('href="/sent.php"', $header);
        self::assertStringContainsString('href="/claimed.php"', $header);
        self::assertStringContainsString('data-gift-nav-count="inbox"', $header);
        self::assertStringContainsString('data-gift-nav-count="sent"', $header);
        self::assertStringContainsString('data-gift-nav-count="claimed"', $header);
        self::assertStringContainsString("\$workspace_agent_tabs = ['agent'];", $header);

        foreach (['inbox.php' => 'inbox', 'sent.php' => 'sent', 'claimed.php' => 'claimed'] as $file => $tab) {
            $page = file_get_contents($root . '/' . $file);
            self::assertIsString($page);
            self::assertStringContainsString('$agent_tab', $page);
            self::assertStringContainsString("'{$tab}'", $page);
        }
    }

    public function testMobileHeaderRestoresExistingGlobalCreateMenu(): void
    {
        $root = dirname(__DIR__, 2);
        $header = file_get_contents($root . '/includes/header-components/app-header.php');
        $loggedIn = file_get_contents($root . '/includes/header-templates/logged-in.php');
        self::assertIsString($header);
        self::assertIsString($loggedIn);
        self::assertStringContainsString('data-header-create', $loggedIn);
        self::assertStringContainsString('data-global-create', $loggedIn);
        self::assertStringContainsString('@media(max-width:640px)', $header);
        self::assertStringContainsString('.mg-app-page .mg-header-create{display:grid!important', $header);
    }

    public function testAgentsUseOneOriginalSidebarAndOneVisibleCreationAction(): void
    {
        $root = dirname(__DIR__, 2);
        $sidebar = file_get_contents($root . '/includes/personal-agent-sidebar.php');
        $templates = file_get_contents($root . '/includes/multi-agent-workspace-data.php');
        self::assertIsString($sidebar);
        self::assertIsString($templates);
        self::assertStringContainsString('data-sidebar-agent-row="default"', $sidebar);
        self::assertStringContainsString('data-sidebar-agent-id="default"', $sidebar);
        self::assertStringContainsString('data-sidebar-agent-manage', $sidebar);
        self::assertStringContainsString('data-open-agent-selector', $sidebar);
        self::assertStringContainsString('>Add Agent</strong>', $sidebar);
        self::assertSame(1, substr_count($sidebar, '>Add Agent</strong>'));
        self::assertStringContainsString("'chat_agent'", $templates);
        self::assertSame(1, substr_count($sidebar, 'data-personal-agent-new-chat'));
        self::assertStringContainsString('Legacy non-rendered compatibility contract', $sidebar);
        self::assertStringNotContainsString('mg-sidebar-agent-list', $sidebar);
        self::assertStringNotContainsString('Default workspace', $sidebar);
    }
}
