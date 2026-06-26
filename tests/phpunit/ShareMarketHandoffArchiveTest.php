<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/handoff-archives.php';

final class ShareMarketHandoffArchiveTest extends TestCase
{
    public function testHandoffHashIgnoresGeneratedTimestamp(): void
    {
        $left = ['handoff_ready' => true, 'generated_at' => '2026-01-01T00:00:00Z', 'checks' => [['passed' => true]]];
        $right = ['handoff_ready' => true, 'generated_at' => '2026-01-02T00:00:00Z', 'checks' => [['passed' => true]]];

        self::assertSame(mg_share_market_handoff_hash($left), mg_share_market_handoff_hash($right));
    }

    public function testArchiveNoteRejectsLongNotes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_share_market_handoff_note(str_repeat('x', 2200));
    }

    public function testArchiveRowAddsDriftComparison(): void
    {
        $handoff = ['handoff_ready' => true, 'checks' => []];
        $hash = mg_share_market_handoff_hash($handoff);
        $row = mg_share_market_handoff_row(['handoff_hash' => $hash, 'handoff_json' => json_encode($handoff)], $handoff);

        self::assertTrue($row['drift']['matches_current']);
        self::assertSame('matching', $row['drift']['drift_status']);
    }

    public function testMigrationIsRegistered(): void
    {
        $manifest = (string)file_get_contents(dirname(__DIR__, 2) . '/config/migrations.php');
        $migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/stage_24_buy_in_handoff_archives.sql');

        self::assertStringContainsString('stage_24_buy_in_handoff_archives.sql', $manifest);
        self::assertStringContainsString('share_market_handoff_archives', $migration);
    }

    public function testArchiveApiRequiresCsrfForWrites(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/handoff-archives.php');

        self::assertStringContainsString('mg_share_market_handoff_archives', $api);
        self::assertStringContainsString('mg_require_csrf_for_write', $api);
        self::assertStringContainsString('mg_share_market_save_handoff_archive', $api);
    }

    public function testAuditConsoleInjectsArchiveControls(): void
    {
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');

        self::assertStringContainsString('data-share-handoff-archive-panel', $js);
        self::assertStringContainsString('data-share-handoff-archive-form', $js);
        self::assertStringContainsString('/api/admin/share-market/handoff-archives.php', $js);
        self::assertStringContainsString('renderArchives', $js);
        self::assertStringContainsString('recordArchive', $js);
    }
}
