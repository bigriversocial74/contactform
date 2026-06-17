<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgentWorkspaceLayoutTest extends TestCase
{
    public function testAgentCanvasActionsAreSeparatedFromTitleCopy(): void
    {
        $root=dirname(__DIR__,2);
        $workspace=file_get_contents($root.'/includes/agent-workspace.php');
        self::assertIsString($workspace);
        self::assertStringContainsString('mg-agent-canvas-toolbar',$workspace);
        self::assertStringContainsString('mg-agent-toolbar-copy',$workspace);
        self::assertStringContainsString('mg-agent-toolbar-actions',$workspace);
        self::assertStringContainsString('data-change-category',$workspace);
        self::assertStringContainsString('data-save-agent',$workspace);

        $actionsPosition=strpos($workspace,'mg-agent-toolbar-actions');
        $savePosition=strpos($workspace,'data-save-agent');
        self::assertNotFalse($actionsPosition);
        self::assertNotFalse($savePosition);
        self::assertGreaterThan($actionsPosition,$savePosition);
    }

    public function testAgentHeaderTabsKeepCanonicalNamesAndOrder(): void
    {
        $header=file_get_contents(dirname(__DIR__,2).'/includes/header-components/app-header.php');
        self::assertIsString($header);
        $positions=[];
        foreach(['Agent','Inbox','Sent','Claimed'] as $label){
            $positions[$label]=strpos($header,"'{$label}'");
            self::assertNotFalse($positions[$label]);
        }
        self::assertLessThan($positions['Inbox'],$positions['Agent']);
        self::assertLessThan($positions['Sent'],$positions['Inbox']);
        self::assertLessThan($positions['Claimed'],$positions['Sent']);
    }

    public function testAgentTabsUseRealActiveInactiveTabStyling(): void
    {
        $css=file_get_contents(dirname(__DIR__,2).'/assets/css/agent-workspace-layout.css');
        self::assertIsString($css);
        self::assertStringContainsString('.mg-agent-tab-item a{',$css);
        self::assertStringContainsString('.mg-agent-tab-item a.is-active{',$css);
        self::assertStringContainsString('border-radius:11px',$css);
        self::assertStringContainsString('background:#2563eb',$css);
    }

    public function testAgentPageLoadsDedicatedLayoutStyles(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/agent.php');
        self::assertIsString($page);
        self::assertStringContainsString('/assets/css/agent-workspace-layout.css',$page);
    }
}
