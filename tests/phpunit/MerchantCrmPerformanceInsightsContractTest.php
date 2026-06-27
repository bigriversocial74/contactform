<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantCrmPerformanceInsightsContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function read(string $path): string { return (string) file_get_contents($this->root . '/' . $path); }

    public function testInsightsEndpointIsSqlFreeAndUsesExistingCrmData(): void
    {
        $endpoint = $this->read('api/merchant/crm-performance-insights.php');
        self::assertStringContainsString('campaign_events', $endpoint);
        self::assertStringContainsString('campaign_contacts', $endpoint);
        self::assertStringContainsString('wallet_items', $endpoint);
        self::assertStringContainsString('crm.campaign_builder.launched', $endpoint);
        self::assertStringContainsString('crm.bulk_action.result', $endpoint);
        self::assertStringNotContainsString('CREATE TABLE', $endpoint);
        self::assertStringNotContainsString('ALTER TABLE', $endpoint);
    }

    public function testInsightsEndpointIsReadOnlyMerchantScoped(): void
    {
        $endpoint = $this->read('api/merchant/crm-performance-insights.php');
        self::assertStringContainsString('mg_require_method(\'GET\')', $endpoint);
        self::assertStringContainsString('merchant.campaigns.view', $endpoint);
        self::assertStringContainsString('mg_merchant_ensure_workspace', $endpoint);
        self::assertStringContainsString('schema_ready', $endpoint);
    }

    public function testInsightsUiIsLoadedAndStyled(): void
    {
        $loader = $this->read('assets/js/merchant-crm-reward-invite-operations.js');
        $ui = $this->read('assets/js/merchant-crm-performance-insights.js');
        $css = $this->read('assets/css/merchant-crm-command-center.css');
        self::assertStringContainsString('merchant-crm-performance-insights.js', $loader);
        self::assertStringContainsString('data-crm-tab-target\',\'insights\'', $ui);
        self::assertStringContainsString('data-crm-tab-panel\',\'insights\'', $ui);
        self::assertStringContainsString('/api/merchant/crm-performance-insights.php', $ui);
        self::assertStringContainsString('data-crm-insights-list', $ui);
        self::assertStringContainsString('data-crm-insights-segments', $ui);
        self::assertStringContainsString('mg-crm-insights-grid', $css);
        self::assertStringContainsString('mg-crm-insight-chips', $css);
    }

    public function testInsightsActionLauncherPrefillsBuilderForReview(): void
    {
        $ui = $this->read('assets/js/merchant-crm-performance-insights.js');
        $builder = $this->read('assets/js/merchant-crm-campaign-builder.js');
        $css = $this->read('assets/css/merchant-crm-command-center.css');
        self::assertStringContainsString('data-crm-insight-action', $ui);
        self::assertStringContainsString('mg:crm-builder:prefill', $ui);
        self::assertStringContainsString('action_exceptions', $ui);
        self::assertStringContainsString('mg:crm-action-history:refresh', $ui);
        self::assertStringContainsString('prefillFromInsight', $builder);
        self::assertStringContainsString('Insight loaded into Campaign Builder for review', $builder);
        self::assertStringContainsString('is-insight-prefilled', $builder);
        self::assertStringContainsString('mg-crm-insight-actions', $css);
    }

    public function testDraftReviewCenterUsesExistingBuilderDrafts(): void
    {
        $ui = $this->read('assets/js/merchant-crm-performance-insights.js');
        $drafts = $this->read('assets/js/merchant-crm-draft-review-center.js');
        $css = $this->read('assets/css/merchant-crm-command-center.css');
        self::assertStringContainsString('merchant-crm-draft-review-center.js', $ui);
        self::assertStringContainsString('data-crm-tab-target\',\'drafts\'', $drafts);
        self::assertStringContainsString('data-crm-tab-panel\',\'drafts\'', $drafts);
        self::assertStringContainsString('/api/merchant/crm-campaign-builder.php', $drafts);
        self::assertStringContainsString('data-crm-draft-needs', $drafts);
        self::assertStringContainsString('data-crm-draft-ready', $drafts);
        self::assertStringContainsString('data-crm-draft-insights', $drafts);
        self::assertStringContainsString('mgCrmDraftReviewState', $drafts);
        self::assertStringContainsString('quality', $drafts);
        self::assertStringContainsString('mg:crm-builder:prefill', $drafts);
        self::assertStringContainsString('mg-crm-draft-review-grid', $css);
        self::assertStringContainsString('mg-crm-draft-kpis', $css);
    }
}
