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
        foreach (['class="mg-profile-hero-card"', 'class="mg-profile-hero-identity"', 'class="mg-profile-hero-actions"', 'data-profile-avatar', 'data-profile-name', 'mg-profile-merchant-badge', 'data-profile-follow', 'data-profile-message', 'data-profile-share', 'data-profile-edit'] as $required) {
            self::assertStringContainsString($required, $page);
        }
        self::assertStringNotContainsString('data-profile-save', $page);
    }

    public function testPublicDataDashboardChartAndAnalyticsMarkupIsRemoved(): void
    {
        $page = $this->read('profile.php');
        foreach (['mg-invest-stat-board', 'mg-invest-chart-row', 'data-invest-market-chart', 'data-invest-demand-meter', 'mg-invest-sidebar', 'Portfolio Snapshot', 'Ticker Value', 'Merchant Score', 'data-invest-analytics-grid', 'data-invest-formula-list', 'data-invest-tab="analytics"', 'data-invest-panel="analytics"', 'Public analytics are not displayed.'] as $removed) {
            self::assertStringNotContainsString($removed, $page);
        }
    }

    public function testSixContentTabsAndContentFirstOverviewRemain(): void
    {
        $page = $this->read('profile.php');
        self::assertSame(6, substr_count($page, 'data-invest-tab='));
        foreach (['overview', 'products', 'stories', 'posts', 'campaigns', 'community'] as $tab) {
            self::assertStringContainsString('data-invest-tab="' . $tab . '"', $page);
        }
        foreach (['Featured Experiences', 'data-profile-products-grid', 'Active Campaigns', 'data-invest-campaigns-list', 'data-profile-community-summary', 'data-profile-community-campaigns', 'data-profile-community-accounts'] as $required) {
            self::assertStringContainsString($required, $page);
        }
    }

    public function testContentFirstStyleShowsMoreCoverAndResponsiveCards(): void
    {
        $page = $this->read('profile.php');
        $css = $this->read('assets/css/public-profile-content-first.css');
        self::assertStringContainsString('/assets/css/public-profile-content-first.css?v=1.0.0', $page);
        foreach (['height:560px!important', 'margin-top:-168px!important', 'grid-template-columns:minmax(0,1fr) 320px', '@media(max-width:900px)', '@media(max-width:680px)', '.mg-profile-campaign-list-full'] as $required) {
            self::assertStringContainsString($required, $css);
        }
    }

    public function testRuntimeUsesContentCardsAndDoesNotLoadProgressOrMarketSeries(): void
    {
        $runtime = $this->read('assets/js/public-profile-investment.js');
        foreach (['mg-profile-campaign-card', 'mg-profile-campaign-icon', 'mg-profile-campaign-chevron', '/api/public/profile-investment.php?slug='] as $required) {
            self::assertStringContainsString($required, $runtime);
        }
        foreach (['profile-market-series.php', "document.createElement('progress')", 'mg-profile-campaign-progress', 'data-campaign-progress'] as $removed) {
            self::assertStringNotContainsString($removed, $runtime);
        }
    }

    public function testProfileApiFallsBackToLinkedProductCoverForPosts(): void
    {
        $api = $this->read('api/public/profile.php');
        foreach (['mg_public_profile_attach_post_product_images', 'catalog_product_version_assets', "pva.role='cover'", "cover.status='ready'", "'source' => 'product_cover'", "'type' => 'image'", "'url' => \$product['cover_url']", 'mg_public_profile_attach_post_product_images($pdo, $data);'] as $required) {
            self::assertStringContainsString($required, $api);
        }
    }

    public function testCanonicalPublicProfileHooksRemainAvailable(): void
    {
        $page = $this->read('profile.php');
        foreach (['data-public-profile-page', 'data-profile-loading', 'data-profile-error', 'data-profile-content', 'data-profile-preview-banner', 'data-profile-cover', 'data-profile-avatar', 'data-profile-name', 'data-profile-headline', 'data-profile-biography', 'data-profile-links', 'data-profile-sections', 'data-profile-followers', 'data-profile-supporters', 'data-profile-products'] as $hook) {
            self::assertStringContainsString($hook, $page);
        }
    }
}
