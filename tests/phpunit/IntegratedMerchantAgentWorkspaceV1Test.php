<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IntegratedMerchantAgentWorkspaceV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSlashMerchantCommandBelongsOnlyToPersonalAgent(): void
    {
        $personal = file_get_contents($this->root . '/assets/js/agent-merchant-handoff.js');
        $receiver = file_get_contents($this->root . '/assets/js/merchant-agent-handoff-receiver.js');

        self::assertIsString($personal);
        self::assertIsString($receiver);
        self::assertStringContainsString('data-personal-gifting-agent', $personal);
        self::assertStringContainsString('/(?:m|merchant)', $personal);
        self::assertStringNotContainsString('data-merchant-agent-chat', $personal);
        self::assertStringContainsString('data-merchant-agent-chat', $receiver);
        self::assertStringNotContainsString('merchantCommand', $receiver);
        self::assertStringNotContainsString('/(?:m|merchant)', $receiver);
    }

    public function testMerchantHandoffKeepsPromptOutOfTheUrl(): void
    {
        $personal = file_get_contents($this->root . '/assets/js/agent-merchant-handoff.js');
        $receiver = file_get_contents($this->root . '/assets/js/merchant-agent-handoff-receiver.js');

        self::assertIsString($personal);
        self::assertIsString($receiver);
        self::assertStringContainsString('sessionStorage.setItem', $personal);
        self::assertStringContainsString('sessionStorage.getItem', $receiver);
        self::assertStringContainsString('sessionStorage.removeItem', $receiver);
        self::assertStringNotContainsString("searchParams.set('prompt'", $personal);
        self::assertStringNotContainsString("params.get('prompt')", $receiver);
    }

    public function testMerchantAgentUsesSharedInboxSidebarAndAgentShell(): void
    {
        $page = file_get_contents($this->root . '/merchant-agent-chat.php');
        $sidebar = file_get_contents($this->root . '/includes/personal-agent-sidebar.php');

        self::assertIsString($page);
        self::assertIsString($sidebar);
        self::assertStringContainsString("\$page_section = 'agent'", $page);
        self::assertStringContainsString("\$header_mode = 'agent'", $page);
        self::assertStringContainsString("require __DIR__ . '/includes/personal-agent-sidebar.php'", $page);
        self::assertStringNotContainsString("require __DIR__ . '/includes/agent-sidebar.php'", $page);
        self::assertStringContainsString('data-agent-sidebar-mode=', $sidebar);
        self::assertStringContainsString('data-merchant-agent-thread-groups', $sidebar);
        self::assertStringContainsString('data-merchant-agent-new-chat', $sidebar);
    }

    public function testMerchantWorkspaceKeepsPermissionAndApprovalBoundaries(): void
    {
        $page = file_get_contents($this->root . '/merchant-agent-chat.php');
        $api = file_get_contents($this->root . '/api/ai/merchant-agent-chat.php');
        $view = file_get_contents($this->root . '/includes/merchant-agent-chat-view.php');

        self::assertIsString($page);
        self::assertIsString($api);
        self::assertIsString($view);
        self::assertStringContainsString("mg_has_permission('merchant.ai.plan')", $page);
        self::assertStringContainsString("mg_has_permission('merchant.ai.review')", $page);
        self::assertStringContainsString("mg_merchant_require_permission('merchant.ai.review')", $api);
        self::assertStringContainsString("mg_merchant_require_permission(\$action === 'send_message' ? 'merchant.ai.plan' : 'merchant.ai.review')", $api);
        self::assertStringContainsString('Business data only', $view);
        self::assertStringContainsString('Approval-first actions', $view);
    }

    public function testMerchantAgentMatchesChatFirstLayoutAndUsesControlsDrawer(): void
    {
        $view = file_get_contents($this->root . '/includes/merchant-agent-chat-view.php');
        $css = file_get_contents($this->root . '/assets/css/merchant-agent-integrated-workspace.css');
        $drawer = file_get_contents($this->root . '/assets/js/merchant-agent-chat-mobile.js');

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertIsString($drawer);
        self::assertStringContainsString('mg-merchant-agent-chat-stream', $view);
        self::assertStringContainsString('mg-merchant-agent-composer', $view);
        self::assertStringContainsString('data-agent-chat-drawer-open', $view);
        self::assertStringContainsString('height:calc(100svh - var(--mg-app-header))', $css);
        self::assertStringContainsString('bottom:16px!important', $css);
        self::assertStringContainsString('transform:translateX(105%)', $css);
        self::assertStringContainsString('var shouldOpen = !!isOpen', $drawer);
    }
}
