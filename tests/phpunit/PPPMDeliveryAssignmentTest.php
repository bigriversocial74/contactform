<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PPPMDeliveryAssignmentTest extends TestCase
{
    public function testSchemaDefinesOperationalTables(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_pppm_delivery_assignment.sql');
        self::assertIsString($sql);
        foreach (['pppm_assignments','pppm_transfer_requests','pppm_delivery_schedules','pppm_delivery_attempts','pppm_provider_events'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('uq_pppm_provider_external_event', $sql);
        self::assertStringNotContainsString('PRIMARY KEY (pppm_item_id,', $sql);
    }

    public function testDeliveryMigrationCommandIsPresent(): void
    {
        $runner = file_get_contents(dirname(__DIR__, 2) . '/scripts/run_stage3_delivery.php');
        self::assertIsString($runner);
        self::assertStringContainsString('stage_3_pppm_delivery_assignment.sql', $runner);
        self::assertStringContainsString('mg_db()->exec($sql)', $runner);
    }

    public function testAssignmentAndTransferEnforceOwnership(): void
    {
        $assign = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/assign.php');
        $transfer = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/transfer.php');
        self::assertIsString($assign);
        self::assertIsString($transfer);
        self::assertStringContainsString("mg_require_permission('pppm.assign')", $assign);
        self::assertStringContainsString('Only the current owner can transfer this item.', $transfer);
        self::assertStringContainsString('Transfer recipient does not match.', $transfer);
        self::assertStringContainsString('hash_equals', $transfer);
    }

    public function testSchedulingDispatchAndCallbacksAreSeparated(): void
    {
        $schedule = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/schedule-delivery.php');
        $dispatch = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/dispatch-delivery.php');
        $callback = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/provider-callback.php');
        self::assertIsString($schedule);
        self::assertIsString($dispatch);
        self::assertIsString($callback);
        self::assertStringContainsString('pppm_delivery_schedules', $schedule);
        self::assertStringContainsString('pppm_delivery_attempts', $dispatch);
        self::assertStringContainsString('MG_DELIVERY_WEBHOOK_SECRET', $callback);
        self::assertStringContainsString('Provider event already processed.', $callback);
        self::assertStringContainsString("'retry_scheduled'", $callback);
    }

    public function testStageNotesPreserveStageFourBoundary(): void
    {
        $notes = file_get_contents(dirname(__DIR__, 2) . '/docs/stage-3-pppm-delivery-assignment-adjustment.md');
        self::assertIsString($notes);
        self::assertStringContainsString('This package remains part of Stage 3.', $notes);
        self::assertStringContainsString('Stage 4 carry-forward', $notes);
    }
}
