<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCustomerServerFallbackTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testMerchantCustomerPageIncludesServerFallbackBeforeCommandCenter(): void
    {
        $page = $this->source('merchant-customer.php');
        self::assertStringContainsString('merchant-customer-profile-server-fallback.php', $page);
        self::assertStringContainsString('merchant-customer-profile-view.php', $page);
        self::assertLessThan(
            strpos($page, 'merchant-customer-profile-view.php'),
            strpos($page, 'merchant-customer-profile-server-fallback.php')
        );
    }

    public function testServerFallbackIsScopedToCampaignContactAndMerchant(): void
    {
        $fallback = $this->source('includes/merchant-customer-profile-server-fallback.php');
        foreach ([
            'campaign_contact_id',
            'FROM campaign_contacts cc',
            'INNER JOIN campaigns c ON c.id=cc.campaign_id',
            'cc.public_id=? AND cc.merchant_user_id=?',
            'data-cp-server-fallback',
            'Open in CRM',
        ] as $needle) {
            self::assertStringContainsString($needle, $fallback);
        }
    }

    public function testLiteProfileAvoidsOptionalUserEmailVerificationColumn(): void
    {
        $lite = $this->source('api/merchant/customer-profile-lite.php');
        self::assertStringContainsString('COALESCE(cc.user_id,email_user.id) resolved_user_id', $lite);
        self::assertStringNotContainsString('email_verified_at', $lite);
    }
}
