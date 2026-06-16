<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UpgradedCheckoutMicrogiftActionCenterBehaviorTest extends TestCase
{
    public function testUpgradedCheckoutIssuesMicrogiftsIntoActionCenterAgainstRealDatabase(): void
    {
        if (trim((string)getenv('MG_DB_HOST')) === '' || trim((string)getenv('MG_DB_NAME')) === '') {
            self::markTestSkipped('Database-backed upgraded checkout validation requires MG_DB_HOST and MG_DB_NAME.');
        }

        $command='php '.escapeshellarg(dirname(__DIR__,2).'/scripts/validate_upgraded_checkout_microgift_behavior.php').' 2>&1';
        exec($command,$output,$exitCode);
        $text=implode("\n",$output);
        self::assertSame(0,$exitCode,$text);
        $summary=json_decode($text,true,512,JSON_THROW_ON_ERROR);
        self::assertTrue($summary['migration_applied']??false,$text);
        self::assertTrue($summary['checkout_created']??false,$text);
        self::assertTrue($summary['capture_completed']??false,$text);
        self::assertTrue($summary['microgifts_issued']??false,$text);
        self::assertTrue($summary['recipient_action_center_visible']??false,$text);
        self::assertTrue($summary['merchant_action_center_sent_visible']??false,$text);
        self::assertTrue($summary['replay_idempotent']??false,$text);
        self::assertTrue($summary['fixtures_clean']??false,$text);
    }
}
