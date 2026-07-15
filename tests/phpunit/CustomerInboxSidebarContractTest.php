<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CustomerInboxSidebarContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testSharedCustomerSidebarContainsOnlyTheRequestedPrimaryDestinations(): void
    {
        $source = $this->source('includes/agent-sidebar.php');

        foreach ([
            "'label' => 'Inbox'",
            "'label' => 'My Feed'",
            "'label' => 'My Loyalty Cards'",
            "'label' => 'My Lists'",
            "'label' => 'Design Studio'",
            "'label' => 'New Chat'",
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testRemovedCustomerDestinationsAreNotInTheVisibleNavigationArray(): void
    {
        $source = $this->source('includes/agent-sidebar.php');

        foreach ([
            "'my-quests' => [",
            "'agent_chat' => [",
            "'messages' => [",
            "'store-canvas' => [",
            "'world-canvas' => [",
            "'build' => [",
            "'feed-following' => [",
            "'merchant_crm' => [",
            "'ads-manager' => [",
        ] as $needle) {
            self::assertStringNotContainsString($needle, $source);
        }
    }

    public function testRequestedDestinationsUseTheCorrectRoutes(): void
    {
        $source = $this->source('includes/agent-sidebar.php');

        foreach ([
            "'href' => '/inbox.php'",
            "'href' => '/feed.php'",
            "'href' => '/loyalty-cards.php'",
            "'href' => '/lists.php'",
            "'href' => '/design-studio.php'",
            "'href' => '/agent.php'",
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testMerchantAdminNavigationRemainsIndependent(): void
    {
        $source = $this->source('includes/agent-sidebar.php');

        self::assertStringContainsString("str_starts_with(\$currentSidebarScript, 'merchant-')", $source);
        self::assertStringContainsString("require_once __DIR__ . '/merchant-navigation.php'", $source);
        self::assertStringContainsString('mg_merchant_navigation_sidebar($agentSidebarActive)', $source);
    }

    public function testEveryFeedViewHighlightsTheMainFeedLink(): void
    {
        $sidebar = $this->source('includes/agent-sidebar.php');
        $feed = $this->source('feed.php');

        self::assertStringContainsString("str_starts_with(\$agentSidebarActive, 'feed-')", $sidebar);
        self::assertStringContainsString("\$agent_tab = 'feed-' . \$feedView", $feed);
    }
}
