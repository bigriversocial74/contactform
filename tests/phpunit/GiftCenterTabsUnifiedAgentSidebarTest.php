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
        self::assertStringContainsString("\$workspace_agent_tabs = ['agent'];", $header);

        foreach (['inbox.php' => 'inbox', 'sent.php' => 'sent', 'claimed.php' => 'claimed'] as $file => $tab) {
            $page = file_get_contents($root . '/' . $file);
            self::assertIsString($page);
            self::assertStringContainsString('$agent_tab', $page);
            self::assertStringContainsString("'{$tab}'", $page);
        }
    }

    public function testSpecializedAgentsUseTheOriginalSidebarMenuInsteadOfASecondAgentSystem(): void
    {
        $sidebar = file_get_contents(dirname(__DIR__, 2) . '/includes/personal-agent-sidebar.php');
        self::assertIsString($sidebar);
        self::assertStringContainsString('data-personal-agent-new-chat', $sidebar);
        self::assertStringContainsString('data-sidebar-agent-row', $sidebar);
        self::assertStringContainsString('data-sidebar-agent-id', $sidebar);
        self::assertStringContainsString('data-sidebar-agent-manage', $sidebar);
        self::assertStringContainsString('data-open-agent-selector', $sidebar);
        self::assertStringContainsString('>Add Agent</strong>', $sidebar);
        self::assertStringNotContainsString('mg-sidebar-agent-list', $sidebar);
        self::assertStringNotContainsString('Default workspace', $sidebar);
    }
}
