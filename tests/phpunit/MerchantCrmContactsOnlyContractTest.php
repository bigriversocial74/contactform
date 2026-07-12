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

        self::assertStringContainsString('mg-crm-contacts-only', $view);
        self::assertStringContainsString('data-crm-contact-stat-strip', $view);
        self::assertStringContainsString('data-merchant-crm-table', $view);
        self::assertSame(5, substr_count($view, '<article'));

        foreach ([
            'mg-crm-toolbar',
            'data-crm-tab-target',
            'data-crm-tab-panel',
            'data-crm-segments',
            'data-crm-bulk-bar',
            'data-crm-bulk-modal',
        ] as $removed) {
            self::assertStringNotContainsString($removed, $view);
        }
    }

    public function testIndividualContactActionsRemainAvailable(): void
    {
        $view = file_get_contents($this->root . '/includes/merchant-crm-view.php');
        self::assertIsString($view);

        self::assertStringContainsString('data-crm-drawer', $view);
        self::assertStringContainsString('data-crm-message-modal', $view);
        self::assertStringContainsString('data-crm-reward-modal', $view);
    }

    public function testPageDoesNotLoadRemovedCrmModules(): void
    {
        $page = file_get_contents($this->root . '/merchant-crm.php');
        self::assertIsString($page);

        self::assertStringContainsString('/assets/css/merchant-crm-contacts-only.css?v=1.0.0', $page);
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

    public function testContactsOnlyCssHidesLegacySelectionColumn(): void
    {
        $css = file_get_contents($this->root . '/assets/css/merchant-crm-contacts-only.css');
        self::assertIsString($css);

        self::assertStringContainsString('.mg-crm-contacts-only', $css);
        self::assertStringContainsString('.mg-crm-select-cell', $css);
        self::assertStringContainsString('display:none!important', $css);
    }
}