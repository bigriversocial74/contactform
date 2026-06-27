<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CrmStampPackageEnforcementContractTest extends TestCase
{
    public function testPublicCampaignLimitsIncludeCrmAndStampGuards(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/public/campaigns/_limits.php');

        self::assertIsString($source);
        self::assertStringContainsString('mg_public_campaign_enforce_crm_contact_limit', $source);
        self::assertStringContainsString('max_crm_contacts', $source);
        self::assertStringContainsString('CRM contact limit reached.', $source);
        self::assertStringContainsString('mg_public_campaign_enforce_monthly_stamp_limit', $source);
        self::assertStringContainsString('monthly_stamps_included', $source);
        self::assertStringContainsString('stamp_overage_enabled', $source);
        self::assertStringContainsString('Monthly stamp limit reached.', $source);
    }

    public function testSignupCampaignAppliesCrmAndMonthlyStampGuards(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/public/campaigns/signup.php');

        self::assertIsString($source);
        self::assertStringContainsString('mg_public_campaign_enforce_crm_contact_limit($pdo, $merchantId, $email, $isNewContact)', $source);
        self::assertStringContainsString('mg_public_campaign_enforce_monthly_stamp_limit($pdo, $merchantId)', $source);
        self::assertStringContainsString('wallet_items', $source);
        self::assertStringContainsString('campaign_contacts', $source);
    }

    public function testOutboundCampaignEmailRespectsPackageEmailFlag(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/public/campaigns/_outbound.php');

        self::assertIsString($source);
        self::assertStringContainsString('mg_public_campaign_outbound_email_enabled', $source);
        self::assertStringContainsString('email_stamps_enabled', $source);
        self::assertStringContainsString('email_stamps_disabled', $source);
        self::assertStringContainsString('mg_delivery_enqueue', $source);
    }

    public function testDirectCrmGiftChecksMonthlyStampAndEmailFlag(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/crm-send-gift.php');

        self::assertIsString($source);
        self::assertStringContainsString('monthly_stamps_included', $source);
        self::assertStringContainsString('stamp_overage_enabled', $source);
        self::assertStringContainsString('Monthly stamp limit reached.', $source);
        self::assertStringContainsString('email_stamps_enabled', $source);
        self::assertStringContainsString('email_stamps_disabled', $source);
    }
}
