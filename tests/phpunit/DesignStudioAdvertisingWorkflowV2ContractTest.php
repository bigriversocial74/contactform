<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DesignStudioAdvertisingWorkflowV2ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testAdditiveMigrationAndManifestContract(): void
    {
        $migration = file_get_contents($this->root . '/database/20260716_design_studio_advertising_workflow_v2.sql');
        $manifest = file_get_contents($this->root . '/config/migrations.php');
        self::assertIsString($migration);
        self::assertIsString($manifest);
        self::assertStringContainsString('merchant_advertising_assets', $migration);
        self::assertStringContainsString('platform_copy_json', $migration);
        self::assertStringContainsString('ON DELETE SET NULL', $migration);
        self::assertStringContainsString('20260716_design_studio_advertising_workflow_v2.sql', $manifest);
    }

    public function testSavedCreativeApiUsesMerchantAuthorityAndPersistentAssets(): void
    {
        $api = file_get_contents($this->root . '/api/merchant/design-advertising-assets.php');
        self::assertIsString($api);
        self::assertStringContainsString("mg_merchant_require_permission", $api);
        self::assertStringContainsString('mg_require_csrf_for_write', $api);
        self::assertStringContainsString('mg_rate_limit', $api);
        self::assertStringContainsString('mg_storage_store_uploaded_file', $api);
        self::assertStringContainsString('catalog_assets', $api);
        self::assertStringContainsString('idempotency_key', $api);
        self::assertStringContainsString('merchant_user_id=?', $api);
        self::assertStringContainsString('mg_audit', $api);
    }

    public function testCalendarSupportsSchedulingCopyAndBulkOperations(): void
    {
        $api = file_get_contents($this->root . '/api/merchant/design-content-calendar.php');
        $javascript = file_get_contents($this->root . '/assets/js/personal-agent-design-studio-calendar.js');
        self::assertIsString($api);
        self::assertIsString($javascript);
        foreach (['three_per_week','twice_per_week','custom','campaign_theme','platform_copy','bulk_update','bulk_delete','duplicate'] as $marker) {
            self::assertStringContainsString($marker, $api);
        }
        foreach (['data-calendar-bulk','data-calendar-copy','dragstart','data-calendar-filter','design-studio:schedule-context'] as $marker) {
            self::assertStringContainsString($marker, $javascript);
        }
    }

    public function testAdvertisingTabIsMerchantEntitlementGated(): void
    {
        $page = file_get_contents($this->root . '/saves.php');
        self::assertIsString($page);
        self::assertStringContainsString("mg_user_package_context", $page);
        self::assertStringContainsString("merchant_access", $page);
        self::assertStringContainsString('data-saves-tab="advertising"', $page);
        self::assertStringContainsString('data-saved-advertising', $page);
    }

    public function testCreativeSavingIsExplicitAndNeverAutomatic(): void
    {
        $javascript = file_get_contents($this->root . '/assets/js/design-studio-creative-save.js');
        self::assertIsString($javascript);
        self::assertStringContainsString('Save Creative Asset', $javascript);
        self::assertStringContainsString("addEventListener('click'", $javascript);
        self::assertStringNotContainsString('setInterval', $javascript);
        self::assertStringNotContainsString('beforeunload', $javascript);
    }
}
