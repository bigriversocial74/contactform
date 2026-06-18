<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10CAtomicClaimRedemptionInboxTest extends TestCase
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

    public function testInboxReadModelIsCanonicalAndIdempotent(): void
    {
        $sql = $this->read('database/stage_10c_atomic_claim_redemption_inbox.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_inbox_items', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_microgift_inbox_instance_user', $sql);
        self::assertStringContainsString("ENUM('received','claimable','claimed','redeemed','expired','revoked')", $sql);
        self::assertStringContainsString('can_tip TINYINT(1)', $sql);
    }

    public function testAtomicServiceOwnsTransactionAndRowLocks(): void
    {
        $service = $this->read('api/microgifts/_atomic_merchant_redemption.php');
        self::assertStringContainsString('beginTransaction', $service);
        self::assertStringContainsString('commit()', $service);
        self::assertStringContainsString('rollBack()', $service);
        self::assertStringContainsString('FOR UPDATE', $service);
        self::assertStringContainsString('mg_microgift_load_instance', $service);
        self::assertStringContainsString('mg_location_claim_resolve_authority', $service);
    }

    public function testCanonicalSideEffectsAreInsideTheApprovalTransaction(): void
    {
        $service = $this->read('api/microgifts/_atomic_merchant_redemption.php');
        foreach (['mg_pppm_redeem','microgift_redemptions','mg_location_claim_increment_usage','mg_microgift_upsert_inbox_redeemed',"status='redeemed'"] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        $approvalCommit = strrpos($service, 'commit()');
        self::assertNotFalse($approvalCommit);
        self::assertLessThan($approvalCommit, strpos($service, 'mg_location_claim_increment_usage'));
        self::assertLessThan($approvalCommit, strpos($service, 'mg_microgift_upsert_inbox_redeemed'));
    }

    public function testFailuresAreRecordedAfterRollback(): void
    {
        $service = $this->read('api/microgifts/_atomic_merchant_redemption.php');
        $rollback = strpos($service, 'rollBack()');
        $failureRecord = strrpos($service, 'mg_location_claim_record_attempt');
        self::assertNotFalse($rollback);
        self::assertNotFalse($failureRecord);
        self::assertLessThan($failureRecord, $rollback);
    }

    public function testRequiredCompatibilityEventsAreProduced(): void
    {
        $service = $this->read('api/microgifts/_atomic_merchant_redemption.php');
        foreach (['gift.claim_attempted','gift.claimed','claim.approved','merchant_location.redemption_completed','inbox.item_moved_to_claimed','psr.redeemed_pending','microgift.redemption_completed'] as $event) {
            self::assertStringContainsString($event, $service);
        }
    }

    public function testSubmittedClaimCodeIsNeverPersisted(): void
    {
        $sql = strtolower($this->read('database/stage_10c_atomic_claim_redemption_inbox.sql'));
        self::assertStringNotContainsString('claim_code varchar', $sql);
        self::assertStringNotContainsString('submitted_code', $sql);
    }
}
