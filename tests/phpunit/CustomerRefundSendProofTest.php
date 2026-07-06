<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CustomerRefundSendProofTest extends TestCase
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

    public function testSendGiftSlideOutShowsProofAndActions(): void
    {
        $source = $this->read('assets/js/merchant-crm-reward-picker.js');

        foreach ([
            'data-crm-reward-proof',
            'data-crm-reward-proof-list',
            'Customer Refund sent',
            'View customer timeline',
            'Send another gift',
            'wallet_item_id',
            'campaign_title',
            'reward_template_title',
            'customer_email',
            'wallet_status',
            'sent_at',
            'Customer Refund sent. Proof is shown above.',
            '/api/merchant/crm-reward-campaigns.php?type=customer_refund&ts=',
            'mg:crm:open-timeline',
        ] as $marker) {
            self::assertStringContainsString($marker, $source);
        }
    }

    public function testCampaignSendEndpointReturnsTimelineFriendlyProof(): void
    {
        $source = $this->read('api/merchant/crm-campaign-send.php');

        foreach ([
            'required_campaign_type',
            'mg_crm_campaign_send_event_type',
            'crm.customer_refund.sent',
            'timeline_label',
            'Customer Refund sent',
            'campaign_title',
            'reward_template_title',
            'customer_email',
            'customer_name',
            'wallet_status',
            'sent_at',
            'Issued to wallet / Inbox PPPM',
            'crm_event_type',
        ] as $marker) {
            self::assertStringContainsString($marker, $source);
        }
    }

    public function testRealtimeScriptOpensTimelineFromProofEvent(): void
    {
        $source = $this->read('assets/js/merchant-crm-realtime-message.js');

        foreach ([
            'mg:crm:open-timeline',
            'openTimelineFromProof',
            '[data-crm-reward-modal]',
            '[data-view-timeline]',
            'campaign_contact_id',
            'action=timeline',
        ] as $marker) {
            self::assertStringContainsString($marker, $source);
        }
    }
}
