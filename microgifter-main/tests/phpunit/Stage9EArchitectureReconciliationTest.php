<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9EArchitectureReconciliationTest extends TestCase
{
    public function testCanonicalPppmOwnershipServiceExistsAndUpdatesPppmBeforeMicrogiftClaimsComplete(): void
    {
        $ownership = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/_ownership.php');
        $microgift = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/_lifecycle.php');
        self::assertIsString($ownership);
        self::assertIsString($microgift);
        self::assertStringContainsString('function mg_pppm_transfer_owner_canonical', $ownership);
        self::assertStringContainsString('UPDATE pppm_items SET owner_user_id=?', $ownership);
        self::assertStringContainsString('mg_entitlements_sync_pppm_owner', $ownership);
        self::assertStringContainsString('pppm.owner_transferred', $ownership);
        self::assertStringContainsString('mg_pppm_transfer_owner_canonical', $microgift);
    }

    public function testMicrogiftRedemptionUsesCanonicalPppmRedeemService(): void
    {
        $pppm = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/_pppm.php');
        $microgift = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/_lifecycle.php');
        self::assertIsString($pppm);
        self::assertIsString($microgift);
        self::assertStringContainsString('function mg_pppm_redeem', $pppm);
        self::assertStringContainsString('pppm.redeemed', $pppm);
        self::assertStringContainsString('mg_pppm_redeem($pdo', $microgift);
        self::assertStringNotContainsString("UPDATE pppm_items SET status='redeemed'", $microgift);
    }

    public function testStageOneThroughNineEventCatalogExistsAndCoversHighRiskEvents(): void
    {
        $catalog = file_get_contents(dirname(__DIR__, 2) . '/docs/contracts/event_catalog_stage1_9.yaml');
        self::assertIsString($catalog);
        foreach (['pppm.owner_transferred','pppm.redeemed','entitlement.granted','microgift.claim_completed','microgift.redemption_completed'] as $event) {
            self::assertStringContainsString($event . ':', $catalog);
        }
        self::assertStringContainsString('raw credentials', $catalog);
        self::assertStringContainsString('validation_policy', $catalog);
    }

    public function testStageOneThroughNineApiContractRegistryExists(): void
    {
        $contracts = file_get_contents(dirname(__DIR__, 2) . '/docs/contracts/api_contracts_stage1_9.yaml');
        self::assertIsString($contracts);
        foreach (['POST /api/microgifts/claim.php','POST /api/microgifts/redeem.php','GET /api/account/microgifts.php','GET /api/merchant/microgifts.php'] as $endpoint) {
            self::assertStringContainsString($endpoint . ':', $contracts);
        }
        self::assertStringContainsString('mg_pppm_transfer_owner_canonical', $contracts);
        self::assertStringContainsString('mg_pppm_redeem', $contracts);
        self::assertStringContainsString('never_return', $contracts);
    }

    public function testStage9EReconciliationDocumentExists(): void
    {
        $doc = file_get_contents(dirname(__DIR__, 2) . '/docs/stages/stage_9e_architecture_reconciliation.md');
        self::assertIsString($doc);
        self::assertStringContainsString('Stage 10 should not begin', $doc);
        self::assertStringContainsString('PPPM is the canonical issued-unit and ownership source', $doc);
    }
}
