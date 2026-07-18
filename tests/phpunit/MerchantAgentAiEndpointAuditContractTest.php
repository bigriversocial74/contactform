<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantAgentAiEndpointAuditContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function file(string $path): string
    {
        $content = file_get_contents($this->root . '/' . ltrim($path, '/'));
        self::assertIsString($content, 'Unable to read ' . $path);
        return $content;
    }

    /** @return list<string> */
    private function directAnthropicCallers(): array
    {
        $callers = [];
        $directory = $this->root . '/includes/ai';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') continue;
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));
            if ($path === 'includes/ai/anthropic-client.php') continue;
            $source = (string)file_get_contents($file->getPathname());
            if (str_contains($source, 'mg_anthropic_messages(')) $callers[] = $path;
        }
        sort($callers);
        return $callers;
    }

    public function testEveryDirectMerchantAgentAnthropicCallerIsInventoried(): void
    {
        self::assertSame([
            'includes/ai/merchant-agent-chat-memory.php',
            'includes/ai/merchant-agent-chat.php',
            'includes/ai/merchant-agent-crm-contact-chat.php',
            'includes/ai/merchant-agent-planner.php',
        ], $this->directAnthropicCallers());
    }

    public function testSharedAnthropicHookFailsClosedBeforeProviderAccess(): void
    {
        $helper = $this->file('includes/ai/merchant-agent-credit-response.php');
        $client = $this->file('includes/ai/anthropic-client.php');

        self::assertStringContainsString('function mg_merchant_agent_ai_call_context(string $phase): array', $helper);
        self::assertStringContainsString("'merchant_agent.ai_call_context_missing'", $helper);
        self::assertStringContainsString("'scope'=>'merchant_ai_call_context_required'", $helper);
        self::assertStringContainsString("str_starts_with((string)(\$call['source_type'] ?? ''), 'merchant_agent_')", $helper);
        self::assertStringContainsString("'preflighted'=>false", $helper);
        self::assertStringContainsString("mg_merchant_agent_ai_call_context('preflight')", $helper);
        self::assertStringContainsString("\$GLOBALS['mg_merchant_agent_ai_call']['preflighted'] = true", $helper);
        self::assertStringContainsString("mg_merchant_agent_ai_call_context('debit')", $helper);
        self::assertStringContainsString('mg_ai_credit_preflight(', $helper);
        self::assertStringContainsString('mg_ai_credit_consume(', $helper);

        $hook = strpos($client, 'mg_merchant_agent_ai_before_anthropic_call($payload)');
        $provider = strpos($client, "curl_init('https://api.anthropic.com/v1/messages')");
        self::assertNotFalse($hook);
        self::assertNotFalse($provider);
        self::assertLessThan($provider, $hook, 'The owner-credit hook must execute before the Anthropic request is initialized.');
    }

    public function testAllPublicGenerationEndpointsEstablishOwnerCreditContext(): void
    {
        $endpoints = [
            'api/ai/merchant-agent-chat.php' => [
                'mg_ai_chat_send_with_memory(',
                'mg_merchant_agent_crm_contact_chat_response(',
            ],
            'api/ai/merchant-agent-plan.php' => [
                'mg_ai_merchant_create_plan(',
            ],
            'api/ai/merchant-agent-command.php' => [
                'mg_agent_cmd_daily_briefing(',
            ],
        ];

        foreach ($endpoints as $path => $generationCalls) {
            $source = $this->file($path);
            self::assertStringContainsString('mg_merchant_agent_require_owner_access($pdo)', $source, $path);
            self::assertStringContainsString("merchant-agent-credit-response.php", $source, $path);
            $begin = strpos($source, 'mg_merchant_agent_ai_begin_call');
            self::assertNotFalse($begin, $path . ' must begin an owner-credit context.');
            $end = strpos($source, 'mg_merchant_agent_ai_end_call', $begin);
            self::assertNotFalse($end, $path . ' must close its owner-credit context in a finally block.');
            self::assertStringContainsString('finally', substr($source, $begin, $end - $begin + 80), $path);
            foreach ($generationCalls as $generationCall) {
                $position = strpos($source, $generationCall);
                self::assertNotFalse($position, $path . ' missing audited generation call ' . $generationCall);
                self::assertGreaterThan($begin, $position, $generationCall . ' must run after the credit context begins.');
                self::assertLessThan($end, $position, $generationCall . ' must run before the credit context closes.');
            }
        }
    }

    public function testMerchantAgentApiEndpointsNeverCallAnthropicDirectly(): void
    {
        $paths = glob($this->root . '/api/ai/merchant-agent*.php') ?: [];
        self::assertNotEmpty($paths);
        foreach ($paths as $path) {
            $source = (string)file_get_contents($path);
            self::assertStringNotContainsString('mg_anthropic_messages(', $source, basename($path) . ' must use an audited Merchant Agent generator.');
        }
    }

    public function testDeterministicReviewAndMemoryEndpointsRemainNonGenerative(): void
    {
        foreach ([
            'api/ai/merchant-agent-chat-review.php',
            'api/ai/merchant-agent-memory-sources.php',
        ] as $path) {
            $source = $this->file($path);
            self::assertStringNotContainsString('mg_anthropic_messages(', $source, $path);
            self::assertStringNotContainsString('mg_merchant_agent_ai_begin_call', $source, $path);
            self::assertStringContainsString('mg_merchant_agent_require_owner_access', $source, $path);
        }
    }

    public function testAuditedSourcesRemainMerchantAgentNamespaced(): void
    {
        $chat = $this->file('api/ai/merchant-agent-chat.php');
        $plan = $this->file('api/ai/merchant-agent-plan.php');
        $command = $this->file('api/ai/merchant-agent-command.php');

        foreach ([
            'merchant_agent_chat',
            'merchant_agent_crm_contact_chat',
            'merchant_agent_plan',
            'merchant_agent_command_briefing',
        ] as $sourceType) {
            self::assertTrue(
                str_contains($chat, "'{$sourceType}'") || str_contains($plan, "'{$sourceType}'") || str_contains($command, "'{$sourceType}'"),
                'Missing audited source type ' . $sourceType
            );
        }
    }
}
