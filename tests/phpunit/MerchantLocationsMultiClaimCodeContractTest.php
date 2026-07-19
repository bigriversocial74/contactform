<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantLocationsMultiClaimCodeContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testMigrationAddsAssignmentAndArchiveSafeguards(): void
    {
        $sql = file_get_contents($this->root . '/database/20260718_locations_multi_claim_code_safeguards.sql');
        self::assertStringContainsString('assignment_type', $sql);
        self::assertStringContainsString('assignment_reference', $sql);
        self::assertStringContainsString('archived_at', $sql);
        self::assertStringContainsString('archive_reason', $sql);
        self::assertStringContainsString('idx_claim_codes_location_assignment_status', $sql);
    }

    public function testLocationsV2DoesNotCreateOrRotateClaimCodes(): void
    {
        $php = file_get_contents($this->root . '/api/merchant/locations-v2.php');
        self::assertStringNotContainsString('INSERT INTO merchant_claim_codes', $php);
        self::assertStringNotContainsString("SET status='revoked'", $php);
        self::assertStringContainsString('active_claim_codes', $php);
        self::assertStringContainsString('active_scanner_devices', $php);
        self::assertStringContainsString('open_claims', $php);
    }

    public function testClaimCodeApiSupportsManyAssignmentsPerLocation(): void
    {
        $php = file_get_contents($this->root . '/api/merchant/claim-codes.php');
        self::assertStringContainsString("'location','staff','register','device','campaign','department','event','integration'", $php);
        self::assertStringContainsString('assignment_reference', $php);
        self::assertStringContainsString('multi_code_per_location', $php);
        self::assertStringNotContainsString("WHERE location_id=? AND status='active'", $php);
    }

    public function testLocationsPageUsesDedicatedController(): void
    {
        $page = file_get_contents($this->root . '/merchant-locations.php');
        $view = file_get_contents($this->root . '/includes/merchant-locations-view.php');
        $js = file_get_contents($this->root . '/assets/js/merchant-locations-multicode.js');
        self::assertStringContainsString('/assets/js/merchant-locations-multicode.js', $page);
        self::assertStringContainsString('data-claim-code-panel', $view);
        self::assertStringContainsString('/api/merchant/locations-v2.php', $js);
        self::assertStringContainsString('/api/merchant/claim-codes.php', $js);
    }
}
