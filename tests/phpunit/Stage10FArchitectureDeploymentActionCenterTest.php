<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage10FArchitectureDeploymentActionCenterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $relative): string
    {
        $content=file_get_contents($this->root.'/'.$relative);
        self::assertIsString($content,'Unable to read '.$relative);
        return $content;
    }

    public function testActionCenterUsesThreeCanonicalFolders(): void
    {
        $sql=$this->read('database/stage_10f_architecture_deployment_action_center.sql');
        $doc=$this->read('docs/stages/stage_10f_action_center_state_model.md');
        self::assertStringContainsString("ENUM('inbox','sent','claimed')",$sql);
        foreach(['INBOX','SENT','CLAIMED'] as $folder)self::assertStringContainsString($folder,$doc);
        self::assertStringContainsString('Merchant code was verified and redemption committed',$doc);
    }

    public function testClaimOperationHasOneCanonicalEntryPoint(): void
    {
        $claim=$this->read('api/microgifts/_claim_operations.php');
        $stage10e=$this->read('api/microgifts/_stage10e_operations.php');
        $api=$this->read('api/merchant/microgift-claim.php');
        self::assertStringContainsString('function mg_claim_execute_operation(',$claim);
        self::assertStringNotContainsString('mg_claim_execute_operation_configured',$stage10e);
        self::assertStringContainsString('mg_claim_execute_operation(',$api);
    }

    public function testOperationalOutboxIsWrittenBeforeRedemptionCommit(): void
    {
        $service=$this->read('api/microgifts/_atomic_merchant_redemption.php');
        $outbox=strpos($service,'mg_claim_operational_outbox(');
        $commit=strrpos($service,'$pdo->commit()');
        self::assertNotFalse($outbox);
        self::assertNotFalse($commit);
        self::assertLessThan($commit,$outbox);
        self::assertStringContainsString('must occur inside the owning domain transaction',$this->read('api/microgifts/_operational_outbox.php'));
    }

    public function testAttemptAuditIsSeparatedFromExpiringSecurityMetadata(): void
    {
        $migration=$this->read('database/stage_10f_architecture_deployment_action_center.sql');
        $authority=$this->read('api/microgifts/_location_claim_authority.php');
        $retention=$this->read('api/microgifts/_stage10e_operations.php');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS microgift_claim_attempt_security',$migration);
        self::assertStringContainsString('INSERT INTO microgift_claim_attempt_security',$authority);
        self::assertStringContainsString('DELETE FROM microgift_claim_attempt_security',$retention);
        self::assertStringNotContainsString('UPDATE microgift_claim_attempts SET ip_hash=NULL',$retention);
    }

    public function testLifecycleFailureClassificationUsesTypedExceptions(): void
    {
        $service=$this->read('api/microgifts/_atomic_merchant_redemption.php');
        self::assertStringContainsString('final class MgMicrogiftLifecycleException',$service);
        self::assertStringNotContainsString('str_contains'.'($message',$service);
        foreach(['gift_not_paid','gift_expired','already_claimed','invalid_state'] as $code)self::assertStringContainsString($code,$service);
    }

    public function testActionCenterApiIsUserScoped(): void
    {
        $api=$this->read('api/account/action-center.php');
        $service=$this->read('api/account/_action_center.php');
        self::assertStringContainsString('mg_require_api_user()',$api);
        self::assertStringContainsString("'ac.user_id=?'",$service);
        self::assertStringContainsString("'ac.folder=?'",$service);
        self::assertStringContainsString("['inbox','sent','claimed']",$service);
    }

    public function testFullUpgradeBuilderRequiresStages10BThrough10F(): void
    {
        $builder=$this->read('scripts/build_full_upgrade_sql.php');
        foreach(['stage_10b_','stage_10c_','stage_10d_','stage_10e_','stage_10f_'] as $stage)self::assertStringContainsString($stage,$builder);
        self::assertStringContainsString("hash('sha256'",$builder);
        self::assertStringContainsString('manifest.json',$builder);
    }
}
