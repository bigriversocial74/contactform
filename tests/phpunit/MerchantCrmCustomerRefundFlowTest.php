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

    public function testSendGiftSlideOutIsSimplifiedForCustomerRefundCampaigns(): void
    {
        $script = $this->read('assets/js/merchant-crm-reward-picker.js');

        foreach ([
            'function ensureGiftUi',
            'Customer Refund campaign',
            'Send gift',
            'Choose Customer Refund campaign',
            '/api/merchant/crm-reward-campaigns.php?type=customer_refund',
            '/api/merchant/crm-campaign-send.php',
            "required_campaign_type:'customer_refund'",
            'crm-customer-refund-send-gift:',
            'wallet / Inbox PPPM',
            'Customer Refund gift sent.',
        ] as $marker) {
            self::assertStringContainsString($marker, $script);
        }

        self::assertStringNotContainsString('merchant-crm-customer-refund.js', $script);
        self::assertStringNotContainsString('data-crm-customer-refund-modal', $script);
    }

    public function testCampaignPickerSupportsCustomerRefundFilter(): void
    {
        $endpoint = $this->read('api/merchant/crm-reward-campaigns.php');

        foreach ([
            'type',
            'customer_refund',
            'filter_type',
            'c.campaign_type=?',
            'Unsupported reward campaign type.',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }
    }
}
