<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/evidence-acknowledgements.php';

final class ShareMarketEvidenceAcknowledgementTest extends TestCase
{
    public function testReviewerRolesAreExplicit(): void
    {
        self::assertSame(['operator','engineering','security','legal','product_owner','executive','other'], mg_share_market_ack_roles());
    }

    public function testReviewerRoleValidationRejectsInvalidRole(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_share_market_ack_role('invalid_role');
    }

    public function testReviewerNoteValidationRejectsLongNotes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mg_share_market_ack_note(str_repeat('x', 2200));
    }

    public function testAcknowledgementDriftComparison(): void
    {
        $hash = str_repeat('a', 64);
        $match = mg_share_market_ack_compare(['package_hash' => $hash], ['package_hash' => $hash]);
        $drift = mg_share_market_ack_compare(['package_hash' => $hash], ['package_hash' => str_repeat('b', 64)]);

        self::assertTrue($match['matches_current']);
        self::assertSame('matching', $match['drift_status']);
        self::assertFalse($drift['matches_current']);
        self::assertSame('drifted', $drift['drift_status']);
    }

    public function testAcknowledgementDecodeAddsDrift(): void
    {
        $hash = str_repeat('c', 64);
        $row = mg_share_market_ack_decode(['package_hash' => $hash], ['package_hash' => $hash]);

        self::assertTrue($row['drift']['matches_current']);
    }

    public function testMigrationIsRegistered(): void
    {
        $manifest = (string)file_get_contents(dirname(__DIR__, 2) . '/config/migrations.php');
        $migration = (string)file_get_contents(dirname(__DIR__, 2) . '/database/stage_23_buy_in_final_reviewer_acknowledgements.sql');

        self::assertStringContainsString('stage_23_buy_in_final_reviewer_acknowledgements.sql', $manifest);
        self::assertStringContainsString('share_market_evidence_acknowledgements', $migration);
    }

    public function testAcknowledgementApiRequiresCsrfForWrites(): void
    {
        $api = (string)file_get_contents(dirname(__DIR__, 2) . '/api/admin/share-market/evidence-acknowledgements.php');

        self::assertStringContainsString('mg_share_market_ack_list', $api);
        self::assertStringContainsString('mg_require_csrf_for_write', $api);
        self::assertStringContainsString('mg_share_market_ack_record', $api);
    }

    public function testAuditConsoleInjectsAcknowledgementControls(): void
    {
        $js = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/share-market-execution-audit.js');

        self::assertStringContainsString('data-share-ack-panel', $js);
        self::assertStringContainsString('data-share-ack-form', $js);
        self::assertStringContainsString('/api/admin/share-market/evidence-acknowledgements.php', $js);
        self::assertStringContainsString('recordAck', $js);
        self::assertStringContainsString('loadAcks', $js);
    }
}
