<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantLocationsPageContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testDedicatedMerchantLocationsRouteUsesSharedMerchantWorkspace(): void
    {
        $source=$this->source('merchant-locations.php');

        foreach([
            "\$page_title='Merchant Locations | Microgifter'",
            "\$page_section='merchant'",
            "\$header_mode='account'",
            "'/assets/css/merchant-locations-redemption.css'",
            "'/assets/js/merchant-workspace.js'",
            "'/assets/js/merchant-locations-tabs.js'",
            "\$merchantView='locations'",
            "require __DIR__.'/includes/merchant-workspace.php'",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testLocationPageUsesOneFullWidthWorkspaceWithoutTabsOrRightSidebar(): void
    {
        $source=$this->source('includes/merchant-locations-view.php');

        foreach([
            'data-location-kpi-active',
            'data-location-kpi-claim',
            'data-location-kpi-primary',
            'data-location-kpi-archived',
            'data-location-kpi-staff',
            'id="locations-list-panel"',
            'id="location-editor-panel"',
            'data-location-open-add',
            'data-location-list',
            'data-location-form',
            'Add or edit location',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('mg-locations-commandbar',$source);
        self::assertStringNotContainsString('mg-locations-tabs',$source);
        self::assertStringNotContainsString('data-location-tab=',$source);
        self::assertStringNotContainsString('<aside',$source);
        self::assertStringNotContainsString('id="locations-readiness"',$source);
        self::assertStringNotContainsString('data-location-section=',$source);
        self::assertStringNotContainsString('hidden>',$source);
    }

    public function testAllFiveLocationStatsRemainInOneRow(): void
    {
        $source=$this->source('assets/css/merchant-locations-redemption.css');

        self::assertStringContainsString('grid-template-columns:repeat(5,minmax(180px,1fr))',$source);
        self::assertStringContainsString('overflow-x:auto',$source);
        self::assertStringContainsString('min-width:180px',$source);
        self::assertStringNotContainsString('grid-template-columns:repeat(3',$source);
        self::assertStringNotContainsString('.mg-locations-side',$source);
        self::assertStringNotContainsString('.mg-locations-layout',$source);
    }

    public function testCanonicalLocationScopeIsWorkspaceAuthoritative(): void
    {
        $scope=$this->source('includes/merchant-location-scope.php');
        $locations=$this->source('api/merchant/locations.php');
        $claims=$this->source('api/merchant/_claims.php');

        self::assertStringContainsString('A valid workspace relationship is authoritative',$scope);
        self::assertStringContainsString('$workspaceAlias}.id=?',$scope);
        self::assertStringContainsString('$workspaceAlias}.id IS NULL',$scope);
        self::assertStringContainsString('$locationAlias}.merchant_user_id=?',$scope);
        self::assertStringNotContainsString('merchant_user_id=? OR',$scope);

        self::assertStringContainsString('mg_merchant_location_scope_context($workspace)',$locations);
        self::assertStringContainsString("mg_merchant_location_scope_join('ml','location_scope_mw')",$locations);
        self::assertStringContainsString("mg_merchant_location_scope_condition('ml','location_scope_mw')",$locations);
        self::assertStringContainsString('SET workspace_id=?,merchant_user_id=?,name=?,location_code=?',$locations);
        self::assertStringContainsString("'ownership_normalized'=>true",$locations);
        self::assertStringNotContainsString('WHERE ml.merchant_user_id=?',$locations);

        self::assertStringContainsString('mg_merchant_location_find_by_public_id(',$claims);
        self::assertStringContainsString('mg_claim_code_assert_no_active_duplicate(',$claims);
    }

    public function testLocationsRemainConsistentAcrossApplicationConsumers(): void
    {
        $overview=$this->source('api/merchant/overview.php');
        $packageLimits=$this->source('api/account/package-limits.php');
        $world=$this->source('api/world-canvas/_locations.php');
        $scannerPage=$this->source('merchant-scanner-settings.php');
        $scannerSettings=$this->source('api/merchant/scanner-settings.php');
        $scannerDevices=$this->source('api/merchant/scanner-devices.php');
        $redeem=$this->source('api/account/action-center-redeem-locations.php');
        $builder=$this->source('api/catalog/_publish_distribution.php');
        $agentContext=$this->source('includes/ai/merchant-context-builder.php');

        self::assertStringContainsString('mg_merchant_location_count($pdo,$workspaceId,$ownerMerchantId)',$overview);
        self::assertStringContainsString('mg_merchant_location_count($pdo,$workspaceId,$ownerMerchantId)',$packageLimits);
        self::assertGreaterThanOrEqual(3,substr_count($world,"mg_merchant_location_scope_condition('ml','location_scope_mw')"));
        self::assertStringContainsString('$ownerMerchantId',$scannerPage);
        self::assertStringContainsString('$ownerMerchantId',$scannerSettings);
        self::assertStringContainsString('$ownerMerchantId',$scannerDevices);
        self::assertStringContainsString("mg_merchant_location_scope_condition('ml','location_scope_mw')",$redeem);
        self::assertStringContainsString('INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id',$builder);
        self::assertStringContainsString('FROM merchant_locations WHERE workspace_id = ?',$agentContext);
    }

    public function testClaimCodeStatusUsesOwnedLocationInsteadOfStaleMerchantColumn(): void
    {
        $locations=$this->source('api/merchant/locations.php');
        $claimCodes=$this->source('api/merchant/claim-codes.php');
        $claimAction=$this->source('api/merchant/claim-code-action.php');

        self::assertStringContainsString('WHERE mcc.location_id=ml.id',$locations);
        self::assertStringNotContainsString('WHERE mcc.merchant_user_id=ml.merchant_user_id',$locations);
        self::assertStringContainsString("mg_merchant_location_scope_condition('ml','location_scope_mw')",$claimCodes);
        self::assertStringContainsString('mg_merchant_location_normalize_ownership(',$claimAction);
        self::assertStringNotContainsString('ml.workspace_id=? AND ml.merchant_user_id=?',$claimCodes);
    }

    public function testLocationEditorScrollControllerNoLongerImplementsTabNavigation(): void
    {
        $source=$this->source('assets/js/merchant-locations-tabs.js');

        self::assertStringContainsString("querySelector('#location-editor-panel')",$source);
        self::assertStringContainsString("closest('[data-location-open-add]')",$source);
        self::assertStringContainsString("closest('[data-location]')",$source);
        self::assertStringContainsString('scrollIntoView',$source);
        self::assertStringNotContainsString('[data-location-tab]',$source);
        self::assertStringNotContainsString('activatePanel',$source);
    }

    public function testCentralMerchantNavigationStillLinksToLocationsPage(): void
    {
        $source=$this->source('includes/merchant-navigation.php');
        self::assertStringContainsString("'locations' => ['Locations', 'Stores and claim scope', '/merchant-locations.php', 'Products & Engagement']",$source);
    }
}
