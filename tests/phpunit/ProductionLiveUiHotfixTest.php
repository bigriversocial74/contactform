<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionLiveUiHotfixTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($this->root . '/' . $path);
        self::assertIsString($contents, $path);
        return $contents;
    }

    public function testLoggedInMenuRoutesRemainAvailable(): void
    {
        foreach (['includes/header-templates/logged-in.php','microgifter-main/includes/header-templates/logged-in.php'] as $path) {
            $source = $this->readFile($path);
            foreach (['/inbox.php','/feed.php','/merchant.php','/account-admin.php'] as $route) {
                self::assertStringContainsString($route, $source, $path);
            }
            foreach (['IN/OUT Box','My Feed','Merchant Dashboard'] as $label) {
                self::assertStringContainsString($label, $source, $path);
            }
        }
    }

    public function testPublicAndAppHeadersExposeCurrentTemplateBoundaries(): void
    {
        foreach (['includes/header-components/public-header.php','microgifter-main/includes/header-components/public-header.php'] as $path) {
            $source = $this->readFile($path);
            self::assertStringContainsString('data-mg-universal-header', $source, $path);
            self::assertStringContainsString('Microgifter', $source, $path);
        }

        $appHeader = $this->readFile('includes/header-components/app-header.php');
        $createTemplate = $this->readFile('includes/header-templates/create-menu.php');
        self::assertStringContainsString('data-global-create', $appHeader);
        self::assertStringContainsString('data-create-menu-option="post"', $createTemplate);
        self::assertStringContainsString('/merchant-locations.php', $createTemplate);
    }

    public function testCreateMenuAndPostComposerAssetsRemainWired(): void
    {
        $layout = $this->readFile('includes/header.php');
        $footer = $this->readFile('includes/footer.php');
        $createScript = $this->readFile('assets/js/create-menu.js');
        $composerScript = $this->readFile('assets/js/global-post-composer.js');
        $modalCss = $this->readFile('assets/css/post-composer-modal.css');

        self::assertStringContainsString('/assets/css/create-menu.css', $layout);
        self::assertStringContainsString('/assets/css/post-composer-modal.css', $layout);
        self::assertStringContainsString('/assets/js/create-menu.js', $footer);
        self::assertStringContainsString('/assets/js/global-post-composer.js', $footer);
        self::assertStringContainsString('looksLikePlusControl', $createScript);
        self::assertStringContainsString('event.preventDefault()', $composerScript);
        self::assertStringContainsString('.mg-post-composer-modal', $modalCss);
    }

    public function testFeedAndNewsfeedSurfacesRemainRoutable(): void
    {
        foreach (['feed.php','microgifter-main/feed.php'] as $path) {
            $source = $this->readFile($path);
            foreach (['data-feed-tab="discover"','data-feed-tab="following"','data-feed-tab="mine"','data-feed-list'] as $marker) {
                self::assertStringContainsString($marker, $source, $path);
            }
        }

        $newsfeed = $this->readFile('newfeed.php');
        $endpoint = $this->readFile('api/public/newsfeed.php');
        self::assertStringContainsString('data-newsfeed', $newsfeed);
        self::assertStringContainsString('class="mg-feed-tabs"', $newsfeed);
        self::assertStringContainsString('Sign in to view your feed.', $endpoint);
    }

    public function testAgentAndPublishErrorControllersRemainLoaded(): void
    {
        $tabs = $this->readFile('assets/js/agent-tabs.js');
        $controls = $this->readFile('assets/js/agent-controls.js');
        $footer = $this->readFile('includes/footer.php');
        $publishErrors = $this->readFile('assets/js/builder-publish-errors.js');

        self::assertStringContainsString('window.Microgifter.agents', $tabs);
        self::assertStringContainsString('applyUpdate: applyAgentUpdate', $tabs);
        self::assertStringContainsString('Microgifter.agents.setRuntimeStatus', $controls);
        self::assertStringContainsString('/assets/js/builder-publish-errors.js', $footer);
        self::assertStringContainsString('preservePublishError', $publishErrors);
    }
}
