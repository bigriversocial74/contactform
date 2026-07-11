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

    public function testDedicatedMerchantLocationsRouteUsesSharedMerchantWorkspace(): void
    {
        $source=file_get_contents($this->root.'/merchant-locations.php');
        self::assertIsString($source);

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
        $source=file_get_contents($this->root.'/includes/merchant-locations-view.php');
        self::assertIsString($source);

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
        $source=file_get_contents($this->root.'/assets/css/merchant-locations-redemption.css');
        self::assertIsString($source);

        self::assertStringContainsString('grid-template-columns:repeat(5,minmax(180px,1fr))',$source);
        self::assertStringContainsString('overflow-x:auto',$source);
        self::assertStringContainsString('min-width:180px',$source);
        self::assertStringNotContainsString('grid-template-columns:repeat(3',$source);
        self::assertStringNotContainsString('.mg-locations-side',$source);
        self::assertStringNotContainsString('.mg-locations-layout',$source);
    }

    public function testMerchantLocationReadScopeIncludesLegacyWorkspaceUnlinkedRows(): void
    {
        $source=file_get_contents($this->root.'/api/merchant/locations.php');
        self::assertIsString($source);

        foreach([
            'WHERE ml.merchant_user_id=?',
            '$stmt->execute([$merchantId])',
            'WHERE public_id=? AND merchant_user_id=?',
            'WHERE merchant_user_id=? AND location_code=? AND public_id<>?',
            'SET workspace_id=?,name=?,location_code=?',
            'WHERE id=? AND public_id=? AND merchant_user_id=?',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringNotContainsString('WHERE ml.workspace_id=? AND ml.merchant_user_id=?',$source);
        self::assertStringNotContainsString('WHERE public_id=? AND workspace_id=? AND merchant_user_id=?',$source);
    }

    public function testLocationEditorScrollControllerNoLongerImplementsTabNavigation(): void
    {
        $source=file_get_contents($this->root.'/assets/js/merchant-locations-tabs.js');
        self::assertIsString($source);

        self::assertStringContainsString("querySelector('#location-editor-panel')",$source);
        self::assertStringContainsString("closest('[data-location-open-add]')",$source);
        self::assertStringContainsString("closest('[data-location]')",$source);
        self::assertStringContainsString('scrollIntoView',$source);
        self::assertStringNotContainsString('[data-location-tab]',$source);
        self::assertStringNotContainsString('activatePanel',$source);
    }

    public function testCentralMerchantNavigationStillLinksToLocationsPage(): void
    {
        $source=file_get_contents($this->root.'/includes/merchant-navigation.php');
        self::assertIsString($source);
        self::assertStringContainsString("'locations' => ['Locations', 'Stores and claim scope', '/merchant-locations.php', 'Business Settings']",$source);
    }
}
