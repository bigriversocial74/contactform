<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CatalogPublishingContractTest extends TestCase
{
    public function testPublishingAndAssetAuthoritiesDefineRequiredContracts(): void
    {
        $publish=file_get_contents(dirname(__DIR__,2).'/api/catalog/_publish.php');
        $assets=file_get_contents(dirname(__DIR__,2).'/api/catalog/_asset_access.php');
        self::assertIsString($publish);
        self::assertIsString($assets);
        foreach(['catalog_publish_events','catalog_moderation_states','mg_catalog_publish_version','mg_catalog_moderate_product','idempotency_key','fingerprint','mg_audit'] as $needle){
            self::assertStringContainsString($needle,$publish);
        }
        foreach(['catalog_asset_access_policies','catalog_asset_access_grants','catalog_asset_access_events','mg_catalog_can_view_asset','mg_catalog_grant_asset_policy'] as $needle){
            self::assertStringContainsString($needle,$assets);
        }
    }
}
