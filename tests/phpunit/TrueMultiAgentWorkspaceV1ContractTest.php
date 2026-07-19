<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TrueMultiAgentWorkspaceV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testPermanentDefaultAgentAndDynamicTabsArePresent(): void
    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');
        self::assertStringContainsString('data-system-tab="agent"', $header);
        self::assertStringContainsString('data-agent-tab-id', $header);
        self::assertStringContainsString('data-agent-add-tab', $header);
        self::assertStringNotContainsString("['inbox','Inbox','/inbox.php'", $header);
        self::assertStringNotContainsString("['sent','Sent','/sent.php'", $header);
        self::assertStringNotContainsString("['claimed','Claimed','/claimed.php'", $header);
    }

    public function testSidebarKeepsInstalledAgentsWhenTabsClose(): void
    {
        $sidebar = file_get_contents($this->root . '/includes/personal-agent-sidebar.php');
        self::assertStringContainsString('mg_multi_agent_active_agents', $sidebar);
        self::assertStringContainsString('data-sidebar-agent-id', $sidebar);
        self::assertStringContainsString('data-sidebar-agent-manage', $sidebar);
        self::assertStringContainsString('Default workspace', $sidebar);
    }

    public function testSelectorAndLifecycleControlsExist(): void
    {
        $view = file_get_contents($this->root . '/includes/personal-agent/multi-agent-workspace.php');
        foreach (['birthday_occasion', 'local_shopping', 'merchant_campaign'] as $template) {
            self::assertStringContainsString($template, file_get_contents($this->root . '/includes/multi-agent-workspace-data.php'));
        }
        foreach (['close', 'pause', 'archive', 'delete'] as $action) {
            self::assertStringContainsString('data-agent-action="' . $action . '"', $view);
        }
        self::assertStringContainsString('Type <strong>DELETE</strong>', $view);
    }

    public function testMobileAndPersistenceContractsExist(): void
    {
        $css = file_get_contents($this->root . '/assets/css/multi-agent-workspace.css');
        $js = file_get_contents($this->root . '/assets/js/multi-agent-workspace.js');
        self::assertStringContainsString('@media(max-width:760px)', $css);
        self::assertStringContainsString('workspace_tab_open', $js);
        self::assertStringContainsString('/api/agents/status.php', $js);
        self::assertStringContainsString('/api/agents/archive.php', $js);
        self::assertStringContainsString("'DELETE'", $js);
    }
}
