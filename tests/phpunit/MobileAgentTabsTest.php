<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MobileAgentTabsTest extends TestCase
{
    public function testClaimedTabExistsAndHeaderCreateMenuReplacesDirectProductLink(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/header-components/app-header.php');
        self::assertIsString($source);
        self::assertStringContainsString("['claimed','Claimed','/claimed.php']",$source);
        self::assertStringNotContainsString('data-agent-tab-add',$source);
        self::assertStringNotContainsString('data-agent-header-create',$source);
        self::assertStringContainsString('data-product-header-create',$source);
        self::assertStringContainsString('data-create-menu-trigger',$source);
        self::assertStringContainsString('aria-label="Create something new"',$source);
        self::assertStringContainsString('data-create-menu-option="microgift"',$source);
        self::assertStringNotContainsString('<a class="mg-header-product-create" href="/build.php"',$source);
    }

    public function testProductCreateButtonStaysInMobileTabRowWithoutDesktopDuplicate(): void
    {
        $header=file_get_contents(dirname(__DIR__,2).'/includes/header-components/app-header.php');
        $css=file_get_contents(dirname(__DIR__,2).'/assets/css/app-fixes.css');
        self::assertIsString($header);
        self::assertIsString($css);
        self::assertStringNotContainsString('mg-header-agent-create',$header);
        self::assertStringNotContainsString('data-agent-header-create',$header);
        self::assertStringContainsString('.mg-section-agent .mg-agent-tab-add,',$css);
        self::assertStringContainsString('.mg-section-agent [data-agent-tab-add],',$css);
        self::assertStringContainsString('.mg-section-agent .mg-header-agent-create,',$css);
        self::assertStringContainsString('.mg-section-agent [data-agent-header-create]{display:none!important;visibility:hidden!important;pointer-events:none!important}',$css);
        self::assertStringContainsString('@media(min-width:981px){',$css);
        self::assertStringContainsString('.mg-section-agent .mg-header-product-create{display:none!important}',$css);
        self::assertStringContainsString('.mg-section-agent .mg-header-agent-tools{display:grid!important;grid-template-columns:minmax(0,1fr) 40px!important',$css);
        self::assertStringContainsString('.mg-section-agent .mg-header-product-create{grid-column:2!important;display:inline-flex!important;position:static!important',$css);
    }
}
