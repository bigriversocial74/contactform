<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgentHeaderTabBehaviorTest extends TestCase
{
    public function testSystemTabsAlwaysRemainInSharedHeader(): void
    {
        $header=file_get_contents(dirname(__DIR__,2).'/includes/header-components/app-header.php');
        self::assertIsString($header);
        self::assertStringContainsString("['agent','Agent','/agent.php']",$header);
        self::assertStringContainsString("['inbox','Inbox','/inbox.php']",$header);
        self::assertStringContainsString("['sent','Sent','/sent.php']",$header);
        self::assertStringContainsString("['claimed','Claimed','/claimed.php']",$header);
        self::assertStringNotContainsString('data-agent-tab-add',$header);
        self::assertStringNotContainsString('data-agent-header-create',$header);
        self::assertStringContainsString('data-product-header-create',$header);
    }

    public function testAddAgentTabControlIsRemoved(): void
    {
        $script=file_get_contents(dirname(__DIR__,2).'/assets/js/agent-tabs.js');
        $css=file_get_contents(dirname(__DIR__,2).'/assets/css/agent-workspace-layout.css');
        self::assertIsString($script);
        self::assertIsString($css);
        self::assertStringNotContainsString('data-agent-tab-add',$script);
        self::assertStringContainsString('.mg-agent-tab-add{display:none!important}',$css);
        self::assertStringContainsString('.mg-header-product-create',$css);
        self::assertStringNotContainsString('.mg-header-agent-create',$css);
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
