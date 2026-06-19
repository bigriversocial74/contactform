<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PageFoundationTest extends TestCase
{
    public function testUniversalPageShellAndAssetRegistryExist(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string) file_get_contents($root . '/includes/page.php');
        self::assertStringContainsString('function mg_page_manifest', $page);
        self::assertStringContainsString('function mg_asset_registry', $page);
        self::assertStringContainsString('function mg_resolve_page_assets', $page);
        self::assertStringContainsString('/header.php', (string) file_get_contents($root . '/includes/page-start.php'));
        self::assertStringContainsString('/footer.php', (string) file_get_contents($root . '/includes/page-end.php'));
    }

    public function testCurrentPublicAndApplicationHeadersRemainAvailable(): void
    {
        $root = dirname(__DIR__, 2);
        $header = (string) file_get_contents($root . '/includes/header.php');
        $app = (string) file_get_contents($root . '/includes/header-components/app-header.php');
        self::assertStringContainsString('data-mg-site-header', $header);
        self::assertStringContainsString('mg-site-header__search', $header);
        self::assertStringContainsString('data-mg-site-header-drawer', $header);
        self::assertStringContainsString('app-header.php', $header);
        self::assertStringContainsString('logged-in.php', $app);
        self::assertFileDoesNotExist($root . '/includes/header-v2.php');
    }

    public function testSharedPresentationEngineUsesExplicitStateMachine(): void
    {
        $root = dirname(__DIR__, 2);
        $script = (string) file_get_contents($root . '/assets/js/agent-presentation.js');
        $style = (string) file_get_contents($root . '/assets/css/agent-presentation.css');
        foreach (['idle', 'playing', 'paused', 'waiting_for_input', 'completed', 'replaying'] as $state) {
            self::assertStringContainsString($state, $script);
        }
        self::assertStringContainsString('prefers-reduced-motion', $style);
        self::assertStringContainsString('aria-live', $script);
    }

    public function testPublicFlowsKeepTheirOnboardingConfigs(): void
    {
        $root = dirname(__DIR__, 2) . '/config/onboarding/';
        foreach (['home', 'learn-more', 'signin', 'signup', 'forgot-password', 'reset-password'] as $page) {
            $source = (string) file_get_contents($root . $page . '.php');
            self::assertStringContainsString("'page'", $source);
            self::assertStringContainsString("'enabled'", $source);
        }
        self::assertStringContainsString("'customTimeline' => 'home-hero-revenue'", (string) file_get_contents($root . 'home.php'));
    }
}
