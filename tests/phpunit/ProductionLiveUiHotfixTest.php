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

    public function testAccountMenusUseCanonicalFeedRoute(): void
    {
        $menus = [
            $this->readFile('includes/header-templates/logged-in.php'),
            $this->readFile('includes/header-components/public-header.php'),
            $this->readFile('microgifter-main/includes/header-templates/logged-in.php'),
            $this->readFile('microgifter-main/includes/header-components/public-header.php'),
        ];

        foreach ($menus as $source) {
            self::assertStringContainsString('href="/inbox.php"', $source);
            self::assertStringContainsString('IN/OUT Box', $source);
            self::assertStringContainsString('href="/feed.php"', $source);
            self::assertStringContainsString('My Feed', $source);
            self::assertStringContainsString('My Profile', $source);
            self::assertStringContainsString('My Storefront', $source);
            self::assertStringContainsString('href="/merchant.php"', $source);
            self::assertStringContainsString('Merchant Dashboard', $source);
            self::assertStringNotContainsString('Account dashboard', $source);
            self::assertStringNotContainsString('Open live agent', $source);
        }
    }

    public function testProfileAndStorefrontUrlLookupsRemainAvailable(): void
    {
        foreach ([$this->readFile('includes/header.php'), $this->readFile('microgifter-main/includes/header.php')] as $source) {
            self::assertStringContainsString("require_once dirname(__DIR__) . '/api/db.php';", $source);
            self::assertStringContainsString('$account_profile_url', $source);
            self::assertStringContainsString('$account_storefront_url', $source);
            self::assertStringContainsString('SELECT slug,status,visibility FROM public_profiles WHERE user_id=? LIMIT 1', $source);
            self::assertStringContainsString('SELECT slug,status FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1', $source);
        }
    }

    public function testCreateMenuAndPostComposerRemainWired(): void
    {
        $appHeader = $this->readFile('includes/header-components/app-header.php');
        $mirrorHeader = $this->readFile('microgifter-main/includes/header-components/app-header.php');
        $layout = $this->readFile('includes/header.php');
        $footer = $this->readFile('includes/footer.php');
        $createScript = $this->readFile('assets/js/create-menu.js');
        $composerScript = $this->readFile('assets/js/global-post-composer.js');
        $modalCss = $this->readFile('assets/css/post-composer-modal.css');

        foreach ([$appHeader, $mirrorHeader] as $source) {
            self::assertStringContainsString('role="dialog"', $source);
            self::assertStringContainsString('data-create-menu-option="post"', $source);
            self::assertStringContainsString('/feed.php', $source);
            self::assertStringContainsString('/agent.php', $source);
            self::assertStringContainsString('/merchant-locations.php', $source);
        }

        self::assertStringContainsString('/assets/css/create-menu.css', $layout);
        self::assertStringContainsString('/assets/css/post-composer-modal.css', $layout);
        self::assertStringContainsString('/assets/js/create-menu.js', $footer);
        self::assertStringContainsString('/assets/js/global-post-composer.js', $footer);
        self::assertStringContainsString('looksLikePlusControl', $createScript);
        self::assertStringContainsString('event.preventDefault()', $composerScript);
        self::assertStringContainsString('.mg-post-composer-modal', $modalCss);
    }

    public function testCommunityFeedAndFollowingFeedSurfacesRemainSeparated(): void
    {
        foreach ([$this->readFile('feed.php'), $this->readFile('microgifter-main/feed.php')] as $source) {
            self::assertStringContainsString('data-feed-tab="discover"', $source);
            self::assertStringContainsString('data-feed-tab="following"', $source);
            self::assertStringContainsString('data-feed-tab="mine"', $source);
            self::assertStringContainsString('data-feed-list', $source);
            self::assertStringNotContainsString('mg-feed-hero', $source);
        }

        $newsfeed = $this->readFile('newfeed.php');
        $endpoint = $this->readFile('api/public/newsfeed.php');
        self::assertStringContainsString('data-newsfeed', $newsfeed);
        self::assertStringContainsString('class="mg-feed-tabs"', $newsfeed);
        self::assertStringContainsString("mg_fail('Sign in to view your feed.', 401);", $endpoint);
    }

    public function testAgentAndPublishErrorControllersRemainWired(): void
    {
        $tabs = $this->readFile('assets/js/agent-tabs.js');
        $controls = $this->readFile('assets/js/agent-controls.js');
        $footer = $this->readFile('includes/footer.php');
        $publishErrors = $this->readFile('assets/js/builder-publish-errors.js');

        self::assertStringContainsString('window.Microgifter.agents', $tabs);
        self::assertStringContainsString('setRuntimeStatus: setRuntimeStatus', $tabs);
        self::assertStringContainsString('applyUpdate: applyAgentUpdate', $tabs);
        self::assertStringContainsString('Microgifter.agents.setRuntimeStatus(id, nextStatus)', $controls);
        self::assertStringContainsString('/assets/js/builder-publish-errors.js', $footer);
        self::assertStringContainsString('MutationObserver', $publishErrors);
    }

    public function testLoggedOutHomeHeaderKeepsSearchNavigationAndDemo(): void
    {
        $header = $this->readFile('includes/header-components/public-header.php');
        $mirrorHeader = $this->readFile('microgifter-main/includes/header-components/public-header.php');
        $css = $this->readFile('assets/css/public-header-footer-fixes.css');

        self::assertStringContainsString('class="mg-public-search"', $header);
        self::assertStringContainsString('Search Microgifter', $header);
        self::assertStringContainsString('/corporate.php', $header);
        self::assertStringContainsString('/corporate.php', $mirrorHeader);
        self::assertStringContainsString('/retail.php', $header);
        self::assertStringContainsString('/locations.php', $header);
        self::assertStringContainsString('class="mg-public-demo"', $header);
        self::assertStringContainsString('Book A Demo', $header);
        self::assertStringContainsString('.mg-public-search', $css);
        self::assertStringContainsString('.mg-public-demo', $css);
    }
}
