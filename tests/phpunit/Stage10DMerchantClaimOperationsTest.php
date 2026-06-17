<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10DMerchantClaimOperationsTest extends TestCase
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

    public function testOperationalSchemaProvidesRateLimitsEscalationsAndOutbox(): void
    {
        $sql = $this->read('database/stage_10d_merchant_claim_operations.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_claim_rate_limits', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_claim_escalations', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_operational_outbox', $sql);
        self::assertStringContainsString('blocked_until DATETIME NULL', $sql);
        self::assertStringContainsString("status ENUM('pending','processing','delivered','failed','dead')", $sql);
    }

    public function testClaimApiUsesCanonicalAtomicServiceAndSecurityGates(): void
    {
        $api = $this->read('api/merchant/microgift-claim.php');
        self::assertStringContainsString("mg_require_method('POST')", $api);
        self::assertStringContainsString("mg_require_permission('merchant.location_claim.execute')", $api);
        self::assertStringContainsString('mg_require_csrf_for_write', $api);
        self::assertStringContainsString('mg_claim_execute_operation', $api);
        self::assertStringContainsString('429', $api);
    }

    public function testRateLimitsCoverActorMerchantLocationGiftAndNetwork(): void
    {
        $service = $this->read('api/microgifts/_claim_operations.php');
        foreach (['actor','merchant','location','gift','network'] as $scope) {
            self::assertStringContainsString("'{$scope}'", $service);
        }
        self::assertStringContainsString('FOR UPDATE', $service);
        self::assertStringContainsString("'rate_limited'", $service);
    }

    public function testOperationalHistoryIsMerchantScoped(): void
    {
        $api = $this->read('api/merchant/microgift-claim-history.php');
        $service = $this->read('api/microgifts/_claim_operations.php');
        self::assertStringContainsString("mg_require_permission('merchant.location_claim.history')", $api);
        self::assertStringContainsString('WHERE a.merchant_user_id=?', $service);
        self::assertStringContainsString('microgift_redemptions r ON r.claim_attempt_id=a.id', $service);
    }

    public function testEscalationRulesCoverSecurityAndOperationalFailures(): void
    {
        $service = $this->read('api/microgifts/_claim_operations.php');
        foreach (['rate_limit','repeated_invalid_code','merchant_mismatch','location_not_allowed','internal_error'] as $trigger) {
            self::assertStringContainsString($trigger, $service);
        }
        self::assertStringContainsString('mg_microgift_create_review', $service);
    }

    public function testAdminEscalationApiHasPermissionAndCsrfProtection(): void
    {
        $api = $this->read('api/admin/microgift-claim-escalations.php');
        self::assertStringContainsString("mg_require_permission('microgift.claim_escalations.manage')", $api);
        self::assertStringContainsString('mg_require_csrf_for_write', $api);
        self::assertStringContainsString("['start','resolve','dismiss']", $api);
    }

    public function testOutboxDoesNotContainClaimCredentialFields(): void
    {
        $service = strtolower($this->read('api/microgifts/_claim_operations.php'));
        self::assertStringNotContainsString("'claim_code'=>", $service);
        self::assertStringNotContainsString("'code_hash'=>", $service);
    }
}
