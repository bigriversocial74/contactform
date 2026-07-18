<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmCommandCenterContractTest extends TestCase
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

    public function testCrmCommandCenterLoadsCurrentTabsAndPanels(): void

    {
        $page = $this->read('merchant-crm.php');
        $view = $this->read('includes/merchant-crm-view.php');
        $css = $this->read('assets/css/merchant-crm-contacts-only.css');
        foreach(['/assets/css/merchant-crm-command-center.css','/assets/css/merchant-crm-contacts-only.css?v=1.1.0','/assets/js/merchant-crm.js','/assets/js/merchant-crm-directory.js?v=1.0.0'] as $needle) self::assertStringContainsString($needle,$page);
        foreach(['data-merchant-crm-shell','data-crm-desktop-hero','data-crm-desktop-directory','data-crm-mobile-overview','data-merchant-crm-table'] as $needle) self::assertStringContainsString($needle,$view);
        self::assertStringContainsString('Four visible columns',$css);
        self::assertStringNotContainsString('data-crm-tab-target',$view);



    }

    public function testContactActionsAreCompactAndHorizontal(): void
    {
        $js = $this->read('assets/js/merchant-crm.js');
        $css = $this->read('assets/css/merchant-crm-command-center.css');

        self::assertStringContainsString('mg-crm-row-actions', $js);
        self::assertStringContainsString('data-view-timeline', $js);
        self::assertStringContainsString('data-crm-message', $js);
        self::assertStringContainsString('data-crm-gift', $js);
        self::assertStringContainsString('flex-wrap:nowrap', $css);
        self::assertStringContainsString('height:28px', $css);
    }

    public function testCreateMessageCreatesActiveCrmThreadAndRefreshesMessages(): void
    {
        $endpoint = $this->read('api/merchant/crm-message.php');
        $crmJs = $this->read('assets/js/merchant-crm.js');
        $tabsJs = $this->read('assets/js/merchant-crm-tabs.js');

        self::assertStringContainsString('function mg_crm_message_thread', $endpoint);
        self::assertStringContainsString("'crm:' . (string)$" . "contact['public_id']", $endpoint);
        self::assertStringContainsString('INSERT INTO message_threads', $endpoint);
        self::assertStringContainsString('INSERT IGNORE INTO message_thread_participants', $endpoint);
        self::assertStringContainsString('INSERT INTO messages', $endpoint);
        self::assertStringContainsString('thread_id', $endpoint);
        self::assertStringContainsString('/api/merchant/crm-message.php', $crmJs . $tabsJs);
        self::assertStringContainsString('mg:notifications:refresh', $tabsJs);
        self::assertStringContainsString('thread_id', $crmJs . $tabsJs);
    }

    public function testRewardInviteOperationsStayInRewardsTab(): void

    {
        $page = $this->read('merchant-crm.php');
        $view = $this->read('includes/merchant-crm-view.php');
        $js = $this->read('assets/js/merchant-crm-reward-invite-operations.js');
        self::assertStringContainsString('/assets/js/merchant-crm-reward-invite-operations.js',$page);
        self::assertStringContainsString('data-merchant-crm-table',$view);
        self::assertStringContainsString('data-crm-reward-invite-ops-host',$js);
        self::assertStringContainsString('if(!host)return',$js);
        self::assertStringNotContainsString("e.detail.tab==='rewards'",$js);



    }
}
