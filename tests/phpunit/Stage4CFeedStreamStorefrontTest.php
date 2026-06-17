<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage4CFeedStreamStorefrontTest extends TestCase
{
    public function testSchemaDefinesFeedVersionsBindingsStorefrontsAndEngagement(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_4c_feed_stream_storefronts.sql');
        self::assertIsString($sql);
        foreach (['merchant_storefronts','feed_posts','feed_post_versions','feed_post_elements','pppm_feed_bindings','content_engagement_events'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('UNIQUE KEY uq_pppm_feed_binding_item (pppm_item_id)', $sql);
        self::assertStringContainsString("ENUM('simple','image','audio','video','greeting_card','multimedia_card','collab')", $sql);
    }

    public function testPublishedAndPromotedFeedContentIsImmutable(): void
    {
        $posts = file_get_contents(dirname(__DIR__, 2) . '/api/feed/posts.php');
        $bind = file_get_contents(dirname(__DIR__, 2) . '/api/feed/bind.php');
        self::assertIsString($posts);
        self::assertIsString($bind);
        self::assertStringContainsString('Promoted or retired feed content cannot be changed in place.', $posts);
        self::assertStringContainsString("version_status = 'published'", $posts);
        self::assertStringContainsString('This issued PPPM item already has immutable envelope contents.', $bind);
        self::assertStringContainsString('pppm_feed_bindings', $bind);
    }

    public function testGiftStreamUsesCursorLoadingAndScopedMedia(): void
    {
        $stream = file_get_contents(dirname(__DIR__, 2) . '/api/feed/stream.php');
        $media = file_get_contents(dirname(__DIR__, 2) . '/api/feed/media.php');
        self::assertIsString($stream);
        self::assertIsString($media);
        self::assertStringContainsString('next_cursor', $stream);
        self::assertStringContainsString('pppm_feed_bindings', $stream);
        self::assertStringContainsString('/api/feed/media.php?asset=', $stream);
        self::assertStringContainsString('recipient_user_id = ?', $media);
        self::assertStringContainsString('str_starts_with', $media);
    }

    public function testReelViewerSupportsVerticalAndHorizontalGesturesAndPreload(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/gift-stream.js');
        self::assertIsString($source);
        self::assertStringContainsString('touchStartX', $source);
        self::assertStringContainsString('touchStartY', $source);
        self::assertStringContainsString('showSheet(true)', $source);
        self::assertStringContainsString('move(dy < 0 ? 1 : -1)', $source);
        self::assertStringContainsString('preloadNeighbors', $source);
        self::assertStringContainsString('progress_25', $source);
        self::assertStringContainsString('progress_50', $source);
        self::assertStringContainsString('progress_75', $source);
    }

    public function testProductAndStorefrontPagesUsePublishedVersions(): void
    {
        $product = file_get_contents(dirname(__DIR__, 2) . '/api/public/product.php');
        $store = file_get_contents(dirname(__DIR__, 2) . '/api/storefront/profile.php');
        self::assertIsString($product);
        self::assertIsString($store);
        self::assertStringContainsString("cp.status = 'published'", $product);
        self::assertStringContainsString("cpv.version_status = 'published'", $product);
        self::assertStringContainsString("ms.status = 'published'", $store);
        self::assertStringContainsString('/api/public/media.php?asset=', $store);
    }

    public function testEngagementIsSeparateFromLifecycleWithMilestoneSummaries(): void
    {
        $engagement = file_get_contents(dirname(__DIR__, 2) . '/api/feed/engagement.php');
        self::assertIsString($engagement);
        self::assertStringContainsString('INSERT INTO content_engagement_events', $engagement);
        self::assertStringContainsString('content_opened', $engagement);
        self::assertStringContainsString('claim_opened', $engagement);
        self::assertStringContainsString('mg_pppm_record_event', $engagement);
    }

    public function testInboxLoadActionRoutesToGiftStream(): void
    {
        $launcher = file_get_contents(dirname(__DIR__, 2) . '/assets/js/gift-stream-launch.js');
        $footer = file_get_contents(dirname(__DIR__, 2) . '/includes/footer.php');
        self::assertIsString($launcher);
        self::assertIsString($footer);
        self::assertStringContainsString('[data-item-action="load"]', $launcher);
        self::assertStringContainsString('/gift-stream.php?item=', $launcher);
        self::assertStringContainsString('/assets/js/gift-stream-launch.js', $footer);
    }
}
