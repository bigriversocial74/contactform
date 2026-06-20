<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CrossStageIntegrityRegressionTest extends TestCase
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

    public function testFullUpgradeUsesExplicitDependencyOrder(): void
    {
        $builder = $this->read('scripts/build_full_upgrade_sql.php');
        self::assertStringContainsString('$orderedMigrations = [', $builder);
        self::assertStringNotContainsString('usort($files', $builder);
        self::assertStringContainsString('Unregistered migration files require an explicit dependency-order decision', $builder);

        $identity = strpos($builder, "'stage_1_identity.sql'");
        $closure = strpos($builder, "'stage_1_foundation_closure.sql'");
        $pppmCore = strpos($builder, "'stage_3_pppm_core.sql'");
        $pppmActivity = strpos($builder, "'stage_3_pppm_activity_layer.sql'");
        self::assertIsInt($identity);
        self::assertIsInt($closure);
        self::assertIsInt($pppmCore);
        self::assertIsInt($pppmActivity);
        self::assertLessThan($closure, $identity);
        self::assertLessThan($pppmActivity, $pppmCore);
    }

    public function testProfilePatchPreservesOmittedFields(): void
    {
        $profile = $this->read('api/me/profile.php');
        self::assertStringContainsString('array_key_exists(\'avatar_url\', $input)', $profile);
        self::assertStringContainsString('array_key_exists(\'headline\', $input)', $profile);
        self::assertStringContainsString('array_key_exists(\'bio\', $input)', $profile);
        self::assertStringContainsString('SELECT avatar_url, headline, bio FROM user_profiles', $profile);
        self::assertStringContainsString('$resolvedAvatarUrl', $profile);
        self::assertStringContainsString('$resolvedHeadline', $profile);
        self::assertStringContainsString('$resolvedBio', $profile);
    }

    public function testMicrogiftIssuanceRejectsCrossRequestIdempotencyReplay(): void
    {
        $engine = $this->read('api/microgifts/_engine.php');
        self::assertStringContainsString('function mg_microgift_existing_issue', $engine);
        self::assertStringContainsString('issuer_user_id', $engine);
        self::assertStringContainsString('template_version_public_id', $engine);
        self::assertStringContainsString('different Microgift issuance request', $engine);
    }

    public function testRedemptionRejectsCrossGiftIdempotencyReplay(): void
    {
        $redemption = $this->read('api/microgifts/_atomic_merchant_redemption.php');
        self::assertStringContainsString('function mg_microgift_existing_redemption', $redemption);
        foreach (['instance_public_id','claimant_user_id','merchant_user_id','location_reference','source_reference'] as $binding) {
            self::assertStringContainsString($binding, $redemption);
        }
        self::assertStringContainsString('idempotency_conflict', $redemption);
    }

    public function testClaimVerificationUsesTheStageFiveHmacContract(): void
    {
        $authority = $this->read('api/microgifts/_location_claim_authority.php');
        $management = $this->read('api/merchant/claim-codes.php');
        self::assertStringContainsString('hash_hmac(\'sha256\',$normalized,mg_location_claim_pepper())', $authority);
        self::assertStringContainsString('hash_hmac(\'sha256\', $code, $pepper)', $management);
        self::assertStringNotContainsString('return hash(\'sha256\',$normalized)', $authority);
        self::assertStringContainsString("'idempotency_conflict'", $authority);
    }

    public function testLedgerReplayRequiresMatchingHeaderAndEntries(): void
    {
        $money = $this->read('api/finance/_money.php');
        self::assertStringContainsString('function mg_ledger_assert_idempotent_request', $money);
        self::assertStringContainsString('function mg_ledger_entry_fingerprint', $money);
        self::assertStringContainsString('different transaction request', $money);
        self::assertStringContainsString('different ledger entries', $money);
    }

    public function testPaidOrderReplayDoesNotDuplicateNotifications(): void
    {
        $capture = $this->read('api/payments/_capture.php');
        self::assertMatchesRegularExpression('/\$paymentTransitioned\s*=\s*false;/', $capture);
        self::assertMatchesRegularExpression('/\$paymentTransitioned\s*=\s*true;/', $capture);
        self::assertMatchesRegularExpression('/if\s*\(\s*\$paymentTransitioned\s*\)/', $capture);
    }

    public function testCommercePppmSourceCreationHandlesDuplicateRace(): void
    {
        $fulfillment = $this->read('api/payments/_fulfillment.php');
        self::assertStringContainsString('str_contains($error->getMessage(), \'Duplicate\')', $fulfillment);
        self::assertStringContainsString('Unable to create commerce PPPM source', $fulfillment);
    }

    public function testWebhookRejectsUnsignedPreseedAndStaleFailureRegression(): void
    {
        $endpoint = $this->read('api/payments/webhook.php');
        $service = $this->read('api/payments/_webhook.php');
        $signatureCheck = strpos($endpoint, 'if(!$valid)');
        $serviceCall = strpos($endpoint, 'mg_payment_process_webhook_event(');
        self::assertIsInt($signatureCheck);
        self::assertIsInt($serviceCall);
        self::assertLessThan($serviceCall, $signatureCheck);
        self::assertStringNotContainsString('INSERT INTO payment_webhook_events', $endpoint);
        self::assertStringContainsString('INSERT INTO payment_webhook_events', $service);
        self::assertStringContainsString('payload_hash', $service);
        self::assertStringContainsString('payment.webhook_idempotency_conflict', $service);
        self::assertStringContainsString('payment.webhook_stale_failure', $service);
        self::assertStringContainsString("payment_status<>'paid'", $service);
        self::assertStringContainsString("status<>'succeeded'", $service);
    }

    public function testCiBuildsTheAuthoritativeFullUpgradeArtifact(): void
    {
        $workflow = $this->read('.github/workflows/pr-validation.yml');
        self::assertStringContainsString('php scripts/build_full_upgrade_sql.php build/microgifter_full_upgrade.sql', $workflow);
        self::assertStringContainsString('microgifter-full-upgrade-sql', $workflow);
        self::assertStringNotContainsString('Build consolidated Stage 1 to Stage 9 upgrade SQL', $workflow);
    }
}
