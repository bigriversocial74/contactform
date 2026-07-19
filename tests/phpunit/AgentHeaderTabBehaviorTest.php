<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgentHeaderTabBehaviorTest extends TestCase
{
    public function testAgentWorkspaceUsesPermanentDefaultAndDynamicAgentTabs(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        self::assertIsString($header);

        self::assertStringContainsString("\$workspace_agent_tabs = ['agent'];",$header);
        self::assertStringContainsString('data-system-tab="agent"',$header);
        self::assertStringContainsString('href="/agent.php"',$header);
        self::assertStringContainsString('>Agent</span>',$header);
        self::assertStringContainsString('mg_multi_agent_open_tabs',$header);
        self::assertStringContainsString('data-agent-tab-id',$header);
        self::assertStringContainsString('data-agent-add-tab',$header);

        foreach (["['inbox','Inbox'","['sent','Sent'","['claimed','Claimed'"] as $removedSystemTab) {
            self::assertStringNotContainsString($removedSystemTab,$header);
        }
    }

    public function testAuthenticatedCustomersCanSeeAgentWorkspaceWithoutMerchantAccess(): void
    {
        $header=file_get_contents(dirname(__DIR__,2).'/includes/header-components/app-header.php');
        self::assertIsString($header);
        self::assertStringContainsString('$is_authenticated_user = mg_current_user() !== null;',$header);
        self::assertStringContainsString('$can_agent_workspace = $is_authenticated_user || $can_merchant_nav',$header);
        self::assertStringContainsString("\$workspace_agent_tabs = ['agent'];",$header);
    }

    public function testAddAgentControlOpensTheEmbeddedMultiAgentSelector(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $workspaceScript=file_get_contents($root.'/assets/js/multi-agent-workspace.js');
        $createMenu=file_get_contents($root.'/assets/js/create-menu.js');
        self::assertIsString($header);
        self::assertIsString($workspaceScript);
        self::assertIsString($createMenu);

        self::assertStringContainsString('class="mg-agent-tab-add"',$header);
        self::assertStringContainsString('data-agent-add-tab',$header);
        self::assertStringContainsString('[data-agent-add-tab]',$workspaceScript);
        self::assertStringContainsString('data-open-agent-selector',$workspaceScript);
        self::assertStringNotContainsString('create_menu_button',$header);
        self::assertStringNotContainsString('mg-header-product-create',$header);
        self::assertStringNotContainsString("createElement('button')",$createMenu);
        self::assertStringContainsString('href="/lists.php?action=create" data-global-create',$header);
    }

    public function testSpecializedAgentsUseManagementModalInsteadOfInlineDelete(): void
    {
        $root=dirname(__DIR__,2);
        $script=file_get_contents($root.'/assets/js/multi-agent-workspace.js');
        $workspace=file_get_contents($root.'/includes/personal-agent/multi-agent-workspace.php');
        self::assertIsString($script);
        self::assertIsString($workspace);

        self::assertStringContainsString('data-agent-management-modal',$workspace);
        self::assertStringContainsString('data-agent-management-action="pause"',$workspace);
        self::assertStringContainsString('data-agent-management-action="archive"',$workspace);
        self::assertStringContainsString('data-agent-management-action="delete"',$workspace);
        self::assertStringContainsString("/api/agents/item.php",$script);
        self::assertStringContainsString("/api/agents/archive.php",$script);
        self::assertStringContainsString("/api/agents/status.php",$script);
    }
}
