<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CustomerInboxSidebarContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testMyQuestsIsPartOfTheSharedCustomerSidebar(): void
    {
        $source=$this->source('includes/agent-sidebar.php');

        foreach([
            "'my-quests' => [",
            "'label' => 'My Quests'",
            "'detail' => 'Track loyalty quest progress'",
            "'href' => '/my-quests.php'",
            "'visible' => \$user !== null",
            "\$agentSidebarActive === 'my-quests'",
            "\$agentSidebarActive === 'loyalty_quests'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testRequestedCustomerPagesUseTheInboxSidebarContract(): void
    {
        $pages=[
            'my-quests.php'=>'$use_inbox_sidebar = true;',
            'account-subscriptions.php'=>'$use_inbox_sidebar = true;',
            'notifications.php'=>'$use_inbox_sidebar = true;',
            'feed.php'=>'$use_inbox_sidebar = true;',
            'account.php'=>'$use_inbox_sidebar = basename(',
        ];

        foreach($pages as $path=>$marker){
            $source=$this->source($path);
            self::assertStringContainsString($marker,$source,$path);
            self::assertStringContainsString("includes/agent-sidebar.php",$source,$path);
        }
    }

    public function testCentralRouteFallbackCoversAllRequestedPages(): void
    {
        $source=$this->source('includes/agent-sidebar.php');

        foreach([
            "'my-quests.php'",
            "'account-subscriptions.php'",
            "'notifications.php'",
            "'feed.php'",
            "'account.php'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringContainsString(
            'if (!$isMerchantAdminSidebar && ($useInboxSidebar || in_array($agentSidebarActive, $reducedInboxSidebarPages, true)))',
            $source
        );
    }

    public function testMerchantAdminNavigationCannotBeReplacedByCustomerInboxMode(): void
    {
        $source=$this->source('includes/agent-sidebar.php');

        self::assertStringContainsString("str_starts_with(\$currentSidebarScript, 'merchant-')",$source);
        self::assertStringContainsString("require_once __DIR__ . '/merchant-navigation.php'",$source);
        self::assertStringContainsString('mg_merchant_navigation_sidebar($agentSidebarActive)',$source);
        self::assertStringContainsString('!$isMerchantAdminSidebar',$source);
    }

    public function testEveryFeedViewHighlightsTheMainFeedLink(): void
    {
        $sidebar=$this->source('includes/agent-sidebar.php');
        $feed=$this->source('feed.php');

        self::assertStringContainsString("str_starts_with(\$agentSidebarActive, 'feed-')",$sidebar);
        self::assertStringContainsString("\$agent_tab = 'feed-' . \$feedView",$feed);
    }

    public function testCompatibilityCustomerNavigationAlsoContainsMyQuests(): void
    {
        $source=$this->source('includes/account-sidebar.php');
        self::assertStringContainsString("'my-quests' => ['Gifts', 'My Quests', 'Loyalty quest progress and rewards', '/my-quests.php']",$source);
    }

    public function testDirectAccountPageDoesNotForceWrapperRoutesIntoInboxMode(): void
    {
        $source=$this->source('account.php');
        self::assertStringContainsString("basename((string) (\$_SERVER['SCRIPT_NAME'] ?? '')) === 'account.php'",$source);
        self::assertStringNotContainsString('$use_inbox_sidebar = true;',$source);
    }
}
