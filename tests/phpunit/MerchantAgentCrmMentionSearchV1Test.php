<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentCrmMentionSearchV1Test extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSearchUsesCanonicalMerchantOwnedCrmAndProfileSlugs(): void
    {
        $source = file_get_contents($this->root . '/includes/merchant-crm-search.php');
        self::assertIsString($source);
        self::assertStringContainsString('FROM merchant_crm_contacts mc', $source);
        self::assertStringContainsString('mc.merchant_user_id=?', $source);
        self::assertStringContainsString('pp.slug profile_slug', $source);
        self::assertStringContainsString("'mention'=>'@' . \$handle", $source);
        self::assertStringContainsString('merged_into_contact_id IS NULL', $source);
    }

    public function testStandaloneMentionIsDeterministicAndDoesNotInvokeClaude(): void
    {
        $route = file_get_contents($this->root . '/api/ai/merchant-agent-chat.php');
        $helper = file_get_contents($this->root . '/includes/ai/merchant-agent-crm-search.php');
        self::assertIsString($route);
        self::assertIsString($helper);
        self::assertStringContainsString("\$action = 'crm_search'", $route);
        self::assertStringContainsString("if (\$action === 'crm_search')", $route);
        self::assertStringContainsString("preg_match('/^@[a-z0-9][a-z0-9._-]{0,119}$/i'", $helper);
        self::assertStringNotContainsString('mg_anthropic_', $helper);
        self::assertStringContainsString('mg_ai_chat_record_message', $helper);
        self::assertStringContainsString("'crm_search'=>\$result", $helper);
    }

    public function testAutocompleteAndAllResultPaginationAreWiredBeforeMainChat(): void
    {
        $page = file_get_contents($this->root . '/merchant-agent-chat.php');
        $script = file_get_contents($this->root . '/assets/js/merchant-agent-crm-mention-search.js');
        self::assertIsString($page);
        self::assertIsString($script);
        $mention = strpos($page, '/assets/js/merchant-agent-crm-mention-search.js?v=1.0.0');
        $chat = strpos($page, '/assets/js/merchant-agent-chat.js?v=2.3.0');
        self::assertNotFalse($mention);
        self::assertNotFalse($chat);
        self::assertLessThan($chat, $mention);
        self::assertStringContainsString("form.addEventListener('submit'", $script);
        self::assertStringContainsString('event.stopImmediatePropagation()', $script);
        self::assertStringContainsString('while (result.has_more && safety < 100)', $script);
        self::assertStringContainsString("'&limit=100&offset='", $script);
        self::assertStringContainsString('new MutationObserver', $script);
    }

    public function testChatRendersCompactRowsAndCurrentCrmActions(): void
    {
        $script = file_get_contents($this->root . '/assets/js/merchant-agent-crm-mention-search.js');
        $css = file_get_contents($this->root . '/assets/css/merchant-agent-crm-mention-search.css');
        self::assertIsString($script);
        self::assertIsString($css);
        self::assertStringContainsString('<table class="mg-agent-crm-table">', $script);
        foreach (['>Select</button>', '>Profile</a>', '>Timeline</a>', '>Message</a>', '>Reward</a>'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
        self::assertStringContainsString('/merchant-crm.php?search=', $script);
        self::assertStringContainsString('@media(max-width:820px)', $css);
        self::assertStringContainsString('@media(max-width:520px)', $css);
    }

    public function testSearchEndpointRequiresAgentAndCrmPermissions(): void
    {
        $source = file_get_contents($this->root . '/api/merchant/crm-search.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_merchant_require_permission('merchant.ai.review')", $source);
        self::assertStringContainsString("mg_merchant_require_permission('merchant.campaigns.view')", $source);
        self::assertStringContainsString("\$workspace['merchant_user_id']", $source);
        self::assertStringContainsString("mg_audit('merchant.agent_crm_search.read', 'merchant_crm'", $source);
        self::assertStringNotContainsString("'query'=>\$query", $source);
    }
}
