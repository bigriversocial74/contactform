<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PersistentMediaStorageContractTest extends TestCase
{
    public function testPersistentStorageFilesExist(): void
    {
        $root=dirname(__DIR__,2);
        self::assertFileExists($root.'/includes/storage.php');
        self::assertFileExists($root.'/scripts/check_media_storage.php');
        self::assertFileExists($root.'/scripts/migrate_feed_media_storage.php');
        self::assertFileExists($root.'/docs/persistent-media-storage.md');
    }

    public function testApplicationLoadsPersistentStorageAdapter(): void
    {
        $root=dirname(__DIR__,2);
        $core=file_get_contents($root.'/includes/app-core.php');
        $config=file_get_contents($root.'/api/config.php');
        $storage=file_get_contents($root.'/includes/storage.php');
        self::assertIsString($core);
        self::assertIsString($config);
        self::assertIsString($storage);
        self::assertStringContainsString("require_once __DIR__.'/storage.php';",$core);
        self::assertStringContainsString('MG_MEDIA_STORAGE_ROOT',$config);
        self::assertStringContainsString('MG_REQUIRE_PERSISTENT_MEDIA_STORAGE',$config);
        self::assertStringContainsString('function mg_storage_assert_ready',$storage);
        self::assertStringContainsString('.microgifter-storage',$storage);
        self::assertStringContainsString('move_uploaded_file',$storage);
    }

    public function testFeedUploadsUseProtectedPersistentStorage(): void
    {
        $root=dirname(__DIR__,2);
        $upload=file_get_contents($root.'/api/social/media-upload.php');
        $delivery=file_get_contents($root.'/api/public/media.php');
        self::assertIsString($upload);
        self::assertIsString($delivery);
        self::assertStringContainsString('mg_storage_store_uploaded_file',$upload);
        self::assertStringContainsString('persistent_local',$upload);
        self::assertStringContainsString('mg_storage_asset_public_url',$upload);
        self::assertStringContainsString('feed_post_assets',$delivery);
        self::assertStringContainsString('mg_social_can_view',$delivery);
        self::assertStringContainsString('mg_storage_resolve_asset_path',$delivery);
        self::assertStringContainsString('Accept-Ranges: bytes',$delivery);
    }
}
