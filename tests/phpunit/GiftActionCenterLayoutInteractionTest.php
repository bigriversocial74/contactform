<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GiftActionCenterLayoutInteractionTest extends TestCase
{
    public function testHeaderFolderTabsExposeNotificationBadges(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/header-components/app-header.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg-agent-tab-badge',$source);
        self::assertStringContainsString('data-gift-nav-count="<?= $tab[0] ?>"',$source);
        self::assertStringContainsString('data-gift-nav-unread="<?= $tab[0] ?>"',$source);
    }

    public function testFolderSpecificActionsUseSharedModal(): void
    {
        $root=dirname(__DIR__,2);
        $markup=file_get_contents($root.'/includes/gift-action-center.php');
        $script=file_get_contents($root.'/assets/js/gift-action-center.js');
        self::assertIsString($markup);
        self::assertIsString($script);
        self::assertStringContainsString('data-action-modal',$markup);
        foreach(['send','follow-up','claim','tip','message'] as $action){
            self::assertStringContainsString('data-action-form="'.$action.'"',$script);
        }
        self::assertStringContainsString("state.folder === 'sent'",$script);
        self::assertStringContainsString("state.folder === 'inbox'",$script);
        self::assertStringContainsString('data-gift-action="tip"',$script);
    }

    public function testInboxLoadOpensScrollablePppmPostDrawer(): void
    {
        $root=dirname(__DIR__,2);
        $markup=file_get_contents($root.'/includes/gift-action-center.php');
        $script=file_get_contents($root.'/assets/js/gift-action-center.js');
        $css=file_get_contents($root.'/assets/css/gift-action-center.css');
        self::assertIsString($markup);
        self::assertIsString($script);
        self::assertIsString($css);
        self::assertStringContainsString('data-gift-drawer-content',$markup);
        self::assertStringContainsString('function openContent(item)',$script);
        self::assertStringContainsString("type === 'load'",$script);
        self::assertStringContainsString('mg-pppm-post',$script);
        self::assertStringContainsString('scroll-snap-type:y',$css);
    }

    public function testActionModalBecomesFullScreenOnMobile(): void
    {
        $css=file_get_contents(dirname(__DIR__,2).'/assets/css/gift-action-center.css');
        self::assertIsString($css);
        self::assertStringContainsString('@media(max-width:760px)',$css);
        self::assertStringContainsString('width:100vw',$css);
        self::assertStringContainsString('height:100dvh',$css);
        self::assertStringContainsString('transform:translateY(0)',$css);
    }
}
