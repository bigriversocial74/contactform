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
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_5g_claim_operations.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('merchant_claim_code_events',$sql);
        self::assertStringContainsString('merchant_claim_exceptions',$sql);
    }

    public function testCanonicalDashboardIsMerchantScoped(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/claims-dashboard.php');
        self::assertIsString($source);
        self::assertStringContainsString('$merchantId=(int)$user[\'id\']',$source);
        self::assertStringContainsString('a.merchant_user_id=?',$source);
        self::assertStringContainsString('microgift_claim_attempts',$source);
        self::assertStringContainsString('microgift_redemptions',$source);
        self::assertStringNotContainsString('gift_merchant_eligibility',$source);
    }

    public function testLegacyClaimDetailRemainsMerchantScoped(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/claim-detail.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_claim_lookup($pdo,(int)$user[\'id\'],$identifier)',$source);
        self::assertStringContainsString('e.merchant_user_id=?',$source);
        self::assertStringContainsString('mw.merchant_user_id=?',$source);
    }

    public function testPermanentIdAndMerchantCodeRemainSeparate(): void
    {
        $helper=file_get_contents(dirname(__DIR__,2).'/api/merchant/_claims.php');
        $verify=file_get_contents(dirname(__DIR__,2).'/api/gifts/verify-merchant-claim.php');
        self::assertIsString($helper);
        self::assertIsString($verify);
        self::assertStringContainsString('g.public_id=? OR pi.public_id=?',$helper);
        self::assertStringContainsString("hash_hmac('sha256',\$merchantCode,\$pepper)",self::compactSource($verify));
    }

    public function testClaimCodeManagementApiCanonicalizesScopesAndHidesSecrets(): void
    {
        $helper=file_get_contents(dirname(__DIR__,2).'/api/merchant/_claims.php');
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/claim-codes.php');
        self::assertIsString($helper);
        self::assertIsString($source);

        foreach([
            "mg_require_permission('merchant.claim_codes.manage')",
            'mg_require_csrf_for_write($input)',
            'mg_claim_code_require',
            'mg_claim_code_hash',
            'mg_claim_code_assert_no_active_duplicate',
            "mcc.merchant_user_id=? AND ml.workspace_id=? AND ml.merchant_user_id=?",
            'merchant_claim_code_events',
            'code_last4',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        foreach([
            'mg_claim_code_normalize',
            "hash_hmac('sha256',\$claimCode,\$pepper)",
            "merchant_user_id=?ANDcode_hash=?ANDstatus='active'",
            'mg_claim_code_event',
        ] as $needle){
            self::assertStringContainsString($needle,self::compactSource($helper));
        }

        self::assertStringNotContainsString("'code'=>",self::compactSource($source));
        self::assertStringNotContainsString('code_hash AS',self::compactSource($source));
    }

    public function testClaimCodeActionsProtectSecretsAndAuditRotation(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/claim-code-action.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_require_csrf_for_write',$source);
        self::assertStringContainsString("hash_hmac('sha256'",$source);
        self::assertStringContainsString('merchant_claim_code_events',$source);
        self::assertStringContainsString('mg_claim_code_assert_no_active_duplicate',$source);
        self::assertStringContainsString("'event'=>\$event",self::compactSource($source));
        self::assertStringNotContainsString("'code'=>\$code",self::compactSource($source));
        self::assertStringNotContainsString("'code'=>\$replacementCode",self::compactSource($source));
    }

    public function testExceptionsRequirePermissionAndCsrf(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/claim-exception.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_require_permission('merchant.claims.exceptions.manage')",$source);
        self::assertStringContainsString('mg_require_csrf_for_write',$source);
    }

    public function testClaimPagesUseMerchantShell(): void
    {
        foreach(['merchant-claims.php','merchant-claim.php'] as $file){
            $source=file_get_contents(dirname(__DIR__,2).'/'.$file);
            self::assertIsString($source);
            self::assertStringContainsString('includes/merchant-workspace.php',$source);
        }
    }
}
