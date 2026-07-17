<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantContactActionCenterV11Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testNotesAreMerchantScopedInternalAndAudited(): void
    {
        $service = (string)file_get_contents($this->root . '/includes/ai/merchant-agent-contact-workspace-v1-1.php');
        $api = (string)file_get_contents($this->root . '/api/ai/merchant-agent-chat.php');

        self::assertStringContainsString('merchant_user_id=? AND public_id=?', $service);
        self::assertStringContainsString('merged_into_contact_id IS NULL', $service);
        self::assertStringContainsString('INSERT INTO merchant_crm_notes', $service);
        self::assertStringContainsString("'merchant_internal'", $service);
        self::assertStringContainsString("'crm.note.added'", $service);
        self::assertStringContainsString("'contact_note' => 'merchant.campaigns.manage'", $api);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
    }

    public function testMessageAndFollowupBuildersOnlyCreateReviewItems(): void
    {
        $service = (string)file_get_contents($this->root . '/includes/ai/merchant-agent-contact-workspace-v1-1.php');
        $api = (string)file_get_contents($this->root . '/api/ai/merchant-agent-chat.php');
        $runtime = (string)file_get_contents($this->root . '/assets/js/merchant-agent-contact-workspace-v1-1.js');

        self::assertStringContainsString("'create_message_draft'", $service);
        self::assertStringContainsString("'create_crm_followup_task'", $service);
        self::assertStringContainsString("'send_directly'=>false", $service);
        self::assertStringContainsString("'create_directly'=>false", $service);
        self::assertStringContainsString('mg_ai_chat_bridge_to_review', $service);
        self::assertStringContainsString('mg_agent_autonomy_require_for_merchant($pdo, $actorId, \'review_queue\'', $api);
        self::assertStringContainsString('mg_agent_autonomy_require_for_merchant($pdo, $actorId, \'messages\'', $api);
        self::assertStringNotContainsString('/api/merchant/crm-message.php', $runtime);
        self::assertStringNotContainsString('/api/merchant/crm-followup.php', $runtime);
    }

    public function testDraftSubmissionIsIdempotentAndStatusIsContactScoped(): void
    {
        $service = (string)file_get_contents($this->root . '/includes/ai/merchant-agent-contact-workspace-v1-1.php');

        self::assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.idempotency_key'))=?", $service);
        self::assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.crm_contact_id'))=?", $service);
        self::assertStringContainsString("JSON_UNQUOTE(JSON_EXTRACT(i.suggested_payload_json,'$.source'))='merchant_contact_action_center_v1_1'", $service);
        self::assertStringContainsString('p.merchant_user_id=?', $service);
        self::assertStringContainsString("'executed'=>'Executed'", $service);
        self::assertStringContainsString("'rejected'=>'Rejected'", $service);
    }

    public function testWorkspaceUiProvidesRequestedEditorsAndFiltersWithoutNestedForms(): void
    {
        $view = (string)file_get_contents($this->root . '/includes/merchant-agent-chat-view.php');
        $page = (string)file_get_contents($this->root . '/merchant-agent-chat.php');
        $css = (string)file_get_contents($this->root . '/assets/css/merchant-agent-contact-workspace-v1-1.css');

        self::assertSame(1, substr_count($view, '<form'));
        foreach (['timeline','notes','followup','draft','review'] as $tab) {
            self::assertStringContainsString('data-contact-workspace-tab="' . $tab . '"', $view);
        }
        foreach (['purchases','rewards','messages','campaigns','tasks_notes'] as $filter) {
            self::assertStringContainsString('data-contact-timeline-filter="' . $filter . '"', $view);
        }
        self::assertStringContainsString('merchant-agent-contact-workspace-v1-1.css?v=1.1.0', $page);
        self::assertStringContainsString('merchant-agent-contact-workspace-v1-1.js?v=1.1.0', $page);
        self::assertStringContainsString('@media(max-width:820px)', $css);
        self::assertStringContainsString('@media(max-width:560px)', $css);
    }
}
