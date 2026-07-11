<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmRealtimeMessageAppendTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testMerchantCrmLoadsRealtimeMessageScript(): void
    {
        $source = file_get_contents($this->root() . '/merchant-crm.php');
        self::assertIsString($source);
        self::assertStringContainsString('/assets/js/merchant-crm-realtime-message.js', $source);
    }

    public function testRealtimeMessageScriptAppendsAndRefreshesMessages(): void
    {
        $source = file_get_contents($this->root() . '/assets/js/merchant-crm-realtime-message.js');
        self::assertIsString($source);

        foreach ([
            'mg:crm-messages:refresh',
            'appendOptimistic',
            '.mg-message-stream',
            '[data-cp-messages], [data-cp-messages-full]',
            '[data-customer-message-list]',
            '/api/merchant/crm-messages.php?thread=',
            'renderThread',
            'data-crm-live-message',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }
}
