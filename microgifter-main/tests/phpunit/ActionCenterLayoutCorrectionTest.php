<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ActionCenterLayoutCorrectionTest extends TestCase
{
    public function testGiftCenterUsesAgentWorkspaceSidebarVariant(): void
    {
        $root=dirname(__DIR__,2);
        $workspace=file_get_contents($root.'/includes/gift-action-center.php');
        self::assertIsString($workspace);
        self::assertStringContainsString('agent-sidebar.php',$workspace);
        self::assertStringNotContainsString('account-sidebar.php',$workspace);
        self::assertStringContainsString('mg-app-shell mg-gift-center-page',$workspace);
        self::assertStringContainsString('mg-app-workspace mg-gift-center-workspace',$workspace);
    }

    public function testGiftCenterUsesFeedAndPppmDrawerWithoutDuplicateFolderTabs(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/gift-action-center.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg-gift-feed-column',$source);
        self::assertStringContainsString('data-gift-drawer',$source);
        self::assertStringContainsString('data-gift-list',$source);
        self::assertStringNotContainsString('mg-gift-folder-tabs',$source);
        self::assertStringNotContainsString('mg-gift-vertical-nav',$source);
    }

    public function testAllGiftRoutesLoadAgentShellAssets(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['inbox.php','sent.php','claimed.php'] as $file){
            $source=file_get_contents($root.'/'.$file);
            self::assertIsString($source);
            self::assertStringContainsString('agent-workspace-layout.css',$source);
            self::assertStringContainsString('gift-action-center.css',$source);
            self::assertStringContainsString('gift-action-center.js',$source);
            self::assertMatchesRegularExpression('/\$header_mode\s*=\s*[\'\"]agent[\'\"]/',$source);
            self::assertStringNotContainsString('account-sidebar.js',$source);
        }
    }

    public function testLoadDrawerIsTheDedicatedSelectedContentSurface(): void
    {
        $root=dirname(__DIR__,2);
        $markup=file_get_contents($root.'/includes/gift-action-center.php');
        $css=file_get_contents($root.'/assets/css/gift-action-center.css');
        self::assertIsString($markup);
        self::assertIsString($css);
        self::assertStringContainsString('data-gift-drawer',$markup);
        self::assertStringContainsString('data-gift-drawer-content',$markup);
        self::assertMatchesRegularExpression('/scroll-snap-type:y\s+(?:mandatory|proximity)/',$css);
    }
}
