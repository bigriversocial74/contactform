<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicDonationsPublicCampaignContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $content = file_get_contents($this->root . '/' . $path);
        self::assertIsString($content, $path . ' must be readable.');
        return $content;
    }

    public function testDedicatedPageUsesDedicatedPublicReportingLayer(): void
    {
        $page = $this->read('public-donations.php');
        self::assertStringContainsString('public-donations-public.php', $page);
        self::assertStringContainsString('public-donations-public-view.php', $page);
        self::assertStringNotContainsString('public-campaign-page.php', $page);
    }

    public function testEndpointIsReadOnlyAndNonTransactional(): void
    {
        $api = $this->read('api/public/public-donations.php');
        self::assertStringContainsString("mg_require_method('GET')", $api);
        self::assertStringContainsString('mg_public_donations_public_payload', $api);
        self::assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE)\b/i', $api);
        self::assertStringNotContainsString('$error->getMessage()', $api);
    }

    public function testPublicCardsRequireAssignmentConsentAndEligibleProfiles(): void
    {
        $core = $this->read('includes/public-donations-public.php');
        self::assertGreaterThanOrEqual(2, substr_count($core, "assignment.public_display_status='approved'"));
        self::assertGreaterThanOrEqual(2, substr_count($core, "profile.status='active'"));
        self::assertGreaterThanOrEqual(2, substr_count($core, "profile.visibility IN ('public','unlisted')"));
    }

    public function testAggregateReportingDoesNotJoinPrivateLifecycleTables(): void
    {
        $core = $this->read('includes/public-donations-public.php');
        self::assertDoesNotMatchRegularExpression('/\bJOIN\s+(wallet_items|pppm_items|microgift_instances|inbox_items)\b/i', $core);
        self::assertStringNotContainsString('internal_note', $core);
        self::assertStringNotContainsString('claim_code', $core);
        self::assertStringContainsString("'final_recipient_identity_exposed' => false", $core);
    }

    public function testViewContainsNoTransactionalOrContactControls(): void
    {
        $view = $this->read('includes/public-donations-public-view.php');
        self::assertDoesNotMatchRegularExpression('/<(form|input|textarea|select|button)\b/i', $view);
        self::assertStringNotContainsString('data-submit-endpoint', $view);
        self::assertStringNotContainsString('name="email"', $view);
        self::assertStringNotContainsString('name="quantity"', $view);
    }

    public function testGovernanceLanguageIsAccurate(): void
    {
        $view = $this->read('includes/public-donations-public-view.php');
        $core = $this->read('includes/public-donations-public.php');
        self::assertStringContainsString('Merchant-funded promotional rewards—not cash donations', $view);
        self::assertStringContainsString('not cash, a charitable receipt, or a tax-deductible contribution', $view);
        self::assertStringContainsString("'tax_deductible_contribution' => false", $core);
        self::assertStringContainsString("'cash_donation' => false", $core);
    }

    public function testSeoFollowsPublicProfileVisibility(): void
    {
        $core = $this->read('includes/public-donations-public.php');
        $page = $this->read('public-donations.php');
        self::assertStringContainsString("'index,follow'", $core);
        self::assertStringContainsString("'noindex,nofollow'", $core);
        self::assertStringContainsString('merchant_profile_visibility', $core);
        self::assertStringContainsString("'robots' =>", $page);
        self::assertStringContainsString('X-Robots-Tag: noindex, nofollow', $page);
    }
}
