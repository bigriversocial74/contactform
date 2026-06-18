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
}
