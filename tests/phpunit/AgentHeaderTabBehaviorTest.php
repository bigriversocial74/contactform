<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgentHeaderTabBehaviorTest extends TestCase
{
    public function testSystemTabsAlwaysRemainInSharedHeader(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $createMenu=file_get_contents($root.'/includes/header-templates/create-menu.php');
        self::assertIsString($header); self::assertIsString($createMenu);
        foreach(["['agent','Agent','/agent.php',$can_agent_workspace]","['inbox','Inbox','/inbox.php',true]","['sent','Sent','/sent.php',true]","['claimed','Claimed','/claimed.php',true]"] as $needle) self::assertStringContainsString($needle,$header);
        self::assertStringContainsString("'option' => 'microgift'",$createMenu);
        self::assertStringNotContainsString('data-agent-tab-add',$header);

    }

    public function testAuthenticatedCustomersCanSeeAgentTabWithoutMerchantAccess(): void

    {
        $header=file_get_contents(dirname(__DIR__,2).'/includes/header-components/app-header.php');
        self::assertIsString($header);
        self::assertStringContainsString('$is_authenticated_user = mg_current_user() !== null;',$header);
        self::assertStringContainsString('$can_agent_workspace = $is_authenticated_user || $can_merchant_nav',$header);
        self::assertStringContainsString("['agent','Agent','/agent.php',\$can_agent_workspace]",$header);



    }

    public function testAddAgentTabAndDuplicateCreateControlsAreRemoved(): void
    {
        $root=dirname(__DIR__,2);
        $script=file_get_contents($root.'/assets/js/agent-tabs.js');
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $createMenu=file_get_contents($root.'/assets/js/create-menu.js');
        $css=file_get_contents($root.'/assets/css/agent-workspace-layout.css');
        self::assertIsString($script);
        self::assertIsString($header);
        self::assertIsString($createMenu);
        self::assertIsString($css);
        self::assertStringNotContainsString('data-agent-tab-add',$script);
        self::assertStringContainsString('.mg-agent-tab-add{display:none!important}',$css);
        self::assertStringNotContainsString('create_menu_button',$header);
        self::assertStringNotContainsString('mg-header-product-create',$header);
        self::assertStringNotContainsString("createElement('button')",$createMenu);
        self::assertStringContainsString('looksLikePlusControl',$createMenu);
        self::assertStringContainsString('explicitTriggerSelector',$createMenu);
        self::assertStringContainsString('.mg-header-build-link',$createMenu);
        self::assertStringContainsString('href="/lists.php?action=create" data-global-create',$header);
        self::assertStringContainsString("document.addEventListener('click'",$createMenu);
    }

    public function testDeleteControlLivesInsideActiveSavedAgentTab(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/agent-tabs.js');
        $css=file_get_contents(dirname(__DIR__,2).'/assets/css/agent-workspace-layout.css');
        self::assertIsString($script);
        self::assertIsString($css);
        self::assertStringContainsString('data-agent-tab-delete',$script);
        self::assertStringContainsString("await Microgifter.delete('/api/agents/item.php'",$script);
        self::assertStringContainsString('.mg-agent-tab-close{position:absolute;top:4px;right:4px',$css);
        self::assertStringNotContainsString('data-agent-tab-close',$script);
    }
}
