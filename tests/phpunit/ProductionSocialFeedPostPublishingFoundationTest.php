<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProductionSocialFeedPostPublishingFoundationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testRealDatabaseBehaviorMatrix(): void
    {
        if ((string)getenv('MG_RUN_SOCIAL_FEED_PUBLISHING_BEHAVIOR') !== '1') {
            self::markTestSkipped('Real-database feed behavior runs in focused validation.');
        }
        if ((string)getenv('MG_DB_HOST') === '') self::markTestSkipped('Database validation requires MG_DB_HOST.');
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/scripts/validate_social_feed_publishing_behavior.php') . ' 2>&1';
        $output = []; $exitCode = 0; exec($command, $output, $exitCode); $raw = implode("\n", $output);
        self::assertSame(0, $exitCode, $raw);
        $result = json_decode($raw, true);
        self::assertIsArray($result, $raw);
        self::assertSame('social_feed_post_publishing_foundation', $result['suite'] ?? null);
        foreach ([
            'draft_create','publish_transition','idempotent_create','discover_visibility','following_visibility',
            'mute_exclusion','block_exclusion','moderation_exclusion','cursor_stability','owner_filters',
            'update_archive_delete','subscriber_plan_requirement','media_safety','safe_projection','rollback_clean',
        ] as $key) self::assertTrue((bool)($result[$key] ?? false), $key . ' failed: ' . $raw);
    }

    public function testCanonicalFeedEnforcesVisibilityRelationshipsModerationAndCursorBounds(): void
    {
        $service = $this->read('api/social/_publishing.php');
        foreach ([
            "['discover','following']", "fp.status='published'", "fp.moderation_status NOT IN ('hidden','removed')",
            "u.status='active'", "pp.status='active'", "pp.visibility IN ('public','unlisted')",
            'social_follows sf', 'social_mutes sm', 'social_blocks sb', 'mg_social_can_view(',
            'MG_SOCIAL_FEED_MAX_LIMIT', "mg_publishing_cursor_decode(\$cursor, 'feed:' . \$mode)",
            'ORDER BY fp.created_at DESC,fp.public_id DESC',
        ] as $needle) self::assertStringContainsString($needle, $service);
    }

    public function testPostMutationsReuseCanonicalTablesAndIdempotencyAuthority(): void
    {
        $endpoint = $this->read('api/social/posts.php');
        $service = $this->read('api/social/_publishing.php');
        foreach ([
            "mg_require_permission('social.posts.create')", "mg_rate_limit('social.posts.write'",
            'mg_engagement_key(', 'mg_engagement_claim(', 'mg_engagement_complete(',
            "['create','update','publish','archive','delete']", 'UPDATE feed_posts SET', 'INSERT INTO feed_posts',
            "status = \$action === 'archive' ? 'archived' : 'retired'",
        ] as $needle) self::assertStringContainsString($needle, $endpoint . $service);
        self::assertStringNotContainsString('CREATE TABLE', $service);
    }

    public function testAttachmentsMediaAndSubscriberAccessAreBoundedAndOwned(): void
    {
        $service = $this->read('api/social/_publishing.php');
        foreach ([
            'MG_SOCIAL_POST_MEDIA_MAX', 'MG_SOCIAL_POST_BODY_MAX', 'mg_publishing_safe_url(',
            "merchant_user_id=?", '(owner_user_id=? OR issuer_user_id=?)',
            "owner_user_id=? AND status='active'", 'Subscriber content requires an active subscription plan.',
            "['image','audio','video','link']",
        ] as $needle) self::assertStringContainsString($needle, $service);
    }

    public function testPublicFeedAndOwnerReadsHaveCorrectCachingThrottlingAndSafeProjection(): void
    {
        $public = $this->read('api/public/feed.php');
        $owner = $this->read('api/social/posts.php');
        foreach ([
            "mg_rate_limit('social.feed.read'", 'Cache-Control: public, max-age=20',
            'Cache-Control: private, no-store', 'Vary: Cookie, Authorization',
        ] as $needle) self::assertStringContainsString($needle, $public);
        foreach ([
            "mg_rate_limit('social.posts.owner_read'", "'id' => (string)\$profile['public_id']",
            "'visibility' => (string)\$profile['visibility']", "'status' => (string)\$profile['status']",
        ] as $needle) self::assertStringContainsString($needle, $owner);
        foreach (['user_id','merchant_user_id','created_by_user_id','metadata_json','email'] as $forbidden) {
            self::assertStringNotContainsString("'{$forbidden}' =>", $owner);
        }
    }

    public function testReportControlsAreVisibilityBoundRateLimitedAndNonDuplicating(): void
    {
        $report = $this->read('api/social/report.php');
        foreach ([
            "mg_rate_limit('social.report.write'", 'mg_engagement_post(', 'mg_social_is_blocked(',
            "status IN ('open','reviewing')", "'duplicate'=>true", 'mg_audit(', 'mg_security_log(',
        ] as $needle) self::assertStringContainsString($needle, $report);
    }

    public function testFeedPageProvidesComposerViewsStatesAndOwnerManagement(): void
    {
        $page = $this->read('feed.php');
        foreach ([
            '/assets/css/social-feed.css','/assets/js/social-feed.js','data-social-feed','data-feed-tab="discover"',
            'data-feed-tab="following"','data-feed-tab="mine"','data-post-composer','data-post-form',
            'data-post-save-draft','data-post-publish','data-owner-filter','data-feed-loading',
            'data-feed-empty','data-feed-error','data-feed-retry','data-feed-pagination',
        ] as $needle) self::assertStringContainsString($needle, $page);
    }

    public function testClientWiresCompleteEngagementAndAvoidsHtmlStringInjection(): void
    {
        $client = $this->read('assets/js/social-feed.js');
        foreach ([
            '/api/public/feed.php','/api/social/posts.php','/api/social/engage.php',
            '/api/public/post-engagement.php','/api/social/report.php','/api/social/relationship.php',
            "['like','love','celebrate','support']", "action: 'share'", "action: 'comment'",
            'owner_publish','owner_archive','owner_delete','textContent','createElement(',
        ] as $needle) self::assertStringContainsString($needle, $client);
        foreach (['.innerHTML =','insertAdjacentHTML(','document.write(','eval('] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $client);
        }
    }

    public function testResponsiveStylesCoverDesktopTabletAndMobile(): void
    {
        $css = $this->read('assets/css/social-feed.css');
        foreach ([
            '.mg-feed-layout','.mg-feed-composer','.mg-feed-card','.mg-feed-actions','.mg-feed-comments',
            '@media(max-width:900px)','@media(max-width:640px)',
        ] as $needle) self::assertStringContainsString($needle, $css);
    }

    public function testFocusedValidationIsRegistered(): void
    {
        $composer = $this->read('composer.json');
        $workflow = $this->read('.github/workflows/social-feed-publishing-validation.yml');
        self::assertStringContainsString('test-social-feed-publishing-behavior', $composer);
        foreach ([
            'MG_RUN_SOCIAL_FEED_PUBLISHING_BEHAVIOR','composer test-social-feed-publishing-behavior',
            'ProductionSocialFeedPostPublishingFoundationTest','social-feed-post-publishing-foundation.spec.js',
            'build_full_upgrade_sql.php','composer test-frontend-contracts','composer test','npm run test:browser',
        ] as $needle) self::assertStringContainsString($needle, $workflow);
    }
}
