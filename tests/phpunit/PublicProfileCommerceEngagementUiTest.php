<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicProfileCommerceEngagementUiTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testProfileLoadsAllSectionAssets(): void
    {
        $page = $this->read('profile.php');
        foreach ([
            '/assets/css/public-profile-storefront.css',
            '/assets/css/public-profile-engagement.css',
            '/assets/js/public-profile-runtime.js',
            '/assets/js/public-profile-storefront.js',
            '/assets/js/public-profile-engagement.js',
        ] as $asset) {
            self::assertStringContainsString($asset, $page);
        }
    }

    public function testStorefrontAndProductMarkupIsPresent(): void
    {
        $page = $this->read('profile.php');
        foreach ([
            'data-profile-storefront-section',
            'data-invest-panel="storefront"',
            'data-profile-products-grid',
            'data-profile-products-grid-clone',
            'data-products-load-more',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
    }

    public function testPostsMembershipsAndTipMarkupIsPresent(): void
    {
        $page = $this->read('profile.php');
        foreach ([
            'data-profile-posts-section',
            'data-profile-posts-list',
            'data-posts-load-more',
            'data-profile-support-section',
            'data-invest-panel="support"',
        ] as $marker) {
            self::assertStringContainsString($marker, $page);
        }
    }

    public function testSharedRuntimePublishesOnlyInitialProfileRead(): void
    {
        $runtime = $this->read('assets/js/public-profile-runtime.js');
        foreach (['product_limit=6', 'post_limit=6', 'plan_limit=6', "indexOf('_cursor=')", 'mg:public-profile:data'] as $needle) {
            self::assertStringContainsString($needle, $runtime);
        }
    }

    public function testSectionControllersUseCursorPaginationAndCanonicalActions(): void
    {
        $storefront = $this->read('assets/js/public-profile-storefront.js');
        foreach (['product_cursor=', 'cart-items.php', 'product_version_id', 'mg:cart:changed'] as $needle) {
            self::assertStringContainsString($needle, $storefront);
        }

        $engagement = $this->read('assets/js/public-profile-engagement.js');
        foreach (['post_cursor=', 'plan_cursor=', 'subscriptions/create.php', 'tips/create.php', 'mg:payment:requires-confirmation'] as $needle) {
            self::assertStringContainsString($needle, $engagement);
        }
    }

    public function testSectionControllersAvoidHtmlStringInjection(): void
    {
        foreach ([
            'assets/js/public-profile-storefront.js',
            'assets/js/public-profile-engagement.js',
        ] as $path) {
            $source = $this->read($path);
            self::assertStringContainsString('textContent', $source);
            self::assertStringContainsString('safeUrl(', $source);
            foreach (['.innerHTML =', 'insertAdjacentHTML(', 'document.write(', 'eval('] as $unsafe) {
                self::assertStringNotContainsString($unsafe, $source, $path);
            }
        }
    }

    public function testSectionStylesIncludeResponsiveLayouts(): void
    {
        $storefront = $this->read('assets/css/public-profile-storefront.css');
        $engagement = $this->read('assets/css/public-profile-engagement.css');
        foreach (['.mg-profile-product-grid', '.mg-profile-product-card', '@media(max-width:760px)'] as $needle) {
            self::assertStringContainsString($needle, $storefront);
        }
        foreach (['.mg-profile-post-card', '.mg-profile-support-grid', '.mg-profile-tip-card', '@media(max-width:820px)'] as $needle) {
            self::assertStringContainsString($needle, $engagement);
        }
    }
}
