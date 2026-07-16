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

    public function testSharedCustomerSidebarContainsOnlyOneOfEachRequestedDestination(): void
    {
        $source = $this->source('includes/personal-agent-sidebar.php');

        foreach (['Inbox', 'My Feed', 'My Loyalty Cards', 'My Lists', 'New Chat', 'Design'] as $label) {
            self::assertSame(1, substr_count($source, '<strong>' . $label . '</strong>'), $label);
        }
    }

    public function testRequestedDestinationsUseTheCorrectRoutesAndActions(): void
    {
        $source = $this->source('includes/personal-agent-sidebar.php');

        foreach ([
            'href="/inbox.php"',
            'href="/feed.php"',
            'href="/loyalty-cards.php"',
            'href="/lists.php"',
            'data-personal-agent-new-chat',
            'href="/design-studio.php"',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testGiftFoldersConsumeTheUnifiedSidebarWithoutInjectingMyLists(): void
    {
        $source = $this->source('includes/gift-center-sidebar.php');

        self::assertStringContainsString("require __DIR__ . '/personal-agent-sidebar.php'", $source);
        self::assertStringNotContainsString('$myListsItem', $source);
        self::assertStringNotContainsString('mg-gift-center-my-lists', $source);
    }

    public function testTrainingLabIsRemovedAndChatHistoryRemains(): void
    {
        $source = $this->source('includes/personal-agent-sidebar.php');

        self::assertStringNotContainsString('Training Lab', $source);
        self::assertStringNotContainsString('/training-lab.php', $source);
        self::assertStringContainsString('data-personal-agent-thread-groups', $source);
        self::assertStringContainsString('Private to your account', $source);
    }

    public function testSharedHistoryAssetsLoadInGiftFolders(): void
    {
        $source = $this->source('includes/gift-action-center.php');

        self::assertStringContainsString('/assets/css/personal-agent-chat-history.css?v=1.2.0', $source);
        self::assertStringContainsString('/assets/js/personal-agent-chat-history.js?v=1.1.0', $source);
    }

    public function testMerchantAdminNavigationRemainsIndependent(): void
    {
        $source = $this->source('includes/agent-sidebar.php');

        self::assertStringContainsString("str_starts_with(\$currentSidebarScript, 'merchant-')", $source);
        self::assertStringContainsString("require_once __DIR__ . '/merchant-navigation.php'", $source);
        self::assertStringContainsString('mg_merchant_navigation_sidebar($agentSidebarActive)', $source);
    }
}
