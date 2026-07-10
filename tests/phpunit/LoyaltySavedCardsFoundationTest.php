<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LoyaltySavedCardsFoundationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testUtilitySidebarRendersLoyaltyCardsServerSide(): void
    {
        $source = file_get_contents($this->root . '/includes/app-sidebar.php');
        self::assertIsString($source);
        self::assertStringContainsString("'loyalty-cards'", $source);
        self::assertStringContainsString("'/loyalty-cards.php'", $source);
        self::assertStringContainsString("Saved stamp and visit cards", $source);
    }

    public function testLoyaltyAssetsArePageScopedInsteadOfGlobal(): void
    {
        $footer = file_get_contents($this->root . '/includes/footer.php');
        $stamp = file_get_contents($this->root . '/stamp-card.php');
        $page = file_get_contents($this->root . '/loyalty-cards.php');

        self::assertIsString($footer);
        self::assertIsString($stamp);
        self::assertIsString($page);
        self::assertStringNotContainsString("'/assets/css/loyalty-cards.css'", $footer);
        self::assertStringNotContainsString("'/assets/js/loyalty-cards.js'", $footer);
        self::assertStringContainsString("'/assets/css/loyalty-cards.css'", $stamp);
        self::assertStringContainsString("'/assets/js/loyalty-cards.js'", $stamp);
        self::assertStringContainsString("'/assets/css/loyalty-cards.css'", $page);
        self::assertStringContainsString("'/assets/js/loyalty-cards.js'", $page);
    }

    public function testStampPageRendersSavedCardButtonDirectly(): void
    {
        $source = file_get_contents($this->root . '/stamp-card.php');
        self::assertIsString($source);
        self::assertStringContainsString('data-loyalty-save-toggle', $source);
        self::assertStringContainsString('data-campaign-id', $source);
        self::assertStringContainsString('data-loyalty-save-icon', $source);
        self::assertStringContainsString('data-loyalty-save-label', $source);
    }

    public function testClientDoesNotInjectSidebarOrSaveButtonMarkup(): void
    {
        $source = file_get_contents($this->root . '/assets/js/loyalty-cards.js');
        self::assertIsString($source);
        self::assertStringNotContainsString('injectSidebarLink', $source);
        self::assertStringNotContainsString('trustRow.appendChild', $source);
        self::assertStringContainsString("document.querySelector('[data-loyalty-save-toggle]')", $source);
    }

    public function testStampCampaignArtworkIsUploadedAndPersisted(): void
    {
        $client = file_get_contents($this->root . '/assets/js/stage12-campaign-media-artwork.js');
        $api = file_get_contents($this->root . '/api/merchant/campaigns.php');

        self::assertIsString($client);
        self::assertIsString($api);
        self::assertStringContainsString('stamp_card_reward', $client);
        self::assertStringContainsString('stamp_card_image_asset_id', $client);
        self::assertStringContainsString('stamp_card_image_url', $client);
        self::assertStringContainsString("'stamp_card_image_asset_id'", $api);
        self::assertStringContainsString("'stamp_card_image_url'", $api);
        self::assertStringContainsString("'media_image_url'", $api);
    }

    public function testSavedCardsUseCampaignImageBeforeFallbacks(): void
    {
        $source = file_get_contents($this->root . '/api/account/loyalty-cards.php');
        self::assertIsString($source);
        $stampPosition = strpos($source, "'stamp_card_image_url'");
        $mediaPosition = strpos($source, "'media_image_url'");
        $merchantCoverPosition = strpos($source, "merchant_profile_cover_url");
        self::assertNotFalse($stampPosition);
        self::assertNotFalse($mediaPosition);
        self::assertNotFalse($merchantCoverPosition);
        self::assertLessThan($mediaPosition, $stampPosition);
        self::assertLessThan($merchantCoverPosition, $mediaPosition);
    }

    public function testSavedCardListAvoidsDuplicateContactJoin(): void
    {
        $source = file_get_contents($this->root . '/api/account/loyalty-cards.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('LEFT JOIN campaign_contacts cc ON', $source);
        self::assertStringContainsString('INNER JOIN campaign_contacts ccx', $source);
        self::assertStringContainsString("ce.event_type='stamp_card.stamped'", $source);
        self::assertStringContainsString("'campaign_status'", $source);
    }

    public function testSavedCardsMigrationCreatesUniqueUserCampaignRecord(): void
    {
        $sql = file_get_contents($this->root . '/database/customer_saved_campaign_cards_20260709.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('customer_saved_campaign_cards', $sql);
        self::assertMatchesRegularExpression('/UNIQUE KEY\s+uq_[^(]+\s*\(user_id,campaign_id\)/i', $sql);
    }
}
