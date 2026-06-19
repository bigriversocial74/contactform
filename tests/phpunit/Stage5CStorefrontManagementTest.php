<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class Stage5CStorefrontManagementTest extends TestCase
{
    public function testSchemaDefinesRevisionedStorefrontAndPlacementTables(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_5c_storefront_management.sql');
        self::assertIsString($sql);
        foreach(['merchant_storefront_revisions','merchant_storefront_revision_products','merchant_storefront_states'] as $table){
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);
        }
    }

    public function testMerchantStorefrontIsOwnerScopedAndPermissionProtected(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/merchant/storefront.php');
        self::assertIsString($api);
        self::assertStringContainsString("mg_require_permission('storefront.manage')",$api);
        self::assertStringContainsString('mg_storefront_owned',$api);
        self::assertStringContainsString('merchant_user_id<>?',$api);
        self::assertStringContainsString('mg_require_csrf_for_write',$api);
    }

    public function testPublishingUsesImmutableRevisionState(): void
    {
        $api=file_get_contents(dirname(__DIR__,2).'/api/merchant/storefront.php');
        self::assertIsString($api);
        self::assertStringContainsString("revision_status='retired'",$api);
        self::assertStringContainsString("revision_status='published'",$api);
        self::assertStringContainsString('published_revision_id',$api);
        self::assertStringContainsString('draft_revision_id=NULL',$api);
    }

    public function testProductPlacementControlsVisibilityFeatureAndOrder(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_5c_storefront_management.sql');
        $js=file_get_contents(dirname(__DIR__,2).'/assets/js/merchant-storefront.js');
        self::assertIsString($sql);
        self::assertIsString($js);
        self::assertStringContainsString('sort_order',$sql);
        self::assertStringContainsString('is_featured',$sql);
        self::assertStringContainsString("visibility ENUM('visible','hidden')",$sql);
        self::assertStringContainsString('data-product-featured',$js);
        self::assertStringContainsString('data-product-move',$js);
        self::assertStringContainsString('data-product-visible',$js);
    }

    public function testPublicStorefrontUsesOnlyPublishedRevision(): void
    {
        $route=file_get_contents(dirname(__DIR__,2).'/api/storefront/profile.php');
        $api=file_get_contents(dirname(__DIR__,2).'/api/storefront/_profile_legacy.php');
        self::assertIsString($route);
        self::assertIsString($api);
        self::assertStringContainsString("require __DIR__ . '/profile-v1.php';",$route);
        self::assertStringContainsString('published_revision_id',$api);
        self::assertStringContainsString("revision_status='published'",$api);
        self::assertStringContainsString("rp.visibility='visible'",$api);
    }

    public function testMerchantPagesReuseSharedShell(): void
    {
        foreach(['merchant-storefront.php','merchant-storefront-preview.php'] as $file){
            $source=file_get_contents(dirname(__DIR__,2).'/'.$file);
            self::assertIsString($source);
            self::assertStringContainsString('includes/merchant-workspace.php',$source);
        }
    }
}
