<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmKpiCurrentV1Test extends TestCase
{
    private string $root;
    private string $page;
    private string $view;
    private string $css;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->page = (string) file_get_contents($this->root . '/merchant-crm.php');
        $this->view = (string) file_get_contents($this->root . '/includes/merchant-crm-view.php');
        $this->css = (string) file_get_contents($this->root . '/assets/css/merchant-crm-kpi-authoritative-v1.css');
    }

    public function testPageLoadsOnlyTheAuthoritativeKpiRepairLayer(): void
    {
        self::assertSame(1, substr_count($this->page, 'merchant-crm-kpi-authoritative-v1.css?v=1.0.0'));

        foreach ([
            'merchant-crm-kpi-cleanup.css',
            'merchant-crm-kpi-hard-reset.css',
            'merchant-crm-kpi-no-icons.css',
            'merchant-crm-analytics-cleanup-v5.css',
            'merchant-crm-kpi-data-polish-v6.css',
            'merchant-crm-kpi-hard-reset.js',
            'merchant-crm-kpi-no-icons.js',
            'merchant-crm-analytics-cleanup-v5.js',
            'merchant-crm-kpi-data-polish-v6.js',
        ] as $legacyAsset) {
            self::assertStringNotContainsString($legacyAsset, $this->page);
        }
    }

    public function testLiveKpiAndDesktopToolBindingsRemainAvailable(): void
    {
        foreach ([
            'data-crm-desktop-high',
            'data-crm-desktop-followup',
            'data-crm-desktop-claims',
            'data-crm-desktop-messages',
            'data-crm-desktop-active',
            'data-crm-desktop-verified',
            'data-crm-desktop-review',
            'data-crm-desktop-range',
            'data-crm-desktop-filter',
            'data-crm-desktop-export',
            'data-crm-desktop-pipeline',
        ] as $binding) {
            self::assertStringContainsString($binding, $this->view);
        }

        self::assertStringContainsString('class="mg-crm-trends"', $this->view);
    }

    public function testKpiLayoutIsFourRowsAndResponsiveWithoutMobileOverrides(): void
    {
        self::assertStringContainsString('grid-template-rows: minmax(28px, auto) 40px minmax(30px, auto) 30px', $this->css);
        self::assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr))', $this->css);
        self::assertStringContainsString('@media (min-width: 1450px)', $this->css);
        self::assertStringContainsString('grid-template-columns: repeat(7, minmax(0, 1fr))', $this->css);
        self::assertStringContainsString('@media (min-width: 981px)', $this->css);
        self::assertStringNotContainsString('@media (max-width: 980px)', $this->css);
    }

    public function testAnalyticsAndPipelineControlsAreNotRemoved(): void
    {
        self::assertMatchesRegularExpression('/\.mg-crm-trends\s*\{[^}]*display:\s*grid\s*!important/s', $this->css);
        self::assertMatchesRegularExpression('/\.mg-crm-desktop-view-pipeline\s*\{[^}]*display:\s*inline-flex\s*!important/s', $this->css);
        self::assertStringContainsString('merchant-crm-mobile-dashboard-contract.css?v=1.0.0', $this->page);
        self::assertStringContainsString('merchant-crm-mobile-card-regression-fix.css?v=1.0.0', $this->page);
    }
}
