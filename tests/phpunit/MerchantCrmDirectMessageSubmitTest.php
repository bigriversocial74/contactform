<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmDirectMessageSubmitTest extends TestCase
{
    public function testCommandCenterContainsDirectMessageSubmitHandling(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/merchant-crm-command-center.js');
        self::assertIsString($source);

        self::assertStringContainsString('installDirectMessageSubmitGuard', $source);
        self::assertStringContainsString('[data-crm-message-submit]', $source);
        self::assertStringContainsString('[data-crm-message-form]', $source);
        self::assertStringContainsString('[data-crm-message-body]', $source);
        self::assertStringContainsString('[data-crm-message-status]', $source);
        self::assertStringContainsString('/api/merchant/crm-message.php', $source);
        self::assertStringContainsString('event.stopImmediatePropagation', $source);
        self::assertStringContainsString('Message delivered to customer Messages.', $source);
        self::assertStringContainsString('Message queued for email fallback.', $source);
        self::assertStringContainsString('Message endpoint returned without thread/message proof.', $source);
    }
}
