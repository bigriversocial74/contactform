<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10AOfficialPlanReconciliationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $relative): string
    {
        $content = file_get_contents($this->root . '/' . $relative);
        self::assertIsString($content, 'Unable to read ' . $relative);
        return $content;
    }

    public function testReconciliationDocumentsExist(): void
    {
        self::assertFileExists($this->root . '/docs/stages/stage_10a_official_plan_reconciliation.md');
        self::assertFileExists($this->root . '/docs/stages/stage_10a_requirement_matrix.md');
        self::assertFileExists($this->root . '/docs/stages/stage_10_adapted_implementation_plan.md');
    }

    public function testAdaptedPlanPreservesCanonicalSourcesOfTruth(): void
    {
        $plan = $this->read('docs/stages/stage_10a_official_plan_reconciliation.md');
        self::assertStringContainsString('merchant_claim_codes', $plan);
        self::assertStringContainsString('pppm_items', $plan);
        self::assertStringContainsString('entitlements', $plan);
        self::assertStringContainsString('Stage 7 ledger remains the money source', $plan);
        self::assertStringContainsString('Do not create a second claim engine', $plan);
    }

    public function testReviewRecognizesExistingCanonicalClaimAndRedemptionServices(): void
    {
        $lifecycle = $this->read('api/microgifts/_lifecycle.php');
        self::assertStringContainsString('function mg_microgift_claim', $lifecycle);
        self::assertStringContainsString('function mg_microgift_redeem', $lifecycle);
        self::assertStringContainsString('mg_pppm_transfer_owner_canonical', $lifecycle);
        self::assertStringContainsString('mg_pppm_redeem', $lifecycle);
        self::assertStringContainsString('FOR UPDATE', $lifecycle);
    }

    public function testStageThreeLocationClaimAuthorityFoundationExists(): void
    {
        $sql = $this->read('database/stage_3_merchant_claim_codes.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS merchant_locations', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS merchant_claim_codes', $sql);
        self::assertStringContainsString('code_hash CHAR(64)', $sql);
        self::assertStringContainsString('code_last4 CHAR(4)', $sql);
        self::assertStringContainsString("merchant.gifts.redeem", $sql);
    }

    public function testRequirementMatrixIdentifiesCriticalStageTenGaps(): void
    {
        $matrix = $this->read('docs/stages/stage_10a_requirement_matrix.md');
        foreach ([
            'Location claim-code hash validation',
            'Claim attempts logged, success and failure',
            'Inbox Received to Claimed',
            'Authorized merchant staff',
            'Concurrent claim prevention',
        ] as $requirement) {
            self::assertStringContainsString($requirement, $matrix);
        }
    }

    public function testAdaptedPlanDoesNotBuildOutOfScopeSystems(): void
    {
        $plan = $this->read('docs/stages/stage_10_adapted_implementation_plan.md');
        self::assertStringContainsString('no financial implementation', $plan);
        self::assertStringContainsString('Future Demand/PSR redeemed source event only', $this->read('docs/stages/stage_10a_official_plan_reconciliation.md'));
        self::assertStringNotContainsString('build subscription billing', strtolower($plan));
        self::assertStringNotContainsString('build social feed', strtolower($plan));
    }
}
