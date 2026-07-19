<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MobileAgentTabsTest extends TestCase
{
    public function testMobileAgentHeaderUsesDefaultAgentDynamicTabsAndAddControl(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $css=file_get_contents($root.'/assets/css/multi-agent-workspace.css');
        self::assertIsString($header);
        self::assertIsString($css);

        self::assertStringContainsString('data-system-tab="agent"',$header);
        self::assertStringContainsString('data-agent-tab-id',$header);
        self::assertStringContainsString('data-agent-add-tab',$header);
        self::assertStringContainsString('overflow-x:auto',$css);
        self::assertStringContainsString('scrollbar-width:none',$css);
        self::assertStringContainsString('position:sticky',$css);
        self::assertStringNotContainsString("['claimed','Claimed'",$header);
        self::assertStringNotContainsString("['sent','Sent'",$header);
        self::assertStringNotContainsString("['inbox','Inbox'",$header);
    }

    public function testMobileAgentWorkspaceDoesNotInjectDuplicateGlobalCreateControls(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $script=file_get_contents($root.'/assets/js/create-menu.js');
        $workspaceScript=file_get_contents($root.'/assets/js/multi-agent-workspace.js');
        self::assertIsString($header);
        self::assertIsString($script);
        self::assertIsString($workspaceScript);

        self::assertStringNotContainsString('create_menu_button',$header);
        self::assertStringNotContainsString('data-agent-header-create',$header);
        self::assertStringNotContainsString('data-product-header-create',$header);
        self::assertStringNotContainsString('mg-header-product-create',$header);
        self::assertStringNotContainsString("createElement('button')",$script);
        self::assertStringContainsString('explicitTriggerSelector',$script);
        self::assertStringContainsString('[data-agent-add-tab]',$workspaceScript);
        self::assertStringContainsString('data-open-agent-selector',$workspaceScript);
    }
}
