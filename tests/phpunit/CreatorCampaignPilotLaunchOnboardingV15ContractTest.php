<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignPilotLaunchOnboardingV15ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testMigrationIsAdditiveNativeAndAddsNoMcpAuthority(): void
    {
        $sql = $this->source('database/20260723_creator_campaign_pilot_launch_onboarding_v15_single_install.sql');
        self::assertSame(3, substr_count($sql, 'CREATE TABLE IF NOT EXISTS creator_campaign_'));
        self::assertStringContainsString('creator_campaign_merchant_onboarding', $sql);
        self::assertStringContainsString('creator_campaign_onboarding_events', $sql);
        self::assertStringContainsString('creator_campaign_onboarding_receipts', $sql);
        self::assertStringNotContainsString('mcp_scope_catalog', $sql);
        self::assertStringNotContainsString('mcp_connections', $sql);
    }

    public function testOnboardingHasNineNativeMerchantStepsAndNoMcpSetupStep(): void
    {
        $bootstrap = $this->source('includes/creator-campaign-onboarding/bootstrap.php');
        $page = $this->source('includes/creator-campaign-onboarding/page-view.php')
            . $this->source('includes/creator-campaign-onboarding/page-view-foundation.php')
            . $this->source('includes/creator-campaign-onboarding/page-view-guardrails.php')
            . $this->source('includes/creator-campaign-onboarding/page-view-launch.php');
        foreach ([
            'Pilot enrollment',
            'Business and campaign profile',
            'Product and offer readiness',
            'Compensation and budget guardrails',
            'Creator eligibility preferences',
            'Operator and approval roles',
            'First campaign guided launch',
            'Production smoke test',
            'Launch dashboard',
        ] as $label) {
            self::assertStringContainsString($label, $bootstrap . $page);
        }
        self::assertStringNotContainsString('MCP connection setup', $page);
        self::assertStringNotContainsString('Create grant', $page);
    }

    public function testProductReadinessUsesCanonicalCatalogEvidence(): void
    {
        $source = $this->source('includes/creator-campaign-onboarding/repository.php');
        foreach ([
            'catalog_products',
            'catalog_product_versions',
            'catalog_product_version_assets',
            'catalog_assets',
            'catalog_pppm_templates',
            "'published'",
            "'price'",
            "'image'",
            "'claim_rules'",
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testFinancialDefaultsArePlanningOnlyAndBounded(): void
    {
        $service = $this->source('includes/creator-campaign-onboarding/service.php');
        $readiness = $this->source('includes/creator-campaign-onboarding/readiness.php');
        self::assertStringContainsString('mg_creator_campaign_onboarding_financial_exposure', $service);
        self::assertStringContainsString('campaign_budget_minor', $service);
        self::assertStringContainsString('per_creator_limit_minor', $service);
        self::assertStringContainsString("'merchant_approval_required'=>true", $service);
        self::assertStringContainsString('financialWithinCeiling', $readiness);
        self::assertStringNotContainsString('payment_intent', $service);
        self::assertStringNotContainsString('stripe', strtolower($service));
    }

    public function testFirstCampaignCreationUsesCanonicalServicesWithoutPublication(): void
    {
        $service = $this->source('includes/creator-campaign-onboarding/service.php');
        self::assertStringContainsString('mg_creator_campaign_create_draft', $service);
        self::assertGreaterThanOrEqual(3, substr_count($service, 'mg_creator_campaign_builder_save_step'));
        self::assertStringContainsString("'automatic_acceptance'=>false", $service);
        self::assertStringNotContainsString('mg_creator_campaign_transition_status(', $service);
    }

    public function testSmokeTestIsReadOnlyAndCreatesDurableEvidence(): void
    {
        $source = $this->source('includes/creator-campaign-onboarding/smoke-test.php');
        self::assertStringContainsString('creator_campaign_onboarding_receipts', $source);
        self::assertStringContainsString('snapshot_hash', $source);
        self::assertStringContainsString("'automatic_execution'=>false", $source);
        self::assertStringContainsString("'campaign_published'=>false", $source);
        self::assertStringContainsString("'payment_provider_called'=>false", $source);
        self::assertStringNotContainsString('mg_creator_campaign_transition_status(', $source);
        self::assertStringNotContainsString('mg_mcp_', $source);
    }

    public function testActivationDoesNotPublishCampaignOrEnableAutomation(): void
    {
        $service = $this->source('includes/creator-campaign-onboarding/service.php');
        self::assertStringContainsString('mg_creator_campaign_onboarding_activate', $service);
        self::assertStringContainsString("SET status='active',current_step=9", $service);
        self::assertStringContainsString("'automatic_execution'=>false", $service);
        self::assertStringContainsString("'campaign_published'=>false", $service);
        self::assertStringNotContainsString('mcp_automation_grants', $service);
        self::assertStringNotContainsString('mg_creator_campaign_transition_status(', $service);
    }

    public function testPhase14CockpitLinksNativeOnboardingProgress(): void
    {
        $pilot = $this->source('includes/creator-campaign-pilot/page-view.php');
        $card = $this->source('includes/creator-campaign-pilot/page-view-onboarding.php');
        self::assertStringContainsString("require __DIR__ . '/page-view-onboarding.php'", $pilot);
        self::assertStringContainsString('Phase 15 · Native merchant launch', $card);
        self::assertStringContainsString('MCP authority remains separate', $card);
        self::assertStringContainsString('/account-creator-campaign-onboarding.php', $card);
    }
}
