<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AccountSidebarLayoutTest extends TestCase
{
    public function testCommerceCenterUsesSharedResponsiveSidebar(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/account-commerce.php');
        $sidebar=file_get_contents($root.'/includes/account-sidebar.php');
        $css=file_get_contents($root.'/assets/css/account-commerce.css');
        $js=file_get_contents($root.'/assets/js/account-sidebar.js');
        self::assertIsString($page);
        self::assertIsString($sidebar);
        self::assertIsString($css);
        self::assertIsString($js);
        self::assertStringContainsString('includes/account-sidebar.php',$page);
        self::assertStringContainsString('/assets/js/account-sidebar.js',$page);
        foreach(['Overview','Orders','Items','Inbox','Sent','Claimed'] as $label){self::assertStringContainsString($label,$sidebar);}
        self::assertStringContainsString('.mg-account-layout',$css);
        self::assertStringContainsString('data-account-sidebar-toggle',$js);
    }
}
