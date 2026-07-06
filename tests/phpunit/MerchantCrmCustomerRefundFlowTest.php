<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmCustomerRefundFlowTest extends TestCase
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

    public function testCustomerRefundFrontendContract(): void
    {
        $loader = $this->read('assets/js/merchant-crm-realtime-message.js');
        $script = $this->read('assets/js/merchant-crm-customer-refund.js');

        self::assertStringContainsString('/assets/js/merchant-crm-customer-refund.js', $loader);

        foreach ([
            'data-crm-customer-refund-modal',
            'data-crm-customer-refund-form',
            'data-crm-customer-refund-campaign',
            'data-crm-customer-refund-submit',
            'data-crm-customer-refund',
            '/api/merchant/crm-reward-campaigns.php?type=customer_refund',
            '/api/merchant/crm-campaign-send.php',
            "required_campaign_type: 'customer_refund'",
            'Customer Refund voucher sent.',
            'wallet / Inbox PPPM',
        ] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
    }

    public function testCampaignPickerSupportsCustomerRefundFilter(): void
    {
        $endpoint = $this->read('api/merchant/crm-reward-campaigns.php');

        foreach ([
            '$_GET[\'type\']',
            'customer_refund',
            'filter_type',
            'c.campaign_type=?',
            'Unsupported reward campaign type.',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }
    }
}
