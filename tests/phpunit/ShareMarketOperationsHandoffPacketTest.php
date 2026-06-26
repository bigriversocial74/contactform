<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/operations-handoff-packet.php';

final class ShareMarketOperationsHandoffPacketTest extends TestCase
{
    public function testArchiveIdentifierValidationAllowsEmptyLatestArchive(): void
    {
        self::assertSame('', mg_share_market_ops_packet_archive_id(''));
    }

    public function testArchiveIdentifierValidationRejectsInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_share_market_ops_packet_archive_id('../bad');
    }

    public function testPacketArchivePickerUsesLatestWhenNoArchiveProvided(): void
    {
        $archive = mg_share_market_ops_packet_pick_archive(['items' => [
            ['public_id' => 'archive-one'],
            ['public_id' => 'archive-two'],
        ]]);

        self::assertSame('archive-one', $archive['public_id']);
    }

    public function testPacketArchivePickerFindsRequestedArchive(): void
    {
        $archive = mg_share_market_ops_packet_pick_archive(['items' => [
            ['public_id' => 'archive-one'],
            ['public_id' => 'archive-two'],
        ]], 'archive-two');

        self::assertSame('archive-two', $archive['public_id']);
    }

    public function testPacketApiIsGetOnlyAndPermissioned(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/operations-handoff-packet.php');

        self::assertStringContainsString("mg_require_method('GET')", $api);
        self::assertStringContainsString('Share Market Admin permission is required', $api);
        self::assertStringContainsString('mg_share_market_ops_packet', $api);
    }

    public function testPacketPageAndConsoleLinksExist(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/account-share-market-operations-handoff.php');
        $script = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-operations-handoff.js');
        $links = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-operations-handoff-links.js');
        $auditPage = (string)file_get_contents(dirname(__DIR__, 2) . '/account-share-market-execution-audit.php');

        self::assertStringContainsString('data-share-ops-root', $page);
        self::assertStringContainsString('/api/admin/share-market/operations-handoff-packet.php', $script);
        self::assertStringContainsString('/account-share-market-operations-handoff.php', $links);
        self::assertStringContainsString('share-market-operations-handoff-links.js', $auditPage);
    }

    public function testNoSqlMigrationWasAddedForPhase22(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . '/database/stage_25_buy_in_operations_handoff_packet.sql');
    }
}
