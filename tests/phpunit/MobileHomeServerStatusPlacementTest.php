<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MobileHomeServerStatusPlacementTest extends TestCase
{
    public function testMobileStatusIndicatorLivesInSlideoutSidebarInsteadOfHeader(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/assets/js/homeserver-status-indicator.js');
        $css = file_get_contents($root . '/assets/css/homeserver-status-indicator.css');
        $header = file_get_contents($root . '/includes/header-components/app-header.php');

        self::assertIsString($script);
        self::assertIsString($css);
        self::assertIsString($header);

        self::assertStringContainsString("{ logo: '.mg-app-sidebar-brand .mg-sidebar-logo', parent: '.mg-app-sidebar-brand' }", $script);
        self::assertStringContainsString('@media(max-width:980px)', $css);
        self::assertStringContainsString('.mg-header-left>.mg-homeserver-status-trigger{display:none!important}', $css);
        self::assertStringContainsString('.mg-app-sidebar-brand>.mg-homeserver-status-trigger{display:inline-grid!important', $css);
        self::assertStringNotContainsString('.mg-header-left>.mg-homeserver-status-trigger{display:inline-grid', $css);
        self::assertStringContainsString('homeserver-status-indicator.css?v=1.2.1', $header);
    }
}
