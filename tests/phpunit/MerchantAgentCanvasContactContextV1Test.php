<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentCanvasContactContextV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testMerchantCanvasMatchesPersonalConversationStructure(): void
    {
        $page = file_get_contents($this->root . '/merchant-agent-chat.php');
        $view = file_get_contents($this->root . '/includes/merchant-agent-chat-view.php');
        $css = file_get_contents($this->root . '/assets/css/merchant-agent-personal-canvas-parity-v1.css');
        self::assertIsString($page);
        self::assertIsString($view);
        self::assertIsString($css);
        self::assertStringContainsString('merchant-agent-personal-canvas-parity-v1.css?v=1.0.0', $page);
        self::assertStringContainsString('mg-personal-agent-main mg-merchant-agent-main', $view);
        self::assertStringContainsString('mg-personal-agent-chat-view mg-merchant-agent-chat-view', $view);
        self::assertStringContainsString('background:transparent!important', $css);
        self::assertStringContainsString('.mg-agent-chat-empty', $css);
    }

    public function testMerchantSidebarUsesGroupedRowsWithRemovalControl(): void

    {
        $script = file_get_contents($this->root . '/assets/js/merchant-agent-sidebar-history.js');
        self::assertIsString($script);
        foreach (['data-merchant-agent-thread-groups','mg-personal-chat-row','mg-personal-chat-open','mg-personal-chat-delete','data-merchant-agent-delete-thread'] as $marker) self::assertStringContainsString($marker,$script);
        self::assertStringContainsString("action: 'delete_thread'",$script);
        self::assertStringContainsString('window.confirm',$script);



    }

    public function testThreadRemovalIsMerchantScoped(): void
    {
        $source = file_get_contents($this->root . '/includes/ai/merchant-agent-thread-delete.php');
        self::assertIsString($source);
        self::assertStringContainsString('WHERE merchant_user_id=? AND public_id=? LIMIT 1', $source);
        self::assertStringContainsString('merchant_agent_insight_snapshots', $source);
        self::assertStringContainsString('merchant.agent_chat.user', $source);
        self::assertStringContainsString("JSON_EXTRACT(event_context_json,'$.thread_public_id')", $source);
        self::assertStringContainsString('mg_agent_create_thread', $source);
    }

    public function testContactAwarePromptsUseExactWorkspaceContacts(): void
    {
        $api = file_get_contents($this->root . '/api/ai/merchant-agent-chat.php');
        $context = file_get_contents($this->root . '/includes/ai/merchant-agent-crm-contact-context.php');
        self::assertIsString($api);
        self::assertIsString($context);
        self::assertStringContainsString('$contactAware', $api);
        self::assertStringContainsString("mg_merchant_require_permission('merchant.campaigns.view')", $api);
        self::assertStringContainsString("['_merchant_owner_id']", $api);
        self::assertStringContainsString('LOWER(pp.slug)=?', $context);
        self::assertStringContainsString("LOWER(REPLACE(public_id,'-',''))", $context);
        self::assertStringContainsString('merchant_user_id=?', $context);
        self::assertStringContainsString('merchant_crm_contact_events', $context);
        self::assertStringContainsString('merchant_crm_contact_campaigns', $context);
    }

    public function testContactAgentRemainsAdvisoryAndNoInvention(): void
    {
        $source = file_get_contents($this->root . '/includes/ai/merchant-agent-crm-contact-chat.php');
        self::assertIsString($source);
        self::assertStringContainsString('Never infer or invent a contact', $source);
        self::assertStringContainsString('Never claim a message was sent', $source);
        self::assertStringContainsString('Never issue a reward', $source);
        self::assertStringContainsString('Never execute directly', $source);
        self::assertStringContainsString('mg_merchant_agent_crm_unresolved_response', $source);
        self::assertStringContainsString('mg_ai_chat_record_message', $source);
        self::assertStringContainsString('mg_ai_chat_auto_bridge_cards', $source);
    }

    public function testNoNewSchemaIsRequired(): void
    {
        $paths = [
            '/includes/ai/merchant-agent-crm-contact-context.php',
            '/includes/ai/merchant-agent-crm-contact-chat.php',
            '/includes/ai/merchant-agent-thread-delete.php',
        ];
        foreach ($paths as $path) {
            $source = file_get_contents($this->root . $path);
            self::assertIsString($source);
            self::assertStringNotContainsString('CREATE TABLE', $source);
            self::assertStringNotContainsString('ALTER TABLE', $source);
        }
    }
}
