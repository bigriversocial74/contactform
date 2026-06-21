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

    public function testAgentControllerExposesImmediateRuntimeUpdates(): void
    {
        $tabs = file_get_contents($this->root . '/assets/js/agent-tabs.js');
        $controls = file_get_contents($this->root . '/assets/js/agent-controls.js');

        self::assertIsString($tabs);
        self::assertIsString($controls);
        self::assertStringContainsString('window.Microgifter.agents', $tabs);
        self::assertStringContainsString('setRuntimeStatus: setRuntimeStatus', $tabs);
        self::assertStringContainsString('applyUpdate: applyAgentUpdate', $tabs);
        self::assertStringContainsString('Microgifter.agents.setRuntimeStatus(id, nextStatus)', $controls);
        self::assertStringContainsString("Microgifter.post('/api/agents/status.php'", $controls);
        self::assertStringContainsString('Microgifter.agents.applyUpdate(response.data.agent)', $controls);
    }

    public function testCreateDialogBindsToExistingHeaderPlusWithoutAddingButtons(): void
    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');
        $mirrorHeader = file_get_contents($this->root . '/microgifter-main/includes/header-components/app-header.php');
        $layout = file_get_contents($this->root . '/includes/header.php');
        $mirrorLayout = file_get_contents($this->root . '/microgifter-main/includes/header.php');
        $footer = file_get_contents($this->root . '/includes/footer.php');
        $mirrorFooter = file_get_contents($this->root . '/microgifter-main/includes/footer.php');
        $script = file_get_contents($this->root . '/assets/js/create-menu.js');
        $css = file_get_contents($this->root . '/assets/css/create-menu.css');

        foreach ([$header,$mirrorHeader,$layout,$mirrorLayout,$footer,$mirrorFooter,$script,$css] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('role="dialog"', $header);
        self::assertStringContainsString('role="dialog"', $mirrorHeader);
        self::assertStringContainsString('/assets/css/create-menu.css', $layout);
        self::assertStringContainsString('/assets/css/create-menu.css', $mirrorLayout);
        self::assertStringContainsString('/assets/js/create-menu.js', $footer);
        self::assertStringContainsString('/assets/js/create-menu.js', $mirrorFooter);
        self::assertStringNotContainsString('mg-header-product-create', $header);
        self::assertStringNotContainsString('mg-header-product-create', $mirrorHeader);
        self::assertStringNotContainsString('data-product-header-create', $header);
        self::assertStringNotContainsString('data-product-header-create', $mirrorHeader);
        self::assertStringNotContainsString('.mg-header-product-create', $css);
        self::assertStringContainsString('looksLikePlusControl', $script);
        self::assertStringContainsString("href==='/build.php'", $script);
        self::assertStringContainsString("document.addEventListener('click'", $script);
        self::assertStringContainsString('new MutationObserver(discoverOriginalTriggers)', $script);
        self::assertStringNotContainsString("createElement('button')", $script);
    }

    public function testCreateDialogContainsEveryRequestedDestination(): void
    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');

        foreach ([
            '/build.php' => 'Microgift',
            '/feed.php' => 'Post',
            '/account-subscriptions.php' => 'Subscription',
            '/merchant-storefront.php' => 'Storefront',
            '/agent.php' => 'Agent',
            '/merchant-locations.php' => 'Add Location',
        ] as $path => $label) {
            self::assertStringContainsString('href="' . $path . '"', $header);
            self::assertStringContainsString('<strong>' . $label . '</strong>', $header);
        }
    }

    public function testAccountMenusUseInboxFeedProfileStorefrontAndMerchantLinks(): void
    {
        $layout = file_get_contents($this->root . '/includes/header.php');
        $mirrorLayout = file_get_contents($this->root . '/microgifter-main/includes/header.php');
        $appMenu = file_get_contents($this->root . '/includes/header-templates/logged-in.php');
        $publicMenu = file_get_contents($this->root . '/includes/header-components/public-header.php');
        $mirrorAppMenu = file_get_contents($this->root . '/microgifter-main/includes/header-templates/logged-in.php');
        $mirrorPublicMenu = file_get_contents($this->root . '/microgifter-main/includes/header-components/public-header.php');

        foreach ([$layout, $mirrorLayout] as $source) {
            self::assertIsString($source);
            self::assertStringContainsString('$account_profile_url', $source);
            self::assertStringContainsString('$account_storefront_url', $source);
            self::assertStringContainsString('SELECT slug,status,visibility FROM public_profiles WHERE user_id=? LIMIT 1', $source);
            self::assertStringContainsString('SELECT slug,status FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1', $source);
            self::assertStringContainsString("'/profile.php?slug='", $source);
            self::assertStringContainsString("'/store.php?s='", $source);
        }

        foreach ([$appMenu, $publicMenu, $mirrorAppMenu, $mirrorPublicMenu] as $source) {
            self::assertIsString($source);
            self::assertStringContainsString('href="/inbox.php"', $source);
            self::assertStringContainsString('IN/OUT Box', $source);
            self::assertStringContainsString('href="/newfeed.php"', $source);
            self::assertStringContainsString('My Feed', $source);
            self::assertStringContainsString('href="/account.php"', $source);
            self::assertStringContainsString('Profile Settings', $source);
            self::assertStringContainsString('My Profile', $source);
            self::assertStringContainsString('My Storefront', $source);
            self::assertStringContainsString('href="/merchant.php"', $source);
            self::assertStringContainsString('Merchant Dashboard', $source);
            self::assertStringNotContainsString('Account dashboard', $source);
            self::assertStringNotContainsString('Open live agent', $source);
        }
    }

    public function testFollowingOnlyNewsfeedPageAndEndpointAreWired(): void
    {
        $page = file_get_contents($this->root . '/newfeed.php');
        $mirrorPage = file_get_contents($this->root . '/microgifter-main/newfeed.php');
        $endpoint = file_get_contents($this->root . '/api/public/newsfeed.php');
        $mirrorEndpoint = file_get_contents($this->root . '/microgifter-main/api/public/newsfeed.php');
        $script = file_get_contents($this->root . '/assets/js/newsfeed.js');
        $mirrorScript = file_get_contents($this->root . '/microgifter-main/assets/js/newsfeed.js');
        $css = file_get_contents($this->root . '/assets/css/newsfeed.css');

        foreach ([$page, $mirrorPage, $endpoint, $mirrorEndpoint, $script, $mirrorScript, $css] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('$header_mode = \'account\';', $page);
        self::assertStringContainsString('data-newsfeed', $page);
        self::assertStringContainsString('/assets/js/newsfeed.js', $page);
        self::assertStringContainsString('/assets/css/newsfeed.css', $page);
        self::assertStringContainsString('Latest from people you follow', $page);
        self::assertStringContainsString("require_once dirname(__DIR__) . '/social/_publishing.php';", $endpoint);
        self::assertStringContainsString("mg_fail('Sign in to view your feed.', 401);", $endpoint);
        self::assertStringContainsString("sf.follower_user_id=? AND sf.followed_user_id=fp.created_by_user_id AND sf.status='active'", $endpoint);
        self::assertStringContainsString('fp.created_by_user_id<>?', $endpoint);
        self::assertStringContainsString("'mode'=>'newsfeed'", $endpoint);
        self::assertStringContainsString('/api/public/newsfeed.php?limit=18', $script);
        self::assertStringContainsString('data-newsfeed-action="reaction"', $script);
        self::assertStringContainsString('.mg-newsfeed-page .mg-feed-tabs a', $css);
    }

    public function testCreatePostOptionOpensComposerModalWithoutNavigating(): void
    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');
        $mirrorHeader = file_get_contents($this->root . '/microgifter-main/includes/header-components/app-header.php');
        $layout = file_get_contents($this->root . '/includes/header.php');
        $mirrorLayout = file_get_contents($this->root . '/microgifter-main/includes/header.php');
        $footer = file_get_contents($this->root . '/includes/footer.php');
        $mirrorFooter = file_get_contents($this->root . '/microgifter-main/includes/footer.php');
        $modal = file_get_contents($this->root . '/includes/header-components/post-composer-modal.php');
        $composer = file_get_contents($this->root . '/includes/social-feed-composer.php');
        $feed = file_get_contents($this->root . '/feed.php');
        $script = file_get_contents($this->root . '/assets/js/global-post-composer.js');
        $css = file_get_contents($this->root . '/assets/css/post-composer-modal.css');

        foreach ([$header,$mirrorHeader,$layout,$mirrorLayout,$footer,$mirrorFooter,$modal,$composer,$feed,$script,$css] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('data-create-menu-option="post" aria-controls="mg-post-composer-modal"', $header);
        self::assertStringContainsString('data-create-menu-option="post" aria-controls="mg-post-composer-modal"', $mirrorHeader);
        self::assertStringContainsString("require __DIR__ . '/post-composer-modal.php'", $header);
        self::assertStringContainsString('data-global-post-composer', $modal);
        self::assertStringContainsString('id="mg-post-composer-modal"', $modal);
        self::assertStringContainsString('data-post-composer', $composer);
        self::assertStringContainsString('data-post-form', $composer);
        self::assertStringContainsString('data-feed-media-uploader', $composer);
        self::assertStringContainsString("require __DIR__ . '/includes/social-feed-composer.php'", $feed);
        self::assertStringContainsString('/assets/css/post-composer-modal.css', $layout);
        self::assertStringContainsString('/assets/css/post-composer-modal.css', $mirrorLayout);
        self::assertStringContainsString('/assets/js/global-post-composer.js', $footer);
        self::assertStringContainsString('/assets/js/global-post-composer.js', $mirrorFooter);
        self::assertStringContainsString('[data-create-menu-option="post"]', $script);
        self::assertStringContainsString('event.preventDefault()', $script);
        self::assertStringContainsString("MG.post('/api/social/posts.php'", $script);
        self::assertStringContainsString('.mg-post-composer-modal', $css);
    }

    public function testCreateDialogSupportsCloseEscapeAndFocusManagement(): void
    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');
        $script = file_get_contents($this->root . '/assets/js/create-menu.js');

        self::assertStringContainsString('data-create-menu-close', $header);
        self::assertStringContainsString("event.key==='Escape'", $script);
        self::assertStringContainsString("trigger.setAttribute('aria-expanded',value?'true':'false')", $script);
        self::assertStringContainsString("modal.setAttribute('aria-hidden','false')", $script);
        self::assertStringContainsString("event.key!=='Tab'", $script);
        self::assertStringContainsString('lastFocused.focus()', $script);
    }

    public function testLoggedOutHomeHeaderRestoresSearchNavigationDemoAndCorporateRoute(): void
    {
        $layout = file_get_contents($this->root . '/includes/header.php');
        $header = file_get_contents($this->root . '/includes/header-components/public-header.php');
        $mirrorHeader = file_get_contents($this->root . '/microgifter-main/includes/header-components/public-header.php');
        $css = file_get_contents($this->root . '/assets/css/public-header-footer-fixes.css');

        self::assertIsString($layout);
        self::assertIsString($header);
        self::assertIsString($mirrorHeader);
        self::assertIsString($css);
        self::assertStringNotContainsString('/assets/css/index-minimal-header.css', $layout);
        self::assertStringContainsString('class="mg-public-search"', $header);
        self::assertStringContainsString('Search Microgifter', $header);
        self::assertStringContainsString('/corporate.php', $header);
        self::assertStringContainsString('/corporate.php', $mirrorHeader);
        self::assertStringNotContainsString('/corporate-gifting.php', $header);
        self::assertStringContainsString('/retail.php', $header);
        self::assertStringContainsString('/locations.php', $header);
        self::assertStringContainsString('class="mg-public-demo"', $header);
        self::assertStringContainsString('Book A Demo', $header);
        self::assertStringContainsString('.mg-public-search', $css);
        self::assertStringContainsString('.mg-public-demo', $css);
    }

    public function testBuilderPublishErrorsRemainVisible(): void
    {
        $footer = file_get_contents($this->root . '/includes/footer.php');
        $script = file_get_contents($this->root . '/assets/js/builder-publish-errors.js');

        self::assertStringContainsString('/assets/js/builder-publish-errors.js', $footer);
        self::assertStringContainsString("status.textContent='Publish failed: '+message", $script);
        self::assertStringContainsString('MutationObserver', $script);
    }
}
