<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantContactActionCenterV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testThreadSelectionIsPersistentScopedAndCleanedUp(): void
    {
        $service = (string)file_get_contents($this->root . '/includes/ai/merchant-agent-contact-action-center.php');
        $api = (string)file_get_contents($this->root . '/api/ai/merchant-agent-chat.php');
        $delete = (string)file_get_contents($this->root . '/includes/ai/merchant-agent-thread-delete.php');

        self::assertStringContainsString('merchant.agent_chat.contact_selected', $service);
        self::assertStringContainsString('merchant.agent_chat.contact_cleared', $service);
        self::assertStringContainsString('JSON_VALID(event_context_json)=1', $service);
        self::assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.thread_public_id'))=?", $service);
        self::assertStringContainsString('merchant_user_id=?', $service);
        self::assertStringContainsString("$action === 'clear_thread'", $api);
        self::assertStringContainsString('mg_merchant_contact_action_center_record_selection($pdo, $actorId, $targetThreadId, null)', $api);
        self::assertStringContainsString('merchant.agent_chat.contact_selected', $delete);
        self::assertStringContainsString('merchant.agent_chat.contact_cleared', $delete);
    }

    public function testPromptContextIsSanitizedAndActionsRemainReviewOnly(): void
    {
        $service = (string)file_get_contents($this->root . '/includes/ai/merchant-agent-contact-action-center.php');
        $chat = (string)file_get_contents($this->root . '/includes/ai/merchant-agent-crm-contact-chat.php');
        $runtime = (string)file_get_contents($this->root . '/assets/js/merchant-agent-contact-action-center.js');

        self::assertStringContainsString("'send_directly'=>false", $service);
        self::assertStringContainsString("'issue_reward_directly'=>false", $service);
        self::assertStringContainsString('selected_contact_action_center', $chat);
        self::assertStringContainsString("unset($contact['id'])", $chat);
        self::assertStringContainsString("unset($item['id'], $item['action_url'], $item['thread_url'], $item['campaign_id'])", $chat);
        self::assertStringContainsString("$payload['approval_required'] = true", $chat);
        self::assertStringContainsString("if ($approvalMode === 'review_queue') mg_ai_chat_auto_bridge_cards", $chat);
        self::assertStringContainsString('form.requestSubmit()', $runtime);
    }

    public function testPublicUiContractLoadsAcrossDesktopAndMobile(): void
    {
        $page = (string)file_get_contents($this->root . '/merchant-agent-chat.php');
        $view = (string)file_get_contents($this->root . '/includes/merchant-agent-chat-view.php');
        $css = (string)file_get_contents($this->root . '/assets/css/merchant-agent-contact-action-center.css');

        self::assertStringContainsString('merchant-agent-contact-action-center.css?v=1.0.0', $page);
        self::assertStringContainsString('merchant-agent-contact-action-center.js?v=1.0.0', $page);
        self::assertStringContainsString('merchant-agent-contact-action-center-select-bridge.js?v=1.0.0', $page);
        self::assertStringContainsString('data-merchant-contact-action-center', $view);
        self::assertStringContainsString('data-contact-center-actions', $view);
        self::assertStringContainsString('@media(max-width:820px)', $css);
        self::assertStringContainsString('@media(max-width:560px)', $css);
    }
}
