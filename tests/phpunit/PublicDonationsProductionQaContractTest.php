<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public-donations-reconciliation.php';

final class PublicDonationsProductionQaContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $content = file_get_contents($this->root . '/' . $path);
        self::assertIsString($content, $path);
        return $content;
    }

    public function testRepairModesAreExplicitAndBounded(): void
    {
        self::assertSame([
            'counters',
            'batch_totals',
            'recalled_visibility',
            'assignments',
        ], MG_PUBLIC_DONATIONS_REPAIR_MODES);
        self::assertSame(MG_PUBLIC_DONATIONS_REPAIR_MODES, mg_public_donations_reconcile_modes('safe'));
        self::assertSame(['counters','assignments'], mg_public_donations_reconcile_modes('counters,assignments'));
        self::assertSame([], mg_public_donations_reconcile_modes(null));
    }

    public function testInvalidRepairModeAndReferencesFailClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_public_donations_reconcile_modes('ownership');
    }

    public function testLimitsAndFiltersAreBounded(): void
    {
        self::assertSame(1, mg_public_donations_reconcile_limit(0));
        self::assertSame(1000, mg_public_donations_reconcile_limit(5000));
        self::assertSame(100, mg_public_donations_reconcile_limit('bad'));

        $filters = mg_public_donations_reconcile_filters([
            'merchant_id' => 42,
            'campaign' => 'summer-support',
            'operation' => '00000000-0000-4000-a000-000000000001',
            'limit' => 250,
        ]);
        self::assertSame(42, $filters['merchant_id']);
        self::assertSame('summer-support', $filters['campaign']);
        self::assertSame(250, $filters['limit']);
    }

    public function testCliDefaultsToDryRunAndRequiresMerchantScope(): void
    {
        $cli = $this->read('scripts/reconcile_public_donations.php');
        self::assertStringContainsString('Dry-run is the default', $cli);
        self::assertStringContainsString("'merchant:'", $cli);
        self::assertStringContainsString("'dry-run'", $cli);
        self::assertStringContainsString("'repair::'", $cli);
        self::assertStringContainsString('--dry-run cannot be combined with --repair.', $cli);
        self::assertStringContainsString('--merchant must be a positive integer.', $cli);
    }

    public function testReportOnlyDefectsCannotBeRepairedBySourceContract(): void
    {
        $core = $this->read('includes/public-donations-reconciliation.php');
        self::assertStringContainsString("'missing_attribution'", $core);
        self::assertStringContainsString("'missing_links'", $core);
        self::assertStringContainsString("'ownership_mismatches'", $core);
        self::assertStringNotContainsString('INSERT INTO campaign_donation_rewards', $core);
        self::assertDoesNotMatchRegularExpression(
            '/UPDATE\s+(?:wallet_items|pppm_items|microgift_instances)\s+SET\s+(?:user_id|owner_user_id|recipient_user_id)/i',
            $core
        );
    }

    public function testSafeRepairsAreTransactionalAndAudited(): void
    {
        $core = $this->read('includes/public-donations-reconciliation.php');
        foreach ([
            'beginTransaction()',
            'rollBack()',
            'GET_LOCK(?,10)',
            'RELEASE_LOCK(?)',
            'public_donations.reconcile',
            "'checksum'",
            "if (empty(\$issue['repairable'])) continue;",
        ] as $contract) {
            self::assertStringContainsString($contract, $core);
        }
    }

    public function testAcceptanceFixtureContainsExactRequiredLifecycle(): void
    {
        $fixture = $this->read('scripts/test_public_donations_production_qa_mysql.php');
        foreach ([
            "'initial_inventory' => 100",
            "'allocations' => [10,20,25]",
            "'gross_allocated' => 55",
            "'regifted' => 4",
            "'claimed' => 2",
            "'redeemed' => 1",
            "'recalled' => 6",
            "'net_allocated' => 49",
            "'remaining_inventory' => 51",
        ] as $contract) {
            self::assertStringContainsString($contract, $fixture);
        }
        self::assertStringContainsString("'repair' => 'safe'", $fixture);
        self::assertStringContainsString("'dashboard'", $fixture);
        self::assertStringContainsString("'public_campaign'", $fixture);
        self::assertStringContainsString("'profile_community_tab'", $fixture);
    }

    public function testRunbookNeverConflatesMergeUploadAndSqlImport(): void
    {
        $runbook = $this->read('docs/production/public-donations-production-runbook.md');
        self::assertStringContainsString('Code upload status', $runbook);
        self::assertStringContainsString('SQL import status', $runbook);
        self::assertStringContainsString('Do not mark deployment complete', $runbook);
        self::assertStringContainsString('MG_PUBLIC_DONATIONS_FEATURE_STATE=disabled', $runbook);
        self::assertStringContainsString('admin_only', $runbook);
        self::assertStringContainsString('selected_merchants', $runbook);
        self::assertStringContainsString('enabled', $runbook);
        self::assertStringContainsString('Smoke test', $runbook);
        self::assertStringContainsString('Rollback', $runbook);
        self::assertStringNotContainsString('Deployment confirmed', $runbook);
        self::assertStringNotContainsString('deployed successfully', $runbook);
    }
}
