<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantContactWorkspaceReviewActionsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testEndpointRoutesOwnedWorkspacePayloadBeforeGenericExecution(): void
    {
        $endpoint = (string)file_get_contents($this->root . '/api/merchant/agent-approval-action.php');

        self::assertStringContainsString('merchant-contact-workspace-review-actions.php', $endpoint);
        self::assertStringContainsString('$merchantOwnerId', $endpoint);
        self::assertStringContainsString('mg_contact_workspace_review_is_payload', $endpoint);
        self::assertStringContainsString('mg_contact_workspace_review_item', $endpoint);
        self::assertLessThan(
            strpos($endpoint, 'mg_ai_plan_review_item'),
            strpos($endpoint, 'mg_contact_workspace_review_is_payload')
        );
    }

    public function testFollowupApprovalCreatesCanonicalTaskAndCrmEvent(): void
    {
        $adapter = (string)file_get_contents($this->root . '/includes/ai/merchant-contact-workspace-review-actions.php');
        $followups = (string)file_get_contents($this->root . '/api/merchant/followup-tasks.php');

        self::assertStringContainsString("'crm.followup.created'", $adapter);
        self::assertStringContainsString('\'note\'=>$note', $adapter);
        self::assertStringContainsString('\'due_at\'=>$dueAt', $adapter);
        self::assertStringContainsString("'status'=>'open'", $adapter);
        self::assertStringContainsString('mg_merchant_crm_record_event', $adapter);
        self::assertStringContainsString("ce.event_type='crm.followup.created'", $followups);
    }

    public function testMessageApprovalCreatesEditableOutboxDraftWithoutSending(): void
    {
        $adapter = (string)file_get_contents($this->root . '/includes/ai/merchant-contact-workspace-review-actions.php');
        $messages = (string)file_get_contents($this->root . '/includes/merchant-agent-messages.php');

        self::assertStringContainsString("'crm.agent.message.draft.created'", $adapter);
        self::assertStringContainsString('\'message_draft_id\'=>$messageDraftId', $adapter);
        self::assertStringContainsString('\'draft_body\'=>$body', $adapter);
        self::assertStringContainsString("'send_directly'=>false", $adapter);
        self::assertStringContainsString('Sending still requires the Agent Messages send action', $adapter);
        self::assertStringNotContainsString('send_customer_message(', $adapter);
        self::assertStringContainsString("'crm.agent.message.draft.created'", $messages);
    }

    public function testExecutionIsTransactionalScopedAndAudited(): void
    {
        $adapter = (string)file_get_contents($this->root . '/includes/ai/merchant-contact-workspace-review-actions.php');

        self::assertStringContainsString('merchant_user_id=? AND public_id=?', $adapter);
        self::assertStringContainsString('merged_into_contact_id IS NULL', $adapter);
        self::assertStringContainsString('FOR UPDATE', $adapter);
        self::assertStringContainsString('$pdo->beginTransaction()', $adapter);
        self::assertStringContainsString("UPDATE ai_merchant_plan_items SET status='executed'", $adapter);
        self::assertStringContainsString('$pdo->commit()', $adapter);
        self::assertStringContainsString('$pdo->rollBack()', $adapter);
        self::assertStringContainsString("mg_audit('merchant.contact_workspace_review_approved'", $adapter);
    }
}
