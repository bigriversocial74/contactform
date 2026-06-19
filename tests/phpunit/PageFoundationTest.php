<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageFoundationTest extends TestCase
{
    public function testUniversalPageShellAndAssetRegistryExist(): void
    {
        $root = dirname(__DIR__, 2);
        $page = file_get_contents($root . '/includes/page.php');
        $start = file_get_contents($root . '/includes/page-start.php');
        $end = file_get_contents($root . '/includes/page-end.php');
        self::assertIsString($page);
        self::assertIsString($start);
        self::assertIsString($end);
        self::assertStringContainsString('function mg_page_manifest', $page);
        self::assertStringContainsString('function mg_asset_registry', $page);
        self::assertStringContainsString('function mg_resolve_page_assets', $page);
        self::assertStringContainsString('/header.php', $start);
        self::assertStringContainsString('/footer.php', $end);
    }

    public function testUniversalHeaderUsesInlinePublicLayoutAndSharedAppVariant(): void
    {
        $root = dirname(__DIR__, 2);
        $header = file_get_contents($root . '/includes/header.php');
        $app = file_get_contents($root . '/includes/header-components/app-header.php');
        foreach ([$header,$app] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('app-header.php', $header);
        self::assertStringContainsString('data-mg-site-header', $header);
        self::assertStringContainsString('data-mg-site-account', $header);
        self::assertStringContainsString('data-mg-site-header-drawer', $header);
        self::assertStringNotContainsString('public-header.php', $header);
        self::assertStringNotContainsString('data-agent-presentation-control', $header);
        self::assertStringContainsString('logged-in.php', $app);
        self::assertFileDoesNotExist($root . '/includes/header-v2.php');
    }

    public function testSharedPresentationEngineUsesExplicitStateMachine(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/assets/js/agent-presentation.js');
        $style = file_get_contents($root . '/assets/css/agent-presentation.css');
        self::assertIsString($script);
        self::assertIsString($style);
        foreach (['idle','playing','paused','waiting_for_input','completed','replaying'] as $state) {
            self::assertStringContainsString($state, $script);
        }
        self::assertStringContainsString('data-agent-presentation-control', $script);
        self::assertStringContainsString('prefers-reduced-motion', $style);
        self::assertStringContainsString('aria-live', $script);
    }

    public function testEveryCurrentPublicFlowHasItsOwnOnboardingConfig(): void
    {
        $root = dirname(__DIR__, 2) . '/config/onboarding/';
        foreach (['home','learn-more','signin','signup','forgot-password','reset-password'] as $page) {
            $path = $root . $page . '.php';
            self::assertFileExists($path);
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringContainsString("'page'", $source);
            self::assertStringContainsString("'enabled'", $source);
        }
    }

    public function testHomeAloneDeclaresCustomTimeline(): void
    {
        $root = dirname(__DIR__, 2) . '/config/onboarding/';
        $home = file_get_contents($root . 'home.php');
        self::assertIsString($home);
        self::assertStringContainsString("'customTimeline' => 'home-hero-revenue'", $home);
        foreach (['learn-more','signin','signup','forgot-password','reset-password'] as $page) {
            $source = file_get_contents($root . $page . '.php');
            self::assertIsString($source);
            self::assertStringNotContainsString("'customTimeline' => 'home-hero-revenue'", $source);
        }
    }
}
