<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminMerchantCatalogOperationsContractTest extends TestCase
{
    public function testStage18mAddsLifecycleStatesPermissionsAndEvents(): void
    {
        $root=dirname(__DIR__,2);
        $migration=file_get_contents($root.'/database/stage_18m_admin_merchant_catalog_operations.sql');
        $manifest=file_get_contents($root.'/config/migrations.php');
        self::assertIsString($migration);
        self::assertIsString($manifest);
        self::assertStringContainsString('stage_18m_admin_merchant_catalog_operations.sql',$manifest);
        self::assertStringContainsString("ENUM('draft','published','suspended','archived')",$migration);
        self::assertStringContainsString("ENUM('draft','review','published','paused','archived')",$migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS merchant_catalog_operation_events',$migration);
        foreach(['admin.merchants.view','admin.merchants.manage','admin.catalog.view','admin.catalog.manage'] as $permission){
            self::assertStringContainsString("'{$permission}'",$migration);
        }
        self::assertStringContainsString("r.slug IN ('admin','super_admin')",$migration);
    }

    public function testQueueCoversWorkspacesStorefrontsProductsAndAssets(): void
    {
        $root=dirname(__DIR__,2);
        $queue=file_get_contents($root.'/api/admin/merchant-catalog/_list.php');
        $endpoint=file_get_contents($root.'/api/admin/merchant-catalog/queue.php');
        self::assertIsString($queue);
        self::assertIsString($endpoint);
        foreach(['merchant_workspaces','merchant_storefronts','catalog_products','catalog_assets'] as $table){
            self::assertStringContainsString($table,$queue);
        }
        self::assertStringContainsString('MG_ADMIN_MC_MAX_LIMIT',file_get_contents($root.'/api/admin/merchant-catalog/_common.php'));
        self::assertStringContainsString("mg_require_method('GET')",$endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.merchant_catalog.queue'",$endpoint);
        self::assertStringContainsString('Cache-Control: private, no-store, max-age=0',$endpoint);
    }

    public function testDetailReadersExposeCanonicalOperationalContextWithoutSecrets(): void
    {
        $root=dirname(__DIR__,2);
        $workspace=file_get_contents($root.'/api/admin/merchant-catalog/_detail_workspace.php');
        $store=file_get_contents($root.'/api/admin/merchant-catalog/_detail_store.php');
        $catalog=file_get_contents($root.'/api/admin/merchant-catalog/_detail_catalog.php');
        foreach([$workspace,$store,$catalog] as $source)self::assertIsString($source);
        foreach(['merchant_locations','merchant_team_members','merchant_onboarding_steps','merchant_payment_readiness','catalog_products','catalog_assets'] as $table){
            self::assertStringContainsString($table,$workspace);
        }
        self::assertStringContainsString('mg_storefront_readiness',$store);
        foreach(['catalog_product_versions','catalog_product_version_assets','merchant_storefront_revision_products','catalog_builder_drafts','feed_posts','catalog_pppm_templates'] as $table){
            self::assertStringContainsString($table,$catalog);
        }
        self::assertStringNotContainsString('password_hash',$workspace.$store.$catalog);
        self::assertStringNotContainsString('token_hash',$workspace.$store.$catalog);
        self::assertStringNotContainsString('provider_secret',$workspace.$store.$catalog);
    }

    public function testOperationsAreCsrfProtectedTransactionalAndAudited(): void
    {
        $root=dirname(__DIR__,2);
        $endpoint=file_get_contents($root.'/api/admin/merchant-catalog/operate.php');
        $actions=file_get_contents($root.'/api/admin/merchant-catalog/_actions.php');
        self::assertIsString($endpoint);
        self::assertIsString($actions);
        self::assertStringContainsString("mg_require_method('POST')",$endpoint);
        self::assertStringContainsString('mg_require_csrf_for_write($input)',$endpoint);
        self::assertStringContainsString("mg_rate_limit('admin.merchant_catalog.operate'",$endpoint);
        self::assertStringContainsString('$pdo->beginTransaction()',$endpoint);
        self::assertStringContainsString('$pdo->commit()',$endpoint);
        self::assertStringContainsString('$pdo->rollBack()',$endpoint);
        self::assertStringContainsString("mg_audit('admin_merchant_catalog_'",$endpoint);
        self::assertStringContainsString("mg_event('admin.merchant_catalog.'",$endpoint);
        self::assertStringContainsString('at least 8 characters',file_get_contents($root.'/api/admin/merchant-catalog/_common.php'));
        self::assertStringContainsString('FOR UPDATE',$actions);
    }

    public function testLifecycleActionsUseSharedReadinessAndSafeCascades(): void
    {
        $root=dirname(__DIR__,2);
        $actions=file_get_contents($root.'/api/admin/merchant-catalog/_actions.php');
        $merchant=file_get_contents($root.'/api/admin/merchant-catalog/_merchant_lifecycle.php');
        $catalog=file_get_contents($root.'/api/catalog/_operations_lifecycle.php');
        foreach([$actions,$merchant,$catalog] as $source)self::assertIsString($source);
        foreach(['activate_workspace','suspend_workspace','publish_storefront','suspend_storefront','publish_product','pause_product','quarantine_asset','retry_asset'] as $action){
            self::assertStringContainsString("'{$action}'",$actions.$merchant.$catalog);
        }
        self::assertStringContainsString('mg_storefront_readiness',$merchant);
        self::assertStringContainsString('mg_catalog_operations_product_readiness',$catalog);
        self::assertStringContainsString("status='paused'",$actions);
        self::assertStringContainsString("status='suspended'",$actions);
        self::assertStringNotContainsString('INSERT INTO catalog_product_versions',$actions.$catalog);
    }
}
