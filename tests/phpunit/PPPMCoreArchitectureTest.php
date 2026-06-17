<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PPPMCoreArchitectureTest extends TestCase
{
    public function testCoreMigrationDefinesSourceNeutralArchitecture(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_pppm_core.sql');

        self::assertIsString($sql);
        foreach ([
            'pppm_sources',
            'pppm_source_events',
            'pppm_issuance_requests',
            'pppm_items',
            'pppm_item_events',
            'pppm_item_snapshots',
            'pppm_legacy_gift_map',
            'pppm_demand_facts',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('UNIQUE KEY uq_pppm_source_external_event (source_id, external_event_id)', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_pppm_items_request_unit (issuance_request_id, unit_sequence)', $sql);
        self::assertStringContainsString("ENUM('gift','prize','reward','voucher','entitlement','reservation','credit','other')", $sql);
        self::assertStringContainsString("ENUM('customer_purchase','merchant_funded','sponsor_funded','platform_funded','promotional','earned_reward','free','other')", $sql);
    }

    public function testMigrationRunnerRegistersPppmCore(): void
    {
        $runner = file_get_contents(dirname(__DIR__, 2) . '/scripts/run_migrations.php');
        self::assertIsString($runner);
        self::assertStringContainsString("'stage_3_pppm_core.sql'", $runner);
    }

    public function testIngestionIsIdempotentAndExpandsQuantityIntoUnits(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/ingest.php');

        self::assertIsString($source);
        self::assertStringContainsString("mg_require_permission('pppm.ingest')", $source);
        self::assertStringContainsString('external_event_id', $source);
        self::assertStringContainsString('Source event already processed.', $source);
        self::assertStringContainsString('for ($sequence = 1; $sequence <= $quantity; $sequence++)', $source);
        self::assertStringContainsString('uq_pppm_source_external_event', file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_pppm_core.sql'));
        self::assertStringContainsString('unit_sequence', $source);
        self::assertStringContainsString('PPPM items issued.', $source);
    }

    public function testItemsAreScopedToAuthorizedParticipants(): void
    {
        $helper = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/_pppm.php');
        $items = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/items.php');

        self::assertIsString($helper);
        self::assertIsString($items);
        self::assertStringContainsString('issuer_user_id = ? OR merchant_user_id = ? OR owner_user_id = ? OR recipient_user_id = ?', $helper);
        self::assertStringContainsString("mg_require_permission('pppm.items.view')", $items);
        self::assertStringContainsString('pppm_item_events', $items);
    }

    public function testLifecycleTransitionsAreExplicitAndAppendOnly(): void
    {
        $transition = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/transition.php');
        $helper = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/_pppm.php');

        self::assertIsString($transition);
        self::assertIsString($helper);
        self::assertStringContainsString("mg_require_permission('pppm.items.manage')", $transition);
        self::assertStringContainsString('This PPPM lifecycle transition is not allowed.', $transition);
        self::assertStringContainsString('version_no = version_no + 1', $transition);
        self::assertStringContainsString('INSERT INTO pppm_item_events', $helper);
        self::assertStringContainsString('INSERT INTO pppm_item_snapshots', $helper);
    }

    public function testLegacyGiftCompatibilityIsNonDestructive(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/scripts/migrate_gifts_to_pppm.php');
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_pppm_core.sql');

        self::assertIsString($script);
        self::assertIsString($sql);
        self::assertStringContainsString('pppm_legacy_gift_map', $script);
        self::assertStringContainsString('legacy_gift_imported', $script);
        self::assertStringContainsString('LEFT JOIN pppm_legacy_gift_map', $script);
        self::assertStringNotContainsString('DELETE FROM gifts', $script);
        self::assertStringNotContainsString('DROP TABLE gifts', $sql);
    }

    public function testDemandIntelligenceFoundationIsIncluded(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_pppm_core.sql');

        self::assertIsString($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS pppm_demand_facts', $sql);
        self::assertStringContainsString('expected_value_cents', $sql);
        self::assertStringContainsString('expected_at', $sql);
        self::assertStringContainsString('fulfilled_at', $sql);
        self::assertStringContainsString('recipient_region', $sql);
    }
}
