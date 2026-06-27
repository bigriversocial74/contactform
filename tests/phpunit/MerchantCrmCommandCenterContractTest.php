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

    public function testCrmCommandCenterLoadsSimpleTabsAndPanels(): void
    {
        $view = $this->read('includes/merchant-crm-view.php');
        $tabs = $this->read('assets/js/merchant-crm-command-center.js');
        $css = $this->read('assets/css/merchant-crm-command-center.css');

        self::assertStringContainsString('/assets/css/merchant-crm-command-center.css', $view);
        self::assertStringContainsString('/assets/js/merchant-crm-command-center.js', $view);
        self::assertStringContainsString('/assets/js/merchant-crm-messages.js', $view);
        foreach (['overview','messages','contacts','campaigns','rewards','stamps','ledger'] as $tab) {
            self::assertStringContainsString('data-crm-tab-target="' . $tab . '"', $view);
            self::assertStringContainsString('data-crm-tab-panel="' . $tab . '"', $view);
        }
        self::assertStringContainsString("bottom:0!important", $css);
        self::assertStringContainsString('overflow-y:hidden', $css);
        self::assertStringContainsString('mg:crm-tab:changed', $tabs);
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
        $messagesJs = $this->read('assets/js/merchant-crm-messages.js');

        self::assertStringContainsString('function mg_crm_message_thread', $endpoint);
        self::assertStringContainsString("'crm:' . (string)$" . "contact['public_id']", $endpoint);
        self::assertStringContainsString('INSERT INTO message_threads', $endpoint);
        self::assertStringContainsString('INSERT INTO message_thread_participants', $endpoint);
        self::assertStringContainsString('INSERT INTO messages', $endpoint);
        self::assertStringContainsString('thread_id', $endpoint);
        self::assertStringContainsString('/api/merchant/crm-message.php', $crmJs);
        self::assertStringContainsString('mg:crm-messages:refresh', $crmJs);
        self::assertStringContainsString('mg:crm-messages:refresh', $messagesJs);
        self::assertStringContainsString('openThread(threadId)', $messagesJs);
    }

    public function testRewardInviteOperationsStayInRewardsTab(): void
    {
        $view = $this->read('includes/merchant-crm-view.php');
        $js = $this->read('assets/js/merchant-crm-reward-invite-operations.js');

        self::assertStringContainsString('data-crm-reward-invite-ops-host', $view);
        self::assertStringContainsString('data-crm-reward-invite-ops-host', $js);
        self::assertStringContainsString("e.detail.tab==='rewards'", $js);
        self::assertStringNotContainsString("q('[data-merchant-crm-messages]')||q('[data-merchant-crm-app]')", $js);
    }
}
