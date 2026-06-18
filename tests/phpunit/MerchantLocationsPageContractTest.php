<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class MerchantLocationsPageContractTest extends TestCase
{
    public function testDedicatedMerchantLocationsRouteUsesMerchantWorkspace(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/merchant-locations.php');
        self::assertIsString($source);
        foreach([
            '$page_title=\'Merchant Locations | Microgifter\'',
            '$page_section=\'merchant\'',
            '$header_mode=\'account\'',
            '$page_styles=[\'/assets/css/merchant-workspace.css\']',
            '$page_scripts=[\'/assets/js/merchant-workspace.js\']',
            '$merchantView=\'locations\'',
            'require __DIR__.\'/includes/merchant-workspace.php\'',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testMerchantLocationsViewCapturesClaimLocationFields(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/merchant-locations-view.php');
        self::assertIsString($source);
        foreach([
            'Claim locations','Location title','name="name"','Location address',
            'name="address_line1" required maxlength="190"',
            'name="address_line2"','Location phone','name="phone"','Location claim code','name="claim_code"',
            'Codes are stored securely and cannot be displayed again.','data-location-code-help','data-location-save',
            'A merchant can only claim gift vouchers from its own product catalog.','data-location-list','data-location-form',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testMerchantLocationsApiPersistsCanonicalHashedClaimCodes(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/locations.php');
        $migration=file_get_contents(dirname(__DIR__,2).'/database/stage_18f_pppm_publish_distribution.sql');
        self::assertIsString($source);
        self::assertIsString($migration);

        foreach([
            "require_once __DIR__ . '/_claims.php';",
            'workspace_id=? AND merchant_user_id=?',
            '$claimCode=strtoupper(trim((string)($input[\'claim_code\']??\'\')))',
            '$address1=trim((string)($input[\'address_line1\']??\'\'))',
            "mg_fail('Location address is required and must be 190 characters or fewer.',422)",
            'mg_claim_code_pepper()',
            "hash_hmac('sha256',\$claimCode,\$pepper)",
            'INSERT INTO merchant_claim_codes',
            'INSERT INTO merchant_claim_code_events',
            'Location claim code already exists.',
            'claim_code_last4',
            'has_active_claim_code',
            'mg_merchant_unique_location_code',
            'WHERE id=? AND public_id=? AND workspace_id=? AND merchant_user_id=?',
            "'schema_ready'=>true",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('mg_merchant_locations_has_claim_code',$source);
        self::assertStringNotContainsString('merchant_locations SET name=?,location_code=?,claim_code=?',$source);
        self::assertStringNotContainsString('(public_id,workspace_id,merchant_user_id,name,location_code,claim_code',$source);
        self::assertStringNotContainsString('ALTER TABLE',$source);
        self::assertStringContainsString('ADD COLUMN workspace_id BIGINT UNSIGNED NULL',$migration);
        self::assertStringContainsString('ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0',$migration);
    }

    public function testMerchantWorkspaceLocationsViewRoutesToDedicatedTemplate(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/merchant-view.php');
        self::assertIsString($source);
        self::assertStringContainsString("elseif(\$merchantView==='locations'): require __DIR__.'/merchant-locations-view.php';",$source);
    }

    public function testAgentMerchantSidebarPointsLocationsToDedicatedPage(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/includes/agent-sidebar.php');
        self::assertIsString($source);
        foreach([
            '<a href="/merchant-locations.php">Locations</a>',
            '<a href="/merchant-products.php">Products &amp; offers</a>',
            '<a href="/merchant-pppm.php">Orders &amp; redemptions</a>',
            '<a href="/merchant-settings.php">Merchant settings</a>',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('/account.php#locations',$source);
    }

    public function testMerchantWorkspaceJavascriptRendersAndSubmitsProtectedClaimCodes(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/merchant-workspace.js');
        self::assertIsString($source);
        foreach([
            'Claim code: ','x.claim_code_last4','form.elements.claim_code',
            'Leave blank to keep it, or enter a new code to rotate it.',
            "Microgifter.post('/api/merchant/locations.php'",
            "Microgifter.get('/api/merchant/locations.php')",
            "setStatus(status,'Saving location…')",
            'Microgifter.setBusy',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
        self::assertStringNotContainsString('x.claim_code||\'not set\'',$source);
    }
}
