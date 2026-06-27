<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCustomerProfileDataTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source);
        return $source;
    }

    public function testCustomerProfileApiContracts(): void
    {
        $api=$this->source('api/merchant/customer-profile.php');
        foreach([
            'merchant.campaigns.view',
            'merchant.campaigns.manage',
            'mg_merchant_ensure_workspace',
            'merchant_user_id=?',
            'merchant_crm_contacts',
            'merchant_crm_contact_events',
            'merchant_crm_contact_campaigns',
            'merchant_crm_notes',
            'campaign_contacts',
            'wallet_items',
            'message_threads',
            'messages',
            'scanner_redemption_receipts',
            'mg_cp_wallet_stats',
            'mg_cp_messages',
            'mg_cp_notes',
            'mg_cp_campaign_sources',
            'mg_cp_redemptions',
            'mg_cp_events',
            'mg_cp_activity_chart',
        ] as $needle) self::assertStringContainsString($needle,$api);
    }

    public function testCustomerProfileNoteContract(): void
    {
        $api=$this->source('api/merchant/customer-profile.php');
        foreach(['mg_require_csrf_for_write($input)','INSERT INTO merchant_crm_notes','crm.note.added','CRM note added.'] as $needle) self::assertStringContainsString($needle,$api);
    }
}
