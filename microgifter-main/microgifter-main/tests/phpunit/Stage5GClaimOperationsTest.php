<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage5GClaimOperationsTest extends TestCase
{
    private static function compactSource(string $source): string
    {
        return preg_replace('/\s+/', '', $source) ?? $source;
    }

    public function testSchemaDefinesCodeEventsAndClaimExceptions(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_5g_claim_operations.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('merchant_claim_code_events', $sql);
        self::assertStringContainsString('merchant_claim_exceptions', $sql);
    }

    public function testDashboardAndDetailAreMerchantScoped(): void
    {
        foreach (['claims-dashboard.php', 'claim-detail.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/' . $file);
            self::assertIsString($source);
            self::assertStringContainsString('merchant_user_id', $source);
            self::assertStringContainsString('gift_merchant_eligibility', $source);
        }
    }

    public function testPermanentIdAndMerchantCodeRemainSeparate(): void
    {
        $helper = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/_claims.php');
        $verify = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/verify-merchant-claim.php');
        self::assertIsString($helper);
        self::assertIsString($verify);
        self::assertStringContainsString('g.public_id=? OR pi.public_id=?', $helper);
        self::assertStringContainsString("hash_hmac('sha256',\$merchantCode,\$pepper)", self::compactSource($verify));
    }

    public function testClaimCodeActionsProtectSecretsAndAuditRotation(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/claim-code-action.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_require_csrf_for_write', $source);
        self::assertStringContainsString("hash_hmac('sha256'", $source);
        self::assertStringContainsString('merchant_claim_code_events', $source);
        self::assertStringNotContainsString("'code'=>\$code", self::compactSource($source));
    }

    public function testExceptionsRequirePermissionAndCsrf(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/claim-exception.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_require_permission('merchant.claims.exceptions.manage')", $source);
        self::assertStringContainsString('mg_require_csrf_for_write', $source);
    }

    public function testClaimPagesUseMerchantShell(): void
    {
        foreach (['merchant-claims.php', 'merchant-claim.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
            self::assertIsString($source);
            self::assertStringContainsString('includes/merchant-workspace.php', $source);
        }
    }
}
