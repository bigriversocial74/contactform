<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CustomerRefundCampaignCreatorPolishTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root() . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testCustomerRefundCreatorUsesGuidedDefaults(): void
    {
        $script = $this->read('assets/js/stage12-customer-refund-campaign-type.js');

        foreach ([
            'Customer Refund Make-Good Voucher',
            'Customer Refund campaign is ready',
            'quantity_limit',
            "setField('quantity_limit','25'",
            "setField('per_user_limit','1'",
            'datetimeLocal(90)',
            'selectFirstReward',
            "form.elements.status.value=selected?'active':'draft'",
            'data-customer-refund-guide',
            'Create reward template',
            'Open Merchant CRM',
            'Create Customer Refund',
            'wallet / Inbox PPPM',
        ] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
    }

    public function testCustomerRefundSubmitStillUsesDedicatedEndpoint(): void
    {
        $script = $this->read('assets/js/stage12-customer-refund-campaign-type.js');

        foreach ([
            "data.campaign_type='customer_refund'",
            "data.agent_discoverable=0",
            '/api/merchant/customer-refund-campaigns.php',
            'Choose an active reward template before activating this Customer Refund campaign.',
            'Customer Refund campaign saved.',
        ] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
    }
}
