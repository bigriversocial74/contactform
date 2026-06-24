<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MobileAgentTabsTest extends TestCase
{
    public function testClaimedTabExistsAndCreateModalUsesExistingHeaderPlusControl(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $menu=file_get_contents($root.'/includes/header-templates/create-menu.php');
        $script=file_get_contents($root.'/assets/js/create-menu.js');
        self::assertIsString($header);
        self::assertIsString($menu);
        self::assertIsString($script);
        self::assertStringContainsString("['claimed','Claimed','/claimed.php']",$header);
        self::assertStringNotContainsString('data-agent-tab-add',$header);
        self::assertStringNotContainsString('data-agent-header-create',$header);
        self::assertStringNotContainsString('data-product-header-create',$header);
        self::assertStringNotContainsString('mg-header-product-create',$header);
        self::assertStringContainsString('data-create-menu-option="microgift"',$menu);
        self::assertStringContainsString('looksLikePlusControl',$script);
        self::assertStringContainsString("href==='/build.php'",$script);
        self::assertStringContainsString("document.addEventListener('click'",$script);
    }

    public function testNoSecondCreateButtonIsInjectedIntoMobileAgentTabs(): void
    {
        $root=dirname(__DIR__,2);
        $header=file_get_contents($root.'/includes/header-components/app-header.php');
        $mirror=file_get_contents($root.'/microgifter-main/includes/header-components/app-header.php');
        $script=file_get_contents($root.'/assets/js/create-menu.js');
        self::assertIsString($header);
        self::assertIsString($mirror);
        self::assertIsString($script);
        self::assertStringNotContainsString('create_menu_button',$header);
        self::assertStringNotContainsString('data-agent-header-create',$header);
        self::assertStringNotContainsString('data-product-header-create',$header);
        self::assertStringNotContainsString('mg-header-product-create',$mirror);
        self::assertStringNotContainsString("createElement('button')",$script);
        self::assertStringContainsString('new MutationObserver(discoverOriginalTriggers)',$script);
    }
}
