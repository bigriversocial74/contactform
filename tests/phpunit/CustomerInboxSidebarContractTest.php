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

    public function testSharedCustomerSidebarContainsApprovedDestinationsPerRenderedState(): void
    {
        $source = $this->source('includes/personal-agent-sidebar.php');

        foreach (['Inbox', 'My Feed', 'My Loyalty Cards', 'My Lists', 'Design'] as $label) {
            self::assertSame(1, substr_count($source, '<strong>' . $label . '</strong>'), $label);
        }

        $newChatCount = substr_count($source, '<strong>New Chat</strong>');
        self::assertGreaterThanOrEqual(1, $newChatCount);
        self::assertLessThanOrEqual(2, $newChatCount);
        self::assertStringContainsString('data-personal-agent-new-chat', $source);
        self::assertStringContainsString('$personalAgentHref', $source);
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

    public function testAgentModeSwitchAndSuggestionsLiveInSidebarFooter(): void
    {
        $source = $this->source('includes/personal-agent-sidebar.php');
        $css = $this->source('assets/css/personal-agent-chat-history.css');
        $footer = strpos($source, 'class="mg-personal-chat-sidebar-footer"');
        $switch = strpos($source, 'class="mg-agent-footer-mode-switch"');

        self::assertNotFalse($footer);
        self::assertNotFalse($switch);
        self::assertLessThan($switch, $footer);
        self::assertStringContainsString('data-agent-suggestions-open', $source);
        self::assertStringContainsString('data-agent-tools-tab="suggestions"', $source);
        self::assertStringContainsString('data-agent-tools-tab="keywords"', $source);
        self::assertStringContainsString('data-agent-mode-link="personal"', $source);
        self::assertStringContainsString('data-agent-mode-link="merchant"', $source);
        self::assertStringNotContainsString('Scoped to your merchant workspace', $source);
        self::assertStringContainsString('.mg-agent-footer-mode-switch', $css);
        self::assertStringContainsString('.mg-agent-sidebar-tools-modal', $css);
    }

    public function testAgentFooterRoutesFreeAccountsToSubscriptions(): void
    {
        $sidebar = $this->source('includes/personal-agent-sidebar.php');
        $personalPage = $this->source('agent.php');
        $merchantPage = $this->source('merchant-agent-chat.php');

        self::assertStringContainsString('$hasPersonalAgentAccess', $sidebar);
        self::assertStringContainsString('$hasMerchantAgentAccess', $sidebar);
        self::assertStringContainsString('/account-subscriptions.php?agent=personal', $sidebar);
        self::assertStringContainsString('/account-subscriptions.php?agent=merchant', $sidebar);
        self::assertStringContainsString("header('Location: /account-subscriptions.php?agent=personal')", $personalPage);
        self::assertStringContainsString("header('Location: /account-subscriptions.php?agent=merchant')", $merchantPage);
    }

    public function testSubscriptionsPageUsesUniversalInboxSidebar(): void
    {
        $source = $this->source('account-subscriptions.php');

        self::assertStringContainsString("\$agent_sidebar_mode='subscriptions'", $source);
        self::assertStringContainsString("require __DIR__ . '/includes/personal-agent-sidebar.php'", $source);
        self::assertStringNotContainsString("require __DIR__ . '/includes/agent-sidebar.php'", $source);
        self::assertStringContainsString('/assets/css/personal-agent-chat-history.css?v=1.4.0', $source);
        self::assertStringContainsString('/assets/js/personal-agent-chat-history.js?v=1.1.0', $source);
    }

    public function testQuickActionCatalogAndRuntimeRemainExpandable(): void
    {
        $catalog = $this->source('includes/agent-quick-actions.php');
        $runtime = $this->source('assets/js/agent-sidebar-tools.js');

        self::assertStringContainsString('function mg_agent_quick_action_catalog', $catalog);
        self::assertStringContainsString("'keyword'=>'/snapshot'", $catalog);
        self::assertStringContainsString("'keyword'=>'memory'", $catalog);
        self::assertStringContainsString("'keyword'=>'/m'", $catalog);
        self::assertStringContainsString('form.requestSubmit()', $runtime);
        self::assertStringContainsString('data-agent-tools-entitled', $runtime);
    }

    public function testGiftFoldersConsumeTheUnifiedSidebarWithoutInjectingMyLists(): void
    {
        $source = $this->source('includes/gift-center-sidebar.php');

        self::assertStringContainsString("require __DIR__ . '/personal-agent-sidebar.php'", $source);
        self::assertStringNotContainsString('$myListsItem', $source);
        self::assertStringNotContainsString('mg-gift-center-my-lists', $source);
    }

    public function testFeedLoyaltyCardsAndListsUseTheInboxSidebarForSignedInUsers(): void
    {
        $source = $this->source('includes/agent-sidebar.php');

        foreach (['feed.php', 'loyalty-cards.php', 'lists.php'] as $script) {
            self::assertStringContainsString("'{$script}'", $source);
        }

        self::assertStringContainsString('$user !== null', $source);
        self::assertStringContainsString("require __DIR__ . '/personal-agent-sidebar.php'", $source);
        self::assertStringContainsString('/assets/css/personal-agent-chat-history.css?v=1.2.0', $source);
        self::assertStringContainsString('/assets/js/personal-agent-chat-history.js?v=1.2.0', $source);
    }

    public function testTrainingLabIsRemovedAndChatHistoryRemains(): void
    {
        $source = $this->source('includes/personal-agent-sidebar.php');

        self::assertStringNotContainsString('Training Lab', $source);
        self::assertStringNotContainsString('/training-lab.php', $source);
        self::assertStringContainsString('data-personal-agent-thread-groups', $source);
        self::assertStringContainsString('data-agent-suggestions-open', $source);
    }

    public function testSharedHistoryAssetsLoadInGiftFolders(): void
    {
        $source = $this->source('includes/gift-action-center.php');

        self::assertStringContainsString('/assets/css/personal-agent-chat-history.css?v=1.2.0', $source);
        self::assertStringContainsString('/assets/js/personal-agent-chat-history.js?v=1.2.0', $source);
    }

    public function testMerchantAdminNavigationRemainsIndependent(): void
    {
        $source = $this->source('includes/agent-sidebar.php');

        self::assertStringContainsString("str_starts_with(\$currentSidebarScript, 'merchant-')", $source);
        self::assertStringContainsString("require_once __DIR__ . '/merchant-navigation.php'", $source);
        self::assertStringContainsString('mg_merchant_navigation_sidebar($agentSidebarActive)', $source);
    }
}
