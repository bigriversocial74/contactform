<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgentBadgeBuilderBrandTest extends TestCase
{
    public function testAgentFolderBadgesAlwaysRenderWithFallbackCounts(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $script=file_get_contents($root.'/assets/js/agent-folder-counts.js');
        self::assertIsString($header);
        self::assertIsString($script);
        self::assertStringContainsString("['inbox' => 3, 'sent' => 2, 'claimed' => 2]",$header);
        self::assertStringNotContainsString('data-gift-nav-unread="<?= $tab[0] ?>" hidden',$header);
        self::assertStringContainsString('apply({inbox:1,sent:0,claimed:0})',$script);
        self::assertStringContainsString('/api/account/action-center.php?folder=inbox&limit=1',$script);
    }

    public function testActionCenterUsesSharedHeaderWithoutDuplicateToolbar(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $workspace=file_get_contents($root.'/includes/gift-action-center.php');
        $search=file_get_contents($root.'/assets/js/agent-global-search.js');
        self::assertIsString($header);
        self::assertIsString($workspace);
        self::assertIsString($search);
        self::assertStringContainsString('data-agent-tabs',$header);
        self::assertStringContainsString('data-global-create',$header);
        self::assertStringNotContainsString('data-gift-search',$workspace);
        self::assertStringNotContainsString('data-gift-folder-label',$workspace);
        self::assertStringContainsString('[data-gift-list] .mg-gift-row',$search);
    }

    public function testBuilderSidebarRestoresMicrogifterBrand(): void
    {
        $root=dirname(__DIR__,2);
        $sidebar=file_get_contents($root.'/includes/product-builder-sidebar.php');
        $build=file_get_contents($root.'/build.php');
        self::assertIsString($sidebar);
        self::assertIsString($build);
        self::assertStringContainsString('mg-builder-brand-row',$sidebar);
        self::assertStringContainsString('aria-label="Microgifter home"',$sidebar);
        self::assertStringContainsString('/assets/css/builder-shell-fixes.css',$build);
    }
}
