<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicProfileCommunityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $value = file_get_contents($this->root . '/' . $path);
        self::assertIsString($value, $path . ' must be readable.');
        return $value;
    }

    public function testCommunityReportingUsesCanonicalMerchantScopedLifecycle(): void
    {
        $core = $this->read('includes/public-profile-community.php');
        self::assertGreaterThanOrEqual(5, substr_count($core, 'merchant_user_id=?'));
        self::assertStringContainsString('INNER JOIN wallet_items wallet', $core);
        self::assertStringContainsString('INNER JOIN pppm_items pppm', $core);
        self::assertStringContainsString('INNER JOIN microgift_instances microgift', $core);
    }

    public function testCommunityAccountsAreDeduplicatedAcrossCampaigns(): void
    {
        $core = $this->read('includes/public-profile-community.php');
        self::assertStringContainsString('GROUP BY assignment.community_user_id', $core);
        self::assertStringContainsString('COUNT(DISTINCT assignment.campaign_id)', $core);
    }

    public function testActivePausedAndCompletedHistoryAreSupported(): void
    {
        $core = $this->read('includes/public-profile-community.php');
        self::assertStringContainsString("campaign.status IN ('active','paused','ended')", $core);
        self::assertStringContainsString("return 'completed'", $core);
        self::assertStringContainsString("return 'paused'", $core);
        self::assertStringContainsString("return 'active'", $core);
    }

    public function testOnlyActivePublicDonationsRemainInActiveCampaigns(): void
    {
        $core = $this->read('includes/public-profile-community.php');
        self::assertStringContainsString('mg_public_profile_community_enrich_campaign_items', $core);
        self::assertStringContainsString("if (\$type !== 'public_donation')", $core);
        self::assertStringContainsString("'url'] = (string)(\$campaign['url']", $core);
        self::assertStringContainsString("'action_label'] = 'View Campaign'", $core);
    }

    public function testPublicPayloadDoesNotExposePrivateRecipientData(): void
    {
        $core = $this->read('includes/public-profile-community.php');
        self::assertStringContainsString("'final_recipient_identity_exposed' => false", $core);
        self::assertStringNotContainsString('recipient.display_name', $core);
        self::assertStringNotContainsString('downstream_user', $core);
        self::assertStringNotContainsString('claim_code', $core);
        self::assertStringNotContainsString('internal_note', $core);
    }

    public function testProfileContainsCommunityTabAndSafeRenderer(): void
    {
        $page = $this->read('profile.php');
        $js = $this->read('assets/js/public-profile-community-v1.js');
        self::assertStringContainsString('data-invest-tab="community"', $page);
        self::assertStringContainsString('data-profile-community-summary', $page);
        self::assertStringContainsString('data-profile-community-campaigns', $page);
        self::assertStringContainsString('data-profile-community-accounts', $page);
        self::assertStringContainsString('document.createElement', $js);
        self::assertStringNotContainsString('.innerHTML', $js);
        self::assertStringNotContainsString('document.write', $js);
        self::assertStringNotContainsString('eval(', $js);
    }

    public function testProfileApiCarriesCanonicalCommunityPayload(): void
    {
        $api = $this->read('api/public/profile-investment.php');
        self::assertStringContainsString("\$payload['community_support']", $api);
        self::assertStringContainsString('mg_public_profile_community_enrich_campaign_items', $api);
        self::assertStringContainsString('Cache-Control: private, no-store', $api);
    }
}
