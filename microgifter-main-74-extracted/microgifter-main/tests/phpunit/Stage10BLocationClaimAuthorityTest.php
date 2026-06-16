<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10BLocationClaimAuthorityTest extends TestCase
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

    public function testMigrationCreatesImmutableAttemptLedgerAndStaffAssignments(): void
    {
        $sql = $this->read('database/stage_10b_location_claim_authority.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS merchant_location_staff', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_claim_attempts', $sql);
        self::assertStringContainsString('merchant_claim_code_id BIGINT UNSIGNED NULL', $sql);
        self::assertStringNotContainsString('ON UPDATE CURRENT_TIMESTAMP', substr($sql, strpos($sql, 'CREATE TABLE IF NOT EXISTS microgift_claim_attempts'), 2600));
    }

    public function testAttemptResultCatalogIsComplete(): void
    {
        $sql = $this->read('database/stage_10b_location_claim_authority.sql');
        foreach (['approved','invalid_gift','gift_not_paid','invalid_state','gift_expired','already_claimed','merchant_mismatch','invalid_location','location_not_allowed','invalid_claim_code','unauthorized_claim_actor','rate_limited','internal_error'] as $result) {
            self::assertStringContainsString("'{$result}'", $sql);
        }
    }

    public function testAuthorityServiceLocksCanonicalLocationAndCode(): void
    {
        $service = $this->read('api/microgifts/_location_claim_authority.php');
        self::assertStringContainsString('merchant_locations WHERE public_id=? LIMIT 1 FOR UPDATE', $service);
        self::assertStringContainsString('merchant_claim_codes WHERE merchant_user_id=? AND location_id=?', $service);
        self::assertStringContainsString('hash_equals', $service);
        self::assertStringContainsString("status='active'", $service);
        self::assertStringContainsString('usage_limit', $service);
    }

    public function testAuthorityServiceUsesPepperedHashAndDoesNotPersistPlaintextCode(): void
    {
        $service = $this->read('api/microgifts/_location_claim_authority.php');
        self::assertStringContainsString('function mg_location_claim_hash', $service);
        self::assertStringContainsString('mg_location_claim_pepper()', $service);
        self::assertStringNotContainsString('submitted_code', strtolower($this->read('database/stage_10b_location_claim_authority.sql')));
        self::assertStringNotContainsString('raw_code', strtolower($this->read('database/stage_10b_location_claim_authority.sql')));
    }

    public function testUsageIsIncrementedOnlyThroughExplicitApprovalHook(): void
    {
        $service = $this->read('api/microgifts/_location_claim_authority.php');
        self::assertStringContainsString('function mg_location_claim_increment_usage', $service);
        self::assertStringNotContainsString('mg_location_claim_increment_usage($pdo', substr($service, 0, strpos($service, 'function mg_location_claim_increment_usage')));
    }
}
