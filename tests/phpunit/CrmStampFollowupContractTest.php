<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CrmStampFollowupContractTest extends TestCase
{
    public function testCampaignRewardLimitHelperAlsoChecksMonthlyStampLimit(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/public/campaigns/_limits.php');

        self::assertIsString($source);
        self::assertStringContainsString('function mg_public_campaign_enforce_reward_limits', $source);
        self::assertStringContainsString('merchant_user_id', $source);
        self::assertStringContainsString('mg_public_campaign_enforce_monthly_stamp_limit($pdo, $merchantId)', $source);
        self::assertStringContainsString('monthly_stamps_included', $source);
        self::assertStringContainsString('stamp_overage_enabled', $source);
    }

    public function testCrmRewardInviteRequiresEmailStampAccess(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/crm-send-reward-invite.php');

        self::assertIsString($source);
        self::assertStringContainsString('mg_user_package_context($pdo, $user)', $source);
        self::assertStringContainsString('email_stamps_enabled', $source);
        self::assertStringContainsString('Email Stamps are not enabled for this package.', $source);
        self::assertStringContainsString('mg_delivery_enqueue', $source);
    }
}
