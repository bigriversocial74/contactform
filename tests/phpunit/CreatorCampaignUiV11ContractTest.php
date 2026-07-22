<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignUiV11ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testApprovedScreenCompositionsArePresent(): void
    {
        $screens = [
            'includes/merchant-creator-campaigns-view.php' => 'merchant-overview',
            'includes/merchant-creator-campaign-builder-view.php' => 'campaign-builder',
            'includes/merchant-creator-campaign-detail-view.php' => 'merchant-campaign-detail',
            'includes/merchant-creator-campaign-participation-view.php' => 'merchant-applications-review',
            'includes/merchant-creator-campaign-deliverables-view.php' => 'merchant-content-review',
            'includes/creator-campaigns-participation-view.php' => 'creator-discovery-active-workspace',
            'includes/creator-campaign-deliverables-view.php' => 'creator-active-campaign-workspace',
        ];

        foreach ($screens as $path => $marker) {
            $content = file_get_contents($this->root . '/' . $path);
            self::assertIsString($content, $path);
            self::assertStringContainsString('data-cc-screen="' . $marker . '"', $content, $path);
        }
    }

    public function testPresentationPreservesExistingRuntimeHooks(): void
    {
        $overview = file_get_contents($this->root . '/includes/merchant-creator-campaigns-view.php');
        $builder = file_get_contents($this->root . '/includes/merchant-creator-campaign-builder-view.php');
        $participation = file_get_contents($this->root . '/includes/merchant-creator-campaign-participation-view.php');
        $deliverables = file_get_contents($this->root . '/includes/merchant-creator-campaign-deliverables-view.php');

        self::assertStringContainsString('data-cc-list', $overview);
        self::assertStringContainsString('data-cc-form', $builder);
        self::assertStringContainsString('data-ccp-review-form', $participation);
        self::assertStringContainsString('data-ccdv-review-form', $deliverables);
    }

    public function testCampaignDetailUsesAuthoritativeReadOnlySources(): void
    {
        $controller = file_get_contents($this->root . '/assets/js/merchant-creator-campaign-detail-v11.js');
        self::assertStringContainsString('/api/merchant/creator-campaigns.php?action=detail', $controller);
        self::assertStringContainsString('/api/merchant/creator-campaign-analytics.php', $controller);
        self::assertStringNotContainsString('localStorage', $controller);
        self::assertStringNotContainsString('sessionStorage', $controller);
        self::assertStringNotContainsString("method: 'POST'", $controller);
    }

    public function testPhaseElevenRequiresNoSqlOrNewMockupAssets(): void
    {
        self::assertFileDoesNotExist($this->root . '/database/20260722_creator_campaign_ui_v11.sql');
        $docs = file_get_contents($this->root . '/docs/creator-campaigns/CREATOR_CAMPAIGN_PHASE11_SIX_SCREEN_UI.md');
        self::assertStringContainsString('**No SQL required.**', $docs);
        self::assertStringContainsString('No new mockup or decorative image assets', $docs);
    }
}
