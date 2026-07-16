<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DesignStudioContentCalendarContractTest extends TestCase
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

    public function testCalendarMigrationAndManifestAreRegistered(): void
    {
        $sql = $this->read('database/20260716_design_studio_content_calendar.sql');
        $manifest = $this->read('config/migrations.php');

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS design_content_schedule', $sql);
        self::assertStringContainsString('idx_design_content_schedule_merchant_date', $sql);
        self::assertStringContainsString("'20260716_design_studio_content_calendar.sql'", $manifest);
    }

    public function testCalendarApiIsMerchantScopedAndSupportsThirtyDayPlanning(): void
    {
        $api = $this->read('api/merchant/design-content-calendar.php');

        foreach ([
            "mg_merchant_require_permission(\$method === 'GET' ? 'catalog.products.view' : 'catalog.products.manage')",
            'MG_DESIGN_CALENDAR_DAYS = 30',
            "['generate', 'update', 'delete']",
            'WHERE merchant_user_id = ?',
            "status <> 'archived'",
            "mg_require_csrf_for_write(\$input)",
            "mg_rate_limit('merchant.design_calendar.write'",
            "mg_audit('merchant.design_calendar_generated'",
            "mg_audit('merchant.design_calendar_updated'",
            "mg_audit('merchant.design_calendar_removed'",
        ] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testDesignStudioProvidesPrintSocialAndCalendarViews(): void
    {
        $workspace = $this->read('includes/personal-agent/workspace-design.php');
        $calendar = $this->read('includes/personal-agent/workspace-design-calendar.php');

        foreach ([
            'data-design-mode="print"',
            'data-design-mode="social"',
            'data-calendar-mode-button',
            "require __DIR__ . '/workspace-design-calendar.php'",
        ] as $needle) {
            self::assertStringContainsString($needle, $workspace);
        }

        foreach ([
            '30-day content calendar',
            'data-calendar-view="grid"',
            'data-calendar-view="stack"',
            'data-calendar-product-list',
            'data-calendar-generator',
            'data-calendar-grid',
            'data-calendar-stack',
        ] as $needle) {
            self::assertStringContainsString($needle, $calendar);
        }
    }

    public function testCalendarClientSupportsProductSelectionCreativeLinksAndManualDownloads(): void
    {
        $client = $this->read('assets/js/personal-agent-design-studio-calendar.js');

        foreach ([
            '/api/merchant/products.php?sort=updated_desc&limit=50',
            '/api/merchant/design-content-calendar.php',
            "action: 'generate'",
            "action: 'update'",
            "action: 'delete'",
            'data-calendar-format-select',
            'data-calendar-layout-select',
            'data-calendar-status-select',
            'activateSocialWorkspace',
            'data-social-download',
            "params.get('mode') === 'social'",
        ] as $needle) {
            self::assertStringContainsString($needle, $client);
        }

        foreach (['.innerHTML =', 'insertAdjacentHTML(', 'document.write(', 'eval('] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $client);
        }
    }

    public function testLogoScaleOverridesAreExactlyFiftyPercentLarger(): void
    {
        $css = $this->read('assets/css/personal-agent-design-studio-calendar.css');

        foreach ([
            '.mg-agent-print-brand img',
            'width: 99px;',
            'height: 99px;',
            '.mg-agent-social-photo-placeholder img',
            'width: 138px;',
            'height: 138px;',
            '.mg-agent-social-footer img',
            'width: 51px;',
            'height: 51px;',
        ] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    public function testAgentLoadsCalendarAssetsAfterExistingDesignStudioAssets(): void
    {
        $agent = $this->read('agent.php');
        self::assertStringContainsString('/assets/css/personal-agent-design-studio-calendar.css?v=1.0.0', $agent);
        self::assertStringContainsString('/assets/js/personal-agent-design-studio-calendar.js?v=1.0.0', $agent);
    }
}
