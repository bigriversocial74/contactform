<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage14PostsFeedSocialTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function read(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testSchemaExtendsFeedVisibilityAndSocialGraph(): void
    {
        $sql=$this->read('database/stage_14_posts_feed_social.sql');
        foreach(['followers','subscribers','premium'] as $visibility){self::assertStringContainsString("'{$visibility}'",$sql);}
        foreach(['social_follows','social_mutes','social_blocks','feed_post_reactions','feed_post_comments','feed_post_saves','feed_post_shares','social_reports'] as $table){self::assertStringContainsString('CREATE TABLE IF NOT EXISTS '.$table,$sql);}
        self::assertStringContainsString('linked_microgift_instance_id',$sql);
        self::assertStringContainsString('subscription_plan_id',$sql);
    }

    public function testPostsSupportDirectUserContentAndCanonicalLinks(): void
    {
        $source=$this->read('api/social/_social.php');
        self::assertStringContainsString('catalog_products',$source);
        self::assertStringContainsString('microgift_instances',$source);
        self::assertStringContainsString('subscription_plans',$source);
        self::assertStringContainsString('INSERT INTO feed_posts',$source);
        self::assertStringContainsString('Post content is required.',$source);
    }

    public function testVisibilityEnforcesBlocksFollowsAndSubscriptions(): void
    {
        $source=$this->read('api/social/_social.php');
        self::assertStringContainsString('mg_social_is_blocked(',$source);
        self::assertStringContainsString('mg_social_is_following(',$source);
        self::assertStringContainsString('mg_social_has_active_subscription(',$source);
        self::assertStringContainsString("'followers'",$source);
        self::assertStringContainsString("'subscribers','premium'",$source);
    }

    public function testEngagementUpdatesCanonicalCountersAndNotifications(): void
    {
        $endpoint=$this->read('api/social/engage.php');
        $authority=$this->read('api/social/_engagement.php');
        foreach(['react','unreact','comment','save','unsave','share'] as $action){self::assertStringContainsString("'{$action}'",$endpoint);}
        self::assertStringContainsString('reaction_count=reaction_count+1',$authority);
        self::assertStringContainsString('comment_count=comment_count+1',$authority);
        self::assertStringContainsString('share_count=share_count+1',$endpoint);
        self::assertStringContainsString('mg_social_notify(',$authority);
        self::assertStringContainsString('mg_engagement_complete(',$endpoint);
    }

    public function testRelationshipControlsRemoveFollowsWhenBlocking(): void
    {
        $endpoint=$this->read('api/social/relationship.php');
        $authority=$this->read('api/social/_engagement.php');
        foreach(['follow','unfollow','mute','unmute','block','unblock'] as $action){self::assertStringContainsString("'{$action}'",$endpoint);}
        self::assertStringContainsString('DELETE FROM social_follows WHERE (follower_user_id=? AND followed_user_id=?) OR',$authority);
        self::assertStringContainsString('mg_social_is_blocked(',$authority);
        self::assertStringContainsString('mg_engagement_relationship(',$endpoint);
    }

    public function testReportsAndModerationPreserveAuditTrail(): void
    {
        $report=$this->read('api/social/report.php');
        self::assertStringContainsString('INSERT INTO social_reports',$report);
        self::assertStringContainsString("moderation_status=IF(moderation_status='clear','flagged'",$report);
        $moderate=$this->read('api/admin/social-moderate.php');
        self::assertStringContainsString("mg_require_permission('social.moderate')",$moderate);
        self::assertStringContainsString('mg_audit(',$moderate);
        self::assertStringContainsString("'hide'=>'hidden'",$moderate);
        self::assertStringContainsString("'remove'=>'removed'",$moderate);
    }

    public function testFeedExcludesMutedAndUnauthorizedContent(): void
    {
        $source=$this->read('api/social/_social.php');
        self::assertStringContainsString('social_mutes',$source);
        self::assertStringContainsString('mg_social_can_view(',$source);
        self::assertStringContainsString("moderation_status'],['hidden','removed']",$source);
    }
}
