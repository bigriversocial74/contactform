<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage15PsrDemandIntelligenceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testSchemaDefinesPsrSnapshotsAndAgentSignals(): void
    {
        $sql = $this->read('database/stage_15_psr_demand_intelligence.sql');
        foreach (['purchase_signal_records', 'purchase_signal_events', 'demand_scope_snapshots', 'demand_agent_signals'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        foreach (['outstanding', 'redeemed', 'expired', 'canceled'] as $state) {
            self::assertStringContainsString("'{$state}'", $sql);
        }
        self::assertStringContainsString('uq_psr_user_idempotency', $sql);
        self::assertStringContainsString('stage_15_psr_demand_intelligence', $sql);
    }

    public function testPsrSupportsFutureVisitsCommittedDemandAndCanonicalScopes(): void
    {
        $source = $this->read('api/demand/_demand.php');
        foreach (['future_visit', 'purchase_intent', 'committed_demand', 'gift_interest', 'repeat_visit', 'reservation_interest'] as $type) {
            self::assertStringContainsString("'{$type}'", $source);
        }
        self::assertStringContainsString('merchant_workspaces', $source);
        self::assertStringContainsString('merchant_locations', $source);
        self::assertStringContainsString('catalog_products', $source);
        self::assertStringContainsString('Product and location belong to different merchants.', $source);
    }

    public function testPsrCreationUsesExactRequestIdempotencyAndAppendOnlyEvents(): void
    {
        $source = $this->read('api/demand/_demand.php');
        self::assertStringContainsString('Idempotency key is already bound to another purchase signal.', $source);
        self::assertStringContainsString('purchase_signal_events', $source);
        self::assertStringContainsString("'created'", $source);
        self::assertStringContainsString('confidence_score', $source);
        self::assertStringContainsString('expected_from', $source);
    }

    public function testPsrLifecycleEnforcesOwnerAndMerchantAuthority(): void
    {
        $source = $this->read('api/demand/psr-transition.php');
        self::assertStringContainsString('$isOwner', $source);
        self::assertStringContainsString('$isMerchant', $source);
        self::assertStringContainsString('Only the signal owner can perform this transition.', $source);
        self::assertStringContainsString('Only the matching merchant can redeem this purchase signal.', $source);
        self::assertStringContainsString('FOR UPDATE', $source);
    }

    public function testRedemptionCanBindToCanonicalMicrogiftRedemption(): void
    {
        $source = $this->read('api/demand/_demand.php');
        self::assertStringContainsString('microgift_redemptions', $source);
        self::assertStringContainsString('microgift_instances', $source);
        self::assertStringContainsString("r.status='completed'", $source);
        self::assertStringContainsString('Redemption does not match purchase signal merchant.', $source);
    }

    public function testSnapshotsUseDeterministicWindowAuthority(): void
    {
        $source = $this->read('api/demand/_snapshot.php');
        self::assertStringContainsString('mg_demand_build_windowed_snapshot(', $source);
        self::assertStringContainsString('mg_demand_window_predicate()', $source);
        self::assertStringContainsString('outstanding_count', $source);
        self::assertStringContainsString('committed_value', $source);
        self::assertStringContainsString('future_visits', $source);
        self::assertStringContainsString('redeemed_count', $source);
        self::assertStringContainsString('$velocity(7)', $source);
        self::assertStringContainsString('$velocity(30)', $source);
        self::assertStringContainsString('utc_half_open_overlap', $source);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $source);
    }

    public function testAgentSignalsAreDeterministicAndDedupeSafe(): void
    {
        $source = $this->read('api/demand/_demand.php');
        foreach (['velocity_spike', 'committed_demand', 'future_visit_cluster'] as $signal) {
            self::assertStringContainsString("'{$signal}'", $source);
        }
        self::assertStringContainsString('dedupe_key', $source);
        self::assertStringContainsString('recommendation_json', $source);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $source);
    }

    public function testMerchantDashboardIsScopedAndWindowed(): void
    {
        $source = $this->read('api/merchant/demand-dashboard.php');
        self::assertStringContainsString("mg_require_permission('demand.dashboard.view')", $source);
        self::assertStringContainsString('mw.merchant_user_id=?', $source);
        self::assertStringContainsString('merchant_user_id=?', $source);
        self::assertStringContainsString('demand_scope_snapshots', $source);
        self::assertStringContainsString('demand_agent_signals', $source);
        self::assertStringContainsString('mg_demand_window_predicate()', $source);
        self::assertStringContainsString("'window_start'", $source);
        self::assertStringContainsString("'window_end'", $source);
    }

    public function testSnapshotProcessorUsesCanonicalWindowedService(): void
    {
        $source = $this->read('scripts/build_demand_snapshots.php');
        self::assertStringContainsString('mg_demand_snapshot_scopes(', $source);
        self::assertStringContainsString('mg_demand_build_windowed_snapshot(', $source);
        self::assertStringContainsString('mg_demand_emit_agent_signals(', $source);
        self::assertStringContainsString('$argv[2]', $source);
        self::assertStringContainsString('$pdo->beginTransaction()', $source);
        self::assertStringContainsString('$pdo->rollBack()', $source);
    }
}
