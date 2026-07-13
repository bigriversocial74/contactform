<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicProfileContentFirstContractTest extends TestCase
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

    public function testProfileUsesOneUnifiedIdentityAndActionCard(): void
    {
        $page = $this->read('profile.php');

        self::assertStringContainsString('class="mg-profile-hero-card"', $page);
        self::assertStringContainsString('class="mg-profile-hero-identity"', $page);
        self::assertStringContainsString('class="mg-profile-hero-actions"', $page);
        self::assertStringContainsString('data-profile-avatar', $page);
        self::assertStringContainsString('data-profile-name', $page);
        self::assertStringContainsString('mg-profile-merchant-badge', $page);

        foreach (['data-profile-follow', 'data-profile-message', 'data-profile-share', 'data-profile-edit'] as $action) {
            self::assertStringContainsString($action, $page);
        }
        self::assertStringNotContainsString('data-profile-save', $page);
    }

    public function testPublicDataDashboardChartAndAnalyticsMarkupIsRemoved(): void
    {
        $page = $this->read('profile.php');

        foreach ([
            'mg-invest-stat-board',
            'mg-invest-chart-row',
            'data-invest-market-chart',
            'data-invest-demand-meter',
            'mg-invest-sidebar',
            'Portfolio Snapshot',
            'Ticker Value',
            'Merchant Score',
            'data-invest-analytics-grid',
            'data-invest-formula-list',
            'data-invest-tab="analytics"',
            'data-invest-panel="analytics"',
            'Public analytics are not displayed.',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $page);
        }
    }

    public function testFiveContentTabsAndContentFirstOverviewRemain(): void
    {
        $page = $this->read('profile.php');

        self::assertSame(5, substr_count($page, 'data-invest-tab='));
        foreach (['overview', 'products', 'stories', 'posts', 'campaigns'] as $tab) {
            self::assertStringContainsString('data-invest-tab="' . $tab . '"', $page);
        }

        self::assertStringContainsString('Featured Experiences', $page);
        self::assertStringContainsString('data-profile-products-grid', $page);
        self::assertStringContainsString('Active Campaigns', $page);
        self::assertStringContainsString('data-invest-campaigns-list', $page);
    }

    public function testContentFirstStyleShowsMoreCoverAndResponsiveCards(): void
    {
        $page = $this->read('profile.php');
        $css = $this->read('assets/css/public-profile-content-first.css');

        self::assertStringContainsString('/assets/css/public-profile-content-first.css?v=1.0.0', $page);
        self::assertStringContainsString('height:560px!important', $css);
        self::assertStringContainsString('margin-top:-168px!important', $css);
        self::assertStringContainsString('grid-template-columns:minmax(0,1fr) 320px', $css);
        self::assertStringContainsString('@media(max-width:900px)', $css);
        self::assertStringContainsString('@media(max-width:680px)', $css);
        self::assertStringContainsString('.mg-profile-campaign-list-full', $css);
    }

    public function testRuntimeUsesContentCardsAndDoesNotLoadMarketSeries(): void
    {
        $runtime = $this->read('assets/js/public-profile-investment.js');

        self::assertStringContainsString('mg-profile-campaign-card', $runtime);
        self::assertStringContainsString('mg-profile-campaign-icon', $runtime);
        self::assertStringContainsString('mg-profile-campaign-chevron', $runtime);
        self::assertStringContainsString('/api/public/profile-investment.php?slug=', $runtime);
        self::assertStringNotContainsString('profile-market-series.php', $runtime);
        self::assertStringNotContainsString("document.createElement('progress')", $runtime);
        self::assertStringNotContainsString('issued_count', $runtime);
    }

    public function testProfileApiFallsBackToLinkedProductCoverForPosts(): void
    {
        $api = $this->read('api/public/profile.php');

        self::assertStringContainsString('mg_public_profile_attach_post_product_images', $api);
        self::assertStringContainsString('catalog_product_version_assets', $api);
        self::assertStringContainsString("pva.role='cover'", $api);
        self::assertStringContainsString("cover.status='ready'", $api);
        self::assertStringContainsString("'source' => 'product_cover'", $api);
        self::assertStringContainsString("'type' => 'image'", $api);
        self::assertStringContainsString("'url' => \$product['cover_url']", $api);
        self::assertStringContainsString('mg_public_profile_attach_post_product_images($pdo, $data);', $api);
    }

    public function testCanonicalPublicProfileHooksRemainAvailable(): void
    {
        $page = $this->read('profile.php');

        foreach ([
            'data-public-profile-page',
            'data-profile-loading',
            'data-profile-error',
            'data-profile-content',
            'data-profile-preview-banner',
            'data-profile-cover',
            'data-profile-avatar',
            'data-profile-name',
            'data-profile-headline',
            'data-profile-biography',
            'data-profile-links',
            'data-profile-sections',
            'data-profile-followers',
            'data-profile-supporters',
            'data-profile-products',
        ] as $hook) {
            self::assertStringContainsString($hook, $page);
        }
    }
}