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

    public function testCreateDialogUsesTheExistingGlobalHeaderControl(): void
    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');
        $layout = file_get_contents($this->root . '/includes/header.php');
        $footer = file_get_contents($this->root . '/includes/footer.php');
        $script = file_get_contents($this->root . '/assets/js/create-menu.js');
        $css = file_get_contents($this->root . '/assets/css/create-menu.css');

        self::assertIsString($header);
        self::assertIsString($layout);
        self::assertIsString($footer);
        self::assertIsString($script);
        self::assertIsString($css);
        self::assertStringContainsString('role="dialog"', $header);
        self::assertStringContainsString('/assets/css/create-menu.css', $layout);
        self::assertStringContainsString('/assets/js/create-menu.js', $footer);
        self::assertStringNotContainsString('mg-header-product-create', $header);
        self::assertStringNotContainsString('data-product-header-create', $header);
        self::assertStringNotContainsString('data-create-menu-trigger', $header);
        self::assertStringNotContainsString('.mg-header-product-create', $css);
        self::assertStringContainsString('.mg-unified-header .mg-header-actions > button', $script);
        self::assertStringContainsString("trigger.dataset.createMenuTrigger=''", $script);
        self::assertStringContainsString('new MutationObserver(discoverOriginalTrigger)', $script);
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

    public function testLoggedOutHomeHeaderRestoresSearchNavigationAndDemo(): void
    {
        $layout = file_get_contents($this->root . '/includes/header.php');
        $header = file_get_contents($this->root . '/includes/header-components/public-header.php');
        $css = file_get_contents($this->root . '/assets/css/public-header-footer-fixes.css');

        self::assertIsString($layout);
        self::assertIsString($header);
        self::assertIsString($css);
        self::assertStringNotContainsString('/assets/css/index-minimal-header.css', $layout);
        self::assertStringContainsString('class="mg-public-search"', $header);
        self::assertStringContainsString('Search Microgifter', $header);
        self::assertStringContainsString('/corporate-gifting.php', $header);
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
