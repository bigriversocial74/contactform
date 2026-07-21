<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignMerchantBuilderV2ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function source(string $path): string
    {
        $content = file_get_contents($this->root . '/' . $path);
        self::assertIsString($content, $path);
        return $content;
    }

    private function builderSource(): string
    {
        $parts = [];
        foreach (['core','options','save','validation','duplicate'] as $part) {
            $parts[] = $this->source('includes/creator-campaigns/builder-' . $part . '.php');
        }
        return implode("\n", $parts);
    }

    public function testMigrationCreatesTypedBuilderAndQuestionsWithoutPrematureFinance(): void
    {
        $sql = $this->source('database/20260721_creator_campaign_merchant_builder_v2.sql');
        self::assertStringContainsString('campaign_focus ENUM', $sql);
        self::assertStringContainsString('creator_campaign_application_questions', $sql);
        self::assertStringContainsString('builder_validation_json JSON', $sql);
        self::assertStringNotContainsString('commission_basis', $sql);
    }

    public function testBuilderUsesWorkspaceAuthorizationAndOptimisticLocks(): void
    {
        $source = $this->builderSource();
        self::assertStringContainsString('mg_creator_campaign_actor_context', $source);
        self::assertStringContainsString('workspace_owner_user_id', $source);
        self::assertStringContainsString('lock_version=lock_version+1', $source);
        self::assertStringContainsString('The campaign changed in another request.', $source);
    }

    public function testParticipationAndAgreementDependenciesFailClosed(): void
    {
        $builder = $this->builderSource();
        $status = $this->source('includes/creator-campaigns/status-service.php');
        self::assertStringContainsString('Automatic acceptance is unavailable until Creator Participation is installed.', $builder);
        self::assertStringContainsString('creator_campaign_agreement_versions', $status);
        self::assertStringContainsString('Campaign publication will unlock when the Agreement phase is installed.', $status);
    }

    public function testApiAndUiUseSeparateCreatorCampaignRoutes(): void
    {
        $api = $this->source('api/merchant/creator-campaigns.php');
        $nav = $this->source('includes/merchant-navigation.php');
        $view = $this->source('includes/merchant-creator-campaign-builder-view.php');
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $api);
        self::assertStringContainsString("'creator_campaigns'", $nav);
        self::assertStringContainsString("10=>'Review'", $view);
        self::assertStringContainsString('name="automatic_acceptance" disabled', $view);
    }

    public function testValidationAndMySqlLifecycleAreRequiredByCi(): void
    {
        $workflow = $this->source('.github/workflows/creator-campaign-merchant-builder-v2.yml');
        self::assertStringContainsString("php: ['8.2','8.3']", $workflow);
        self::assertStringContainsString('node --check assets/js/merchant-creator-campaigns.js', $workflow);
        self::assertStringContainsString('node --check assets/js/merchant-creator-campaign-builder.js', $workflow);
        self::assertStringContainsString('validate_creator_campaign_merchant_builder_v2_mysql.php', $workflow);
        self::assertStringContainsString('composer migrate', $workflow);
    }
}
