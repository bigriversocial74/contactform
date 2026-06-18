<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SocialFeedMediaPersistenceContractTest extends TestCase
{
    public function testMediaPersistenceFilesExist(): void
    {
        $root=dirname(__DIR__,2);
        self::assertFileExists($root.'/database/stage_18h_feed_media_assets.sql');
        self::assertFileExists($root.'/api/social/_media_assets.php');
        self::assertFileExists($root.'/api/social/post-media.php');
        self::assertFileExists($root.'/scripts/cleanup_feed_media.php');
    }

    public function testMigrationAndManifestIncludeFeedAssets(): void
    {
        $root=dirname(__DIR__,2);
        $manifest=file_get_contents($root.'/config/migrations.php');
        $migration=file_get_contents($root.'/database/stage_18h_feed_media_assets.sql');
        self::assertIsString($manifest);
        self::assertIsString($migration);
        self::assertStringContainsString('stage_18h_feed_media_assets.sql',$manifest);
        self::assertStringContainsString('feed_post_assets',$migration);
        self::assertStringContainsString('uq_feed_post_assets_post_asset',$migration);
        self::assertStringContainsString('idx_feed_post_assets_post_order',$migration);
    }

    public function testUploadEndpointHasManagedLifecycleGuards(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/social/media-upload.php');
        self::assertIsString($source);
        self::assertStringContainsString('is_uploaded_file',$source);
        self::assertStringContainsString('move_uploaded_file',$source);
        self::assertStringContainsString('40000000',$source);
        self::assertStringContainsString('1073741824',$source);
        self::assertStringContainsString('feed_state',$source);
        self::assertStringContainsString('unattached',$source);
    }
}
