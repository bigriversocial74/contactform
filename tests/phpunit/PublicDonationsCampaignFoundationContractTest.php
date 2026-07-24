<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicDonationsCampaignFoundationContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }

    public function testRegistryDefinesInformationalNonTransactionalCampaign(): void
    {
        require_once $this->root . '/includes/campaign-types.php';
        $definition = mg_campaign_type_get('public_donation');
        self::assertIsArray($definition);
        self::assertSame('Public Donations', $definition['label']);
        self::assertSame('community_support', $definition['category']);
        self::assertTrue(mg_campaign_type_public_enabled('public_donation'));
        self::assertFalse(mg_campaign_type_public_transactional('public_donation'));
        self::assertSame('informational', mg_campaign_type_public_mode('public_donation'));
        self::assertSame('', mg_campaign_type_submit_endpoint('public_donation'));
    }

    public function testFeatureStatesAreServerControlled(): void
    {
        $feature = (string)file_get_contents($this->root . '/includes/public-donations-feature.php');
        self::assertStringContainsString('MG_PUBLIC_DONATIONS_FEATURE_STATE', $feature);
        self::assertStringContainsString('MG_PUBLIC_DONATIONS_MERCHANT_IDS', $feature);
        self::assertStringContainsString("['disabled', 'admin_only', 'selected_merchants', 'enabled']", $feature);
        self::assertStringContainsString("?: 'disabled'", $feature);
    }

    public function testPublicExperienceHasNoTransactionPath(): void
    {
        $page = (string)file_get_contents($this->root . '/includes/public-campaign-page.php');
        $detail = (string)file_get_contents($this->root . '/api/public/campaigns/detail.php');
        $engage = (string)file_get_contents($this->root . '/api/public/campaigns/engage.php');
        self::assertStringContainsString('mg_campaign_type_public_transactional', $page);
        self::assertStringContainsString('These rewards are not available for public purchase or request.', $page);
        self::assertStringContainsString("'public_transactional'", $detail);
        self::assertStringContainsString('does not accept public requests', $engage);
        self::assertStringContainsString("require __DIR__ . '/engage-core.php'", $engage);
        self::assertStringContainsString('data-campaign-closed-state', $page);
        $profilePage = (string)file_get_contents($this->root . '/profile.php');
        self::assertStringContainsString('/assets/css/public-donations-campaign-v1.css', $profilePage);
    }

    public function testMerchantAndProfileSurfacesUseCanonicalContracts(): void
    {
        $merchant = (string)file_get_contents($this->root . '/api/merchant/campaigns-core.php');
        $market = (string)file_get_contents($this->root . '/includes/market/merchant-market-engine.php');
        $profile = (string)file_get_contents($this->root . '/assets/js/public-profile-investment.js');
        self::assertStringContainsString('mg_public_donations_is_enabled_for', $merchant);
        self::assertStringContainsString('mg_public_donations_campaign_type_options', $merchant);
        self::assertStringContainsString('community_accounts_supported', $market);
        self::assertStringContainsString('rewards_allocated', $market);
        self::assertStringContainsString('Community accounts supported', $profile);
        self::assertStringContainsString('View Campaign', $profile);
    }
}
