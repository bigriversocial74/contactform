<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentOwnerAiCreditSeparationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function file(string $path): string
    {
        return (string)file_get_contents($this->root . '/' . ltrim($path, '/'));
    }

    public function testMerchantAgentWorkspaceIsOwnerAndPackageGatedButNotAiPermissionGated(): void
    {
        $page = $this->file('merchant-agent-chat.php');
        self::assertStringContainsString("header('Location: /account-subscriptions.php?agent=merchant')", $page);
        self::assertStringContainsString('mg_merchant_agent_owner_context', $page);
        self::assertStringContainsString('$merchantAgentAllowed = $hasMerchantAccess && $isMerchantOwner', $page);
        self::assertStringContainsString("require __DIR__ . '/includes/merchant-agent-chat-view.php'", $page);
        self::assertStringNotContainsString('$hasMerchantPlanPermission', $page);
        self::assertStringNotContainsString('$hasMerchantReviewPermission', $page);
        self::assertStringContainsString('Database and systematic tools remain available.', $page);
    }

    public function testStrictAiPackageEligibilityRejectsInactiveStatuses(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-credit-response.php');
        self::assertStringContainsString("['free','expired','past_due','paused','canceled','incomplete','pending_admin_review']", $helper);
        self::assertStringContainsString("!empty(\$context['is_complimentary'])", $helper);
        self::assertStringContainsString("\$status === 'admin'", $helper);
        self::assertStringContainsString("'scope'=>'ai_subscription_required'", $helper);
        self::assertStringContainsString('mg_merchant_agent_owner_context', $helper);
        self::assertStringContainsString("'workspace_subscription'", $helper);
    }

    public function testSystematicActionsAreSeparatedFromExternalAiGeneration(): void
    {
        $endpoint = $this->file('api/ai/merchant-agent-chat.php');
        $begin = strpos($endpoint, 'mg_merchant_agent_ai_begin_call');
        self::assertNotFalse($begin);
        foreach (["if (\$action === 'crm_search')", "if (\$action === 'select_contact')", "if (\$action === 'clear_contact')", "if (\$action === 'contact_note')", "if (\$action === 'snapshot'", "if (\$action === 'save_agent_profile')", "if (\$action === 'save_memory_profile')", "if (\$action === 'create_thread')"] as $needle) {
            $position = strpos($endpoint, $needle);
            self::assertNotFalse($position, $needle);
            self::assertLessThan($begin, $position, $needle . ' must finish before the AI call context begins.');
        }
        self::assertStringContainsString("mg_merchant_agent_require_owner_permission(\$user, 'merchant.campaigns.view')", $endpoint);
        self::assertStringContainsString("mg_merchant_agent_require_owner_permission(\$user, 'merchant.campaigns.manage')", $endpoint);
    }

    public function testAiCallsRequirePlanPermissionPreflightAndActualDebit(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-credit-response.php');
        $client = $this->file('includes/ai/anthropic-client.php');
        self::assertStringContainsString("mg_merchant_agent_user_has_permission(\$user, 'merchant.ai.plan')", $helper);
        self::assertStringContainsString('mg_ai_credit_preflight(', $helper);
        self::assertStringContainsString('mg_ai_credit_consume(', $helper);
        self::assertStringContainsString("\$usage['input_tokens']", $helper);
        self::assertStringContainsString("\$usage['output_tokens']", $helper);
        self::assertStringContainsString("'merchant_agent.ai_credit_debit_failed'", $helper);
        self::assertStringContainsString("'ai_tokens_used'=>\$tokens", $helper);
        self::assertStringContainsString("'ai_response_reference'=>\$responseId", $helper);
        self::assertStringContainsString("'ai_source'=>(string)\$call['source_type']", $helper);
        self::assertStringContainsString('mg_merchant_agent_ai_before_anthropic_call($payload)', $client);
        self::assertStringContainsString('mg_merchant_agent_ai_after_anthropic_call($payload, $decoded)', $client);
        self::assertLessThan(
            strpos($client, "curl_init('https://api.anthropic.com/v1/messages')"),
            strpos($client, 'mg_merchant_agent_ai_before_anthropic_call($payload)')
        );
    }

    public function testReviewPermissionControlsExistingReviewRecordsWithoutCredits(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-credit-response.php');
        $chat = $this->file('api/ai/merchant-agent-chat.php');
        $plan = $this->file('api/ai/merchant-agent-plan.php');
        self::assertStringContainsString("'merchant.ai.review'=>(bool)\$status['review_access']", $helper);
        self::assertStringContainsString("\$state['overview']['review_permission_required'] = true", $helper);
        self::assertStringContainsString("mg_merchant_agent_require_owner_permission(\$user, 'merchant.ai.review')", $chat);
        self::assertStringContainsString("mg_merchant_agent_require_owner_permission(\$user, 'merchant.ai.review')", $plan);
        $get = substr($plan, strpos($plan, "if (\$method === 'GET')"), strpos($plan, "if (\$method === 'POST')") - strpos($plan, "if (\$method === 'GET')"));
        self::assertStringNotContainsString('mg_merchant_agent_ai_begin_call', $get);
    }

    public function testChatPlanAndBriefingUseMerchantOwnerCreditContext(): void
    {
        $chat = $this->file('api/ai/merchant-agent-chat.php');
        $plan = $this->file('api/ai/merchant-agent-plan.php');
        $command = $this->file('api/ai/merchant-agent-command.php');
        self::assertStringContainsString("'merchant_agent_chat'", $chat);
        self::assertStringContainsString("'merchant_agent_crm_contact_chat'", $chat);
        self::assertStringContainsString("'merchant_agent_plan'", $plan);
        self::assertStringContainsString("'merchant_agent_command_briefing'", $command);
        foreach ([$chat, $plan, $command] as $source) {
            self::assertStringContainsString('mg_merchant_agent_require_owner_access', $source);
            self::assertStringContainsString('mg_merchant_agent_ai_begin_call', $source);
            self::assertStringContainsString('mg_merchant_agent_ai_last_result', $source);
        }
    }

    public function testUiBlocksOnlyAiSubmissionsAndKeepsSystematicComposerActions(): void
    {
        $ui = $this->file('assets/js/merchant-agent-ai-status.js');
        self::assertStringContainsString('root.dataset.merchantAgentAiCanGenerate', $ui);
        self::assertStringContainsString('isSystematic(text)', $ui);
        self::assertStringContainsString('snapshot|current snapshot|merchant snapshot', $ui);
        self::assertStringContainsString('^@[a-z0-9]', $ui);
        self::assertStringContainsString('event.stopImmediatePropagation()', $ui);
        self::assertStringContainsString('Database and systematic Merchant Agent tools remain available.', $ui);
    }

    public function testExistingSecurityAndApprovalBoundariesRemainPresent(): void
    {
        $chat = $this->file('api/ai/merchant-agent-chat.php');
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $chat);
        self::assertStringContainsString('mg_merchant_ensure_workspace($pdo, $user)', $chat);
        self::assertStringContainsString('mg_rate_limit(', $chat);
        self::assertStringContainsString('mg_agent_autonomy_require_for_merchant', $chat);
        self::assertStringContainsString('mg_agent_admin_limit_enforce_default', $chat);
        self::assertStringContainsString("'merchant_owner_id'=>\$actorId", $chat);
        self::assertStringContainsString('mg_merchant_contact_action_center_find_contact', $chat);
    }
}
