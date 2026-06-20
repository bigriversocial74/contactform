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

    public function testHeaderPlusButtonOpensCreateDialogInsteadOfDirectNavigation(): void
    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');

        self::assertIsString($header);
        self::assertStringContainsString('data-create-menu-trigger', $header);
        self::assertStringContainsString('data-product-header-create', $header);
        self::assertStringContainsString('role="dialog"', $header);
        self::assertStringNotContainsString('<a class="mg-header-product-create" href="/build.php"', $header);
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
        ] as $path => $label) {
            self::assertStringContainsString('href="' . $path . '"', $header);
            self::assertStringContainsString('<strong>' . $label . '</strong>', $header);
        }
    }

    public function testCreateDialogSupportsCloseAndEscapeBehavior(): void
    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');

        self::assertStringContainsString('data-create-menu-close', $header);
        self::assertStringContainsString("e.key==='Escape'", $header);
        self::assertStringContainsString("t.setAttribute('aria-expanded','true')", $header);
        self::assertStringContainsString("m.setAttribute('aria-hidden','false')", $header);
    }

    public function testLoggedOutHomeHeaderRemovesSearchAndNavigation(): void
    {
        $layout = file_get_contents($this->root . '/includes/header.php');
        $css = file_get_contents($this->root . '/assets/css/index-minimal-header.css');

        self::assertIsString($layout);
        self::assertIsString($css);
        self::assertStringContainsString('/assets/css/index-minimal-header.css', $layout);
        self::assertStringContainsString('data-authenticated="false"', $css);
        self::assertStringContainsString('data-page-id="home"', $css);
        self::assertStringContainsString('.mg-public-nav', $css);
        self::assertStringContainsString('input[type="search"]', $css);
    }
}
