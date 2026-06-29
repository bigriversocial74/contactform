<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCustomerProfileLoadFallbackTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testCustomerProfileJavascriptFallsBackToLiteEndpoint(): void
    {
        $js = $this->source('assets/js/merchant-customer-profile.js');
        self::assertStringContainsString('/api/merchant/customer-profile.php', $js);
        self::assertStringContainsString('/api/merchant/customer-profile-lite.php', $js);
        self::assertStringContainsString('async function loadLite', $js);
        self::assertStringContainsString('Full profile error:', $js);
        self::assertStringContainsString('Basic customer profile loaded.', $js);
    }

    public function testLiteEndpointLoadsCampaignContactByMerchantScope(): void
    {
        $api = $this->source('api/merchant/customer-profile-lite.php');
        foreach ([
            'mg_require_permission(\'merchant.campaigns.view\')',
            'campaign_contact_id',
            'FROM campaign_contacts cc',
            'INNER JOIN campaigns c ON c.id=cc.campaign_id',
            'cc.merchant_user_id=?',
            'wallet_items',
            'can_message',
            'can_send_reward',
            'Loaded basic customer profile.',
        ] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }
}
