<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignCrmV12ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCanonicalContactAndSeparatedCampaignBoundaries(): void
    {
        $sql = file_get_contents($this->root . '/database/20260722_creator_campaign_crm_lifecycle_v12_single_install.sql');
        $service = file_get_contents($this->root . '/includes/creator-campaigns/crm-service.php');
        self::assertIsString($sql);
        self::assertStringContainsString('merchant_crm_contact_creator_campaigns', $sql);
        self::assertStringContainsString('REFERENCES merchant_crm_contacts(id)', $sql);
        self::assertStringContainsString('REFERENCES creator_campaigns(id)', $sql);
        self::assertStringNotContainsString('ALTER TABLE merchant_crm_contact_campaigns', $sql);
        self::assertStringContainsString("'campaign_id'=>null", $service);
        self::assertStringContainsString("'campaign_type'=>'creator_campaign'", $service);
    }

    public function testRealtimeHooksAndPrivacyBoundary(): void
    {
        $participation = file_get_contents($this->root . '/includes/creator-campaigns/participation-repository.php');
        $tracking = file_get_contents($this->root . '/includes/creator-campaigns/tracking-service.php');
        $definitions = file_get_contents($this->root . '/includes/creator-campaigns/crm-definitions.php');
        self::assertGreaterThanOrEqual(3, substr_count((string)$participation, 'mg_creator_campaign_crm_project_participation_event_safe'));
        self::assertGreaterThanOrEqual(2, substr_count((string)$tracking, 'mg_creator_campaign_crm_project_tracking_event_safe'));
        self::assertStringContainsString("'identity_unresolved'", file_get_contents($this->root . '/includes/creator-campaigns/crm-service.php'));
        self::assertStringContainsString('crm_identity', (string)$definitions);
        self::assertStringNotContainsString('visitor_hash', (string)$definitions);
    }

    public function testProjectionIsIdempotentAndTransactionIsolated(): void
    {
        $repository = file_get_contents($this->root . '/includes/creator-campaigns/crm-repository.php');
        $service = file_get_contents($this->root . '/includes/creator-campaigns/crm-service.php');
        self::assertStringContainsString('uq_merchant_crm_cc_event_source', file_get_contents($this->root . '/database/20260722_creator_campaign_crm_lifecycle_v12_single_install.sql'));
        self::assertStringContainsString('mg_creator_campaign_crm_reserve_projection', (string)$repository);
        self::assertStringContainsString('SAVEPOINT ', (string)$service);
        self::assertStringContainsString('ROLLBACK TO SAVEPOINT', (string)$service);
        self::assertStringContainsString('mg_creator_campaign_crm_reconcile', (string)$service);
    }

    public function testMerchantWorkspacesExposeCanonicalRelationships(): void
    {
        $api = file_get_contents($this->root . '/api/merchant/merchant-crm.php');
        $bridge = file_get_contents($this->root . '/assets/js/merchant-crm-creator-campaign-bridge-v12.js');
        $workspace = file_get_contents($this->root . '/includes/merchant-creator-campaign-crm-view.php');
        self::assertStringContainsString('mg_merchant_crm_creator_campaign_enrich_directory', (string)$api);
        self::assertStringContainsString('canonical_only: true', (string)$bridge);
        self::assertStringContainsString('data-cccrm-sync', (string)$workspace);
        self::assertStringContainsString('relationship_type', (string)$workspace);
        self::assertStringContainsString('/merchant-creator-crm.php', file_get_contents($this->root . '/includes/merchant-creator-campaigns-view.php'));
    }
}
