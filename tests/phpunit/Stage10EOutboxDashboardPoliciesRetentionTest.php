<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10EOutboxDashboardPoliciesRetentionTest extends TestCase
{
    private string $root;
    protected function setUp(): void{$this->root=dirname(__DIR__,2);}
    private function read(string $file): string{$value=file_get_contents($this->root.'/'.$file);self::assertIsString($value);return $value;}

    public function testSchemaAddsPoliciesAndRetentionRuns(): void
    {
        $sql=$this->read('database/stage_10e_outbox_dashboard_policies_retention.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_claim_rate_policies',$sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_retention_runs',$sql);
        self::assertStringContainsString('microgift.rate_policies.manage',$sql);
        self::assertStringContainsString('merchant.claim_dashboard.view',$sql);
    }

    public function testMerchantClaimUsesCanonicalConfiguredPolicyService(): void
    {
        $api=$this->read('api/merchant/microgift-claim.php');
        $claimService=$this->read('api/microgifts/_claim_operations.php');
        $stage10e=$this->read('api/microgifts/_stage10e_operations.php');
        self::assertStringContainsString('mg_claim_execute_operation',$api);
        self::assertStringContainsString('mg_claim_rate_policy',$claimService);
        self::assertStringContainsString('microgift_claim_rate_policies',$claimService);
        self::assertStringNotContainsString('mg_claim_execute_operation_configured',$stage10e);
    }

    public function testDashboardIsMerchantScoped(): void
    {
        $api=$this->read('api/merchant/microgift-claim-dashboard.php');
        $service=$this->read('api/microgifts/_stage10e_operations.php');
        self::assertStringContainsString("mg_require_permission('merchant.claim_dashboard.view')",$api);
        self::assertStringContainsString('WHERE merchant_user_id=?',$service);
        self::assertStringContainsString('success_rate',$service);
    }

    public function testOutboxWorkerUsesLockingRetryAndDeadLetterState(): void
    {
        $service=$this->read('api/microgifts/_stage10e_operations.php');
        $worker=$this->read('scripts/stage10e_outbox_worker.php');
        self::assertStringContainsString('FOR UPDATE SKIP LOCKED',$service);
        self::assertStringContainsString("'dead'",$service);
        self::assertStringContainsString('mg_outbox_complete',$worker);
        self::assertStringContainsString('mg_event',$worker);
    }

    public function testRetentionRemovesOperationalDataAndExpiresSecurityEnvelope(): void
    {
        $service=$this->read('api/microgifts/_stage10e_operations.php');
        self::assertStringContainsString('DELETE FROM microgift_claim_rate_limits',$service);
        self::assertStringContainsString("DELETE FROM microgift_operational_outbox WHERE status='delivered'",$service);
        self::assertStringContainsString('DELETE FROM microgift_claim_attempt_security',$service);
        self::assertStringNotContainsString('UPDATE microgift_claim_attempts SET ip_hash=NULL',$service);
    }
}
