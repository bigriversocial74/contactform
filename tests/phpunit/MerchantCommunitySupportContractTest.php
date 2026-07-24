<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCommunitySupportContractTest extends TestCase
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

    public function testDashboardUsesCanonicalMerchantScopedLifecycleSources(): void
    {
        $core = $this->read('includes/merchant-community-support.php');
        self::assertGreaterThanOrEqual(5, substr_count($core, 'merchant_user_id=?'));
        self::assertStringContainsString('INNER JOIN wallet_items wallet', $core);
        self::assertStringContainsString('INNER JOIN pppm_items pppm', $core);
        self::assertStringContainsString('INNER JOIN microgift_instances microgift', $core);
    }

    public function testLifecycleMetricsAreCumulativeAndKeepGrossRecallNetDistinct(): void
    {
        $core = $this->read('includes/merchant-community-support.php');
        foreach (['gross_allocated', 'recalled', 'net_allocated', 'available', 'regifted', 'claimed', 'redeemed'] as $marker) {
            self::assertStringContainsString("'{$marker}'", $core);
        }
    }

    public function testCommunityAccountsAggregateAcrossCampaigns(): void
    {
        $core = $this->read('includes/merchant-community-support.php');
        self::assertStringContainsString('GROUP BY assignment.community_user_id', $core);
        self::assertStringContainsString('COUNT(DISTINCT assignment.campaign_id)', $core);
    }

    public function testDownstreamRecipientIdentityIsNotSelected(): void
    {
        $core = $this->read('includes/merchant-community-support.php');
        self::assertStringContainsString("'downstream_recipient_identity_exposed' => false", $core);
        self::assertStringNotContainsString('recipient.display_name', $core);
        self::assertStringNotContainsString('downstream_user', $core);
    }

    public function testApiIsReadOnlyPermissionProtectedAndUsesSafeUnexpectedErrors(): void
    {
        $api = $this->read('api/merchant/community-support.php');
        self::assertStringContainsString("mg_merchant_require_permission('merchant.campaigns.view')", $api);
        self::assertStringContainsString("if (\$method !== 'GET')", $api);
        self::assertStringContainsString('mg_fail_unexpected(', $api);
        self::assertStringNotContainsString('$error->getMessage()', $api);
        self::assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE)\b/i', $api);
    }

    public function testWorkspaceExposesRequiredNavigationAndTabs(): void
    {
        $nav = $this->read('includes/merchant-navigation.php');
        $view = $this->read('includes/merchant-community-support-view.php');
        self::assertStringContainsString('/merchant-community-support.php', $nav);
        self::assertStringContainsString("'merchant-community-support' => 'community_support'", $nav);
        foreach (['campaigns', 'accounts', 'batches', 'activity'] as $tab) {
            self::assertStringContainsString('data-tab="' . $tab . '"', $view);
        }
    }

    public function testFrontendUsesSafeDomConstruction(): void
    {
        $js = $this->read('assets/js/merchant-community-support.js');
        self::assertStringContainsString('document.createElement', $js);
        self::assertStringNotContainsString('.innerHTML', $js);
        self::assertStringNotContainsString('document.write', $js);
        self::assertStringNotContainsString('eval(', $js);
    }
}
