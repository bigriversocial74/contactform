<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Base-refresh marker: rerun against integration after PR #1208 Golden Path repair.
final class ClaimLifecycleIntegrityAuditTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testGiftVerificationUsesWorkspaceOwnerNotOperatorAsMerchant(): void
    {
        $php = file_get_contents($this->root . '/api/gifts/verify-merchant-claim.php');
        self::assertStringContainsString('$ownerMerchantId = (int)$scope[\'owner_merchant_id\']', $php);
        self::assertStringContainsString('mg_claim_location($pdo, $user, $locationPublicId, true)', $php);
        self::assertStringContainsString('$ownerMerchantId, (int)$location[\'id\']', $php);
        self::assertStringNotContainsString('merchant_user_id=? AND mcc.location_id=? AND ml.workspace_id=?', $php);
    }

    public function testPppmVerificationUsesWorkspaceOwnerAndCanonicalCodeHelpers(): void
    {
        $php = file_get_contents($this->root . '/api/pppm/verify-merchant-claim.php');
        self::assertStringContainsString('$ownerMerchantId = (int) $scope[\'owner_merchant_id\']', $php);
        self::assertStringContainsString('mg_claim_code_hash(mg_claim_code_require(', $php);
        self::assertStringContainsString('mg_merchant_location_scope_condition', $php);
        self::assertStringContainsString('owner_merchant_id', $php);
    }

    public function testGiftRedemptionRechecksCodeAndUsesAtomicStateGuards(): void
    {
        $php = file_get_contents($this->root . '/api/gifts/redeem-merchant-claim.php');
        self::assertStringContainsString("merchant_user_id=? AND status='active'", $php);
        self::assertStringContainsString("WHERE id=? AND status='verified'", $php);
        self::assertStringContainsString("WHERE id=? AND status<>'claimed'", $php);
        self::assertStringContainsString("'duplicate' => true", $php);
        self::assertStringContainsString('verified_by_user_id', $php);
        self::assertStringContainsString('redeemed_by_user_id', $php);
    }

    public function testPppmRedemptionUsesOwnerForFinancialRecordAndActorForOperator(): void
    {
        $php = file_get_contents($this->root . '/api/pppm/redeem-merchant-claim.php');
        self::assertStringContainsString('$ownerMerchantId,', $php);
        self::assertStringContainsString('$actorUserId,', $php);
        self::assertStringContainsString("WHERE id = ? AND status = 'verified'", $php);
        self::assertStringContainsString("WHERE id = ? AND status <> 'redeemed'", $php);
        self::assertStringContainsString("'duplicate' => true", $php);
        self::assertStringContainsString('existingRedemptionId', $php);
    }

    public function testAllFourEndpointsRecordWorkspaceAndMerchantAuditContext(): void
    {
        foreach ([
            '/api/gifts/verify-merchant-claim.php',
            '/api/gifts/redeem-merchant-claim.php',
            '/api/pppm/verify-merchant-claim.php',
            '/api/pppm/redeem-merchant-claim.php',
        ] as $path) {
            $php = file_get_contents($this->root . $path);
            self::assertStringContainsString('workspace_id', $php, $path);
            self::assertStringContainsString('owner_merchant_id', $php, $path);
        }
    }
}
