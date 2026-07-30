<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignDropdownNavigationContractTest extends TestCase
{
    private string $template;

    protected function setUp(): void
    {
        $this->template = (string) file_get_contents(
            dirname(__DIR__, 2) . '/includes/header-templates/logged-in.php'
        );
    }

    public function testMerchantTabLinksToCanonicalCreatorCampaignWorkspace(): void
    {
        self::assertStringContainsString('mg-account-merchant-panel', $this->template);
        self::assertStringContainsString('href="/merchant-creator-campaigns.php"', $this->template);
        self::assertStringContainsString('<span>Creator Campaigns</span>', $this->template);
    }

    public function testCreatorLinkRequiresApprovedCreatorOrSuperAdmin(): void
    {
        self::assertStringContainsString("in_array('super_admin', \$user_roles, true)", $this->template);
        self::assertStringContainsString("um.code='creator'", $this->template);
        self::assertStringContainsString("uma.status='active'", $this->template);
        self::assertStringContainsString("cp.status='active'", $this->template);
        self::assertStringContainsString('if ($can_creator_campaign_nav)', $this->template);
        self::assertStringContainsString('href="/creator-campaigns.php"', $this->template);
    }

    public function testCreatorEligibilityLookupFailsClosed(): void
    {
        self::assertStringContainsString('catch (Throwable)', $this->template);
        self::assertStringContainsString('$can_creator_campaign_nav = false;', $this->template);
    }
}
