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
            'Claim locations',
            'Location title',
            'name="name"',
            'Location address',
            'name="address_line1"',
            'name="address_line2"',
            'Location phone',
            'name="phone"',
            'Location claim code',
            'name="claim_code"',
            'A merchant can only claim gift vouchers from its own product catalog.',
            'data-location-list',
            'data-location-form',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testMerchantLocationsApiUsesMigratedClaimCodeStorage(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/merchant/locations.php');
        $migration=file_get_contents(dirname(__DIR__,2).'/database/stage_11h_backend_hardening.sql');
        self::assertIsString($source);
        self::assertIsString($migration);

        foreach([
            'mg_merchant_locations_has_claim_code($pdo)',
            'SELECT public_id,name,location_code,claim_code,address_line1,address_line2,city,region,postal_code,country_code,timezone,phone,status,is_primary,created_at,updated_at FROM merchant_locations',
            '$claimCode=strtoupper(trim((string)($input[\'claim_code\']??\'\')))',
            '$claimCode===\'\'',
            'Location claim code already exists.',
            'INSERT INTO merchant_locations (public_id,workspace_id,name,location_code,claim_code,address_line1,address_line2,city,region,postal_code,country_code,timezone,phone,status,is_primary,created_at,updated_at)',
            'UPDATE merchant_locations SET name=?,location_code=?,claim_code=?,address_line1=?,address_line2=?,city=?,region=?,postal_code=?,country_code=?,timezone=?,phone=?,status=?,is_primary=?,updated_at=NOW()',
            '\'claim_code\'=>$claimCode',
            '\'schema_ready\'=>$hasClaimCode',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('ALTER TABLE',$source);
        self::assertStringNotContainsString('mg_merchant_locations_ensure_claim_code',$source);
        self::assertStringContainsString('ALTER TABLE merchant_locations ADD COLUMN claim_code VARCHAR(80) NULL',$migration);
        self::assertStringContainsString('uq_merchant_locations_workspace_claim_code',$migration);
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

    public function testMerchantWorkspaceJavascriptRendersAndSubmitsClaimCodes(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/assets/js/merchant-workspace.js');
        self::assertIsString($source);

        foreach([
            'Claim code: ',
            'x.claim_code',
            'form.elements.claim_code',
            'Microgifter.post(\'/api/merchant/locations.php\'',
            'Microgifter.get(\'/api/merchant/locations.php\')',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }
}
