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

    public function testSidebarAgentSwitchLivesInFooterWithSuggestions(): void
    {
        $sidebar = file_get_contents($this->root . '/includes/personal-agent-sidebar.php');
        $css = file_get_contents($this->root . '/assets/css/personal-agent-chat-history.css');
        self::assertIsString($sidebar);
        self::assertIsString($css);
        self::assertStringNotContainsString('class="mg-agent-mode-switch"', $sidebar);
        self::assertStringNotContainsString('mg-agent-mode-options', $sidebar);
        $footer = strpos($sidebar, 'class="mg-personal-chat-sidebar-footer"');
        $switch = strpos($sidebar, 'class="mg-agent-footer-mode-switch"');
        self::assertNotFalse($footer);
        self::assertNotFalse($switch);
        self::assertLessThan($switch, $footer);
        self::assertStringContainsString('data-agent-footer-mode-switch', $sidebar);
        self::assertStringContainsString('data-agent-mode-link="personal"', $sidebar);
        self::assertStringContainsString('data-agent-mode-link="merchant"', $sidebar);
        self::assertStringContainsString('data-agent-suggestions-open', $sidebar);
        self::assertStringContainsString('data-agent-tools-tab="suggestions"', $sidebar);
        self::assertStringContainsString('data-agent-tools-tab="keywords"', $sidebar);
        self::assertStringNotContainsString('Scoped to your merchant workspace', $sidebar);
        self::assertStringContainsString('.mg-agent-footer-mode-switch', $css);
        self::assertStringContainsString('.mg-agent-sidebar-tools-modal', $css);
    }

    public function testQuickActionCatalogContainsCurrentCommandsAndOneClickRuntime(): void
    {
        $catalog = file_get_contents($this->root . '/includes/agent-quick-actions.php');
        $runtime = file_get_contents($this->root . '/assets/js/agent-sidebar-tools.js');
        self::assertIsString($catalog);
        self::assertIsString($runtime);
        self::assertStringContainsString('function mg_agent_quick_action_catalog', $catalog);
        foreach (["'keyword'=>'/snapshot'", "'keyword'=>'memory'", "'keyword'=>'contact count'", "'keyword'=>'saved opportunities'", "'keyword'=>'/m'", "'keyword'=>'review queue'"] as $marker) self::assertStringContainsString($marker, $catalog);
        self::assertStringContainsString("event.target.closest('[data-agent-suggestion-prompt]')", $runtime);
        self::assertStringContainsString("event.target.closest('[data-agent-keyword-prompt]')", $runtime);
        self::assertStringContainsString('form.requestSubmit()', $runtime);
        self::assertStringContainsString('sessionStorage.setItem', $runtime);
        self::assertStringContainsString('data-agent-tools-entitled', $runtime);
        self::assertStringContainsString('subscriptionsUrl', $runtime);
    }

    public function testSystematicPersonalAccessAndSubscriptionSidebarRemainCompatible(): void
    {
        $personalPage = file_get_contents($this->root . '/agent.php');
        $merchantPage = file_get_contents($this->root . '/merchant-agent-chat.php');
        $subscriptionsPage = file_get_contents($this->root . '/account-subscriptions.php');
        $sidebar = file_get_contents($this->root . '/includes/personal-agent-sidebar.php');
        self::assertIsString($personalPage);
        self::assertIsString($merchantPage);
        self::assertIsString($subscriptionsPage);
        self::assertIsString($sidebar);
        self::assertStringNotContainsString('/account-subscriptions.php?agent=personal', $personalPage);
        self::assertStringContainsString("\$header_mode = 'agent'", $personalPage);
        self::assertStringContainsString("header('Location: /account-subscriptions.php?agent=merchant')", $merchantPage);
        self::assertStringContainsString('/account-subscriptions.php?agent=personal', $sidebar);
        self::assertStringContainsString('/account-subscriptions.php?agent=merchant', $sidebar);
        self::assertStringContainsString("\$agent_sidebar_mode='subscriptions'", $subscriptionsPage);
        self::assertStringContainsString("require __DIR__ . '/includes/personal-agent-sidebar.php'", $subscriptionsPage);
        self::assertStringNotContainsString("require __DIR__ . '/includes/agent-sidebar.php'", $subscriptionsPage);
        self::assertStringContainsString('/assets/css/personal-agent-chat-history.css?v=1.4.0', $subscriptionsPage);
    }

    public function testPersonalAgentConversationOwnsFullCanvas(): void
    {
        $page = file_get_contents($this->root . '/agent.php');
        $view = file_get_contents($this->root . '/includes/personal-agent/workspace-dashboard.php');
        $css = file_get_contents($this->root . '/assets/css/personal-agent-full-canvas.css');
        self::assertIsString($page);
        self::assertIsString($view);
        self::assertIsString($css);
        self::assertStringContainsString('/assets/css/personal-agent-full-canvas.css?v=1.0.0', $page);
        self::assertStringContainsString('/assets/css/personal-agent-chat-history.css?v=1.4.0', $page);
        self::assertStringContainsString('mg-personal-agent-chat-view mg-personal-agent-chat-stream', $view);
        self::assertStringNotContainsString('<div class="mg-personal-agent-chat-stream">', $view);
        self::assertStringContainsString('width:100%!important', $css);
        self::assertStringContainsString('background:transparent!important', $css);
        self::assertStringContainsString('box-shadow:none!important', $css);
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
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
        self::assertStringContainsString('mg_merchant_agent_require_owner_permission($user,', $api);
        self::assertStringContainsString('Business data only', $view);
        self::assertStringContainsString('Approval-first actions', $view);
    }

    public function testSnapshotKeywordUsesDatabaseOnlyPath(): void
    {
        $api = file_get_contents($this->root . '/api/ai/merchant-agent-chat.php');
        $service = file_get_contents($this->root . '/includes/ai/merchant-agent-snapshot.php');
        $runtime = file_get_contents($this->root . '/assets/js/merchant-agent-chat.js');
        $css = file_get_contents($this->root . '/assets/css/merchant-agent-snapshot.css');
        self::assertIsString($api);
        self::assertIsString($service);
        self::assertIsString($runtime);
        self::assertIsString($css);
        self::assertStringContainsString('mg_merchant_snapshot_is_keyword', $api);
        self::assertStringContainsString('mg_merchant_snapshot_chat_response', $api);
        self::assertLessThan(strpos($api, 'mg_ai_chat_send_with_memory'), strpos($api, 'mg_merchant_snapshot_chat_response'));
        self::assertStringContainsString("'external_ai_called' => false", $service);
        self::assertStringContainsString("'customer_details_included' => false", $service);
        self::assertStringContainsString("'model' => 'database-snapshot-v1'", $service);
        self::assertStringContainsString("action: snapshotRequest ? 'snapshot' : 'send_message'", $runtime);
        self::assertStringContainsString('Promise.all([request, delay(650)])', $runtime);
        self::assertStringContainsString('mg-agent-snapshot-thinking', $runtime);
        self::assertStringContainsString('@keyframes mgMerchantSnapshotPulse', $css);
    }

    public function testSnapshotCoversRequestedMerchantSignals(): void
    {
        $service = file_get_contents($this->root . '/includes/ai/merchant-agent-snapshot.php');
        self::assertIsString($service);
        foreach (['pppm_issuance_requests','social_follows','feed_post_comments','campaign_contacts','merchant_crm_contacts','merchant_crm_contact_events','microgift_claim_escalations','ai_merchant_plan_items'] as $table) self::assertStringContainsString($table, $service);
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
