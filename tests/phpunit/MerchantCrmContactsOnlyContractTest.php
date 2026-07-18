<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmContactsOnlyContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testViewContainsOnlyContactStatsAndContactRuntime(): void

    {
        $view = file_get_contents($this->root . '/includes/merchant-crm-view.php');
        self::assertIsString($view);
        foreach(['mg-crm-contacts-only','data-crm-desktop-hero','data-crm-desktop-directory','data-crm-mobile-overview','data-crm-contact-stat-strip','data-merchant-crm-table'] as $needle) self::assertStringContainsString($needle,$view);
        self::assertSame(8,substr_count($view,'<article'));
        foreach(['mg-crm-toolbar','data-crm-tab-target','data-crm-tab-panel','data-crm-segments','data-crm-bulk-bar','data-crm-bulk-modal'] as $removed) self::assertStringNotContainsString($removed,$view);



    }

    public function testIndividualContactActionsRemainAvailable(): void
    {
        $view = file_get_contents($this->root . '/includes/merchant-crm-view.php');
        self::assertIsString($view);

        self::assertStringContainsString('data-crm-drawer', $view);
        self::assertStringContainsString('data-crm-message-modal', $view);
        self::assertStringContainsString('data-crm-reward-modal', $view);
    }

    public function testPageLoadsCacheBumpedLayoutAfterLegacyStyles(): void
    {
        $page = file_get_contents($this->root . '/merchant-crm.php');
        self::assertIsString($page);

        self::assertStringContainsString('/assets/css/merchant-crm-contacts-only.css?v=1.1.0', $page);
        self::assertGreaterThan(
            strpos($page, 'merchant-crm-layout-stability.css?v=1.0.0'),
            strpos($page, 'merchant-crm-contacts-only.css?v=1.1.0')
        );

        foreach ([
            'merchant-crm-tabs.js',
            'merchant-crm-overview-consolidation.js',
            'merchant-crm-campaign-builder.js',
            'merchant-crm-performance-dashboard.js',
            'merchant-crm-retention-playbooks.js',
            'crm-media-segments.js',
        ] as $asset) {
            self::assertStringNotContainsString($asset, $page);
        }
    }

    public function testContactsOnlyCssDefinesFourVisibleDesktopColumns(): void
    {
        $css = file_get_contents($this->root . '/assets/css/merchant-crm-contacts-only.css');
        self::assertIsString($css);

        self::assertStringContainsString('Four visible columns: Contact, Campaign, Engagement, Actions', $css);
        self::assertStringContainsString('minmax(250px,1.08fr)', $css);
        self::assertStringContainsString('minmax(260px,1.12fr)', $css);
        self::assertStringContainsString('minmax(250px,.98fr)', $css);
        self::assertStringContainsString('minmax(190px,.72fr)', $css);
        self::assertStringContainsString('.mg-crm-select-cell,', $css);
        self::assertStringContainsString('.mg-crm-account-cell,', $css);
        self::assertStringContainsString('td:not(.mg-crm-select-cell):not(.mg-crm-account-cell)', $css);
    }

    public function testContactsOnlyCssPreventsTextAndActionOverflow(): void
    {
        $css = file_get_contents($this->root . '/assets/css/merchant-crm-contacts-only.css');
        self::assertIsString($css);

        self::assertStringContainsString('overflow-wrap:anywhere', $css);
        self::assertStringContainsString('text-overflow:ellipsis', $css);
        self::assertStringContainsString('.mg-crm-icon-btn span', $css);
        self::assertStringContainsString('max-width:36px!important', $css);
        self::assertStringContainsString('@media(max-width:820px)', $css);
        self::assertStringContainsString('grid-template-columns:minmax(0,1fr)!important', $css);
    }
}
