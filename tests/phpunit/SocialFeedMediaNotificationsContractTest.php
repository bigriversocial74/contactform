<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SocialFeedMediaNotificationsContractTest extends TestCase
{
    public function testFeedComposerProvidesTypedMediaUploads(): void
    {
        $root=dirname(__DIR__,2);
        $page=file_get_contents($root.'/feed.php');
        $client=file_get_contents($root.'/assets/js/social-feed-upload.js');
        $endpoint=file_get_contents($root.'/api/social/media-upload.php');
        self::assertIsString($page);
        self::assertIsString($client);
        self::assertIsString($endpoint);
        foreach([
            '/assets/css/social-feed-upload.css',
            '/assets/js/social-feed-upload.js',
            'data-feed-upload-input="image"',
            'data-feed-upload-input="video"',
            'data-feed-upload-input="audio"',
            'data-feed-upload-list',
            '0 of 8 attached',
        ] as $needle) self::assertStringContainsString($needle,$page);
        self::assertStringContainsString('/api/social/media-upload.php',$client);
        self::assertStringContainsString("data.append('media'",$client);
        self::assertStringContainsString('file.name',$client);
        self::assertStringContainsString("mg_require_permission('social.posts.create')",$endpoint);
        self::assertStringContainsString('is_uploaded_file',$endpoint);
        self::assertStringContainsString('move_uploaded_file',$endpoint);
        self::assertStringContainsString('new finfo(FILEINFO_MIME_TYPE)',$endpoint);
        self::assertStringContainsString('INSERT INTO catalog_assets',$endpoint);
        self::assertStringContainsString("'ready'",$endpoint);
    }

    public function testNotificationPreferencesIncludeSocialActivityAndFilterInAppResults(): void
    {
        $root=dirname(__DIR__,2);
        $helper=file_get_contents($root.'/api/communications/_communications.php');
        $preferences=file_get_contents($root.'/api/communications/preferences.php');
        $index=file_get_contents($root.'/api/notifications/index.php');
        self::assertIsString($helper);
        self::assertIsString($preferences);
        self::assertIsString($index);
        self::assertStringContainsString('function mg_create_notification',$helper);
        self::assertStringContainsString('INSERT INTO notifications',$helper);
        self::assertStringContainsString('mg_queue_notification_deliveries',$helper);
        self::assertStringContainsString("'social'",$preferences);
        self::assertStringContainsString('notification_preferences np',$index);
        self::assertStringContainsString('COALESCE(np.in_app_enabled,1)=1',$index);
    }

    public function testFollowMessageAndGiftRecipientSignalsAreConnected(): void
    {
        $root=dirname(__DIR__,2);
        $follow=file_get_contents($root.'/api/social/_follow_notification.php');
        $relationship=file_get_contents($root.'/api/social/relationship.php');
        $messages=file_get_contents($root.'/api/messages/send.php');
        $gift=file_get_contents($root.'/api/gifts/_gift.php');
        $microgift=file_get_contents($root.'/api/microgifts/_issue_signal.php');
        $issue=file_get_contents($root.'/api/microgifts/issue.php');
        foreach([$follow,$relationship,$messages,$gift,$microgift,$issue] as $source) self::assertIsString($source);
        self::assertStringContainsString('mg_create_notification',$follow);
        self::assertStringContainsString('social.follow.',$follow);
        self::assertStringContainsString('mg_follow_notification_send',$relationship);
        self::assertStringContainsString("'message'",$messages);
        self::assertStringContainsString('message.thread.',$messages);
        self::assertStringContainsString("'aggregate'=>true",$messages);
        self::assertStringContainsString('message_thread_settings',$messages);
        self::assertStringContainsString('mg_create_notification',$gift);
        self::assertStringContainsString('gift.sent.',$gift);
        self::assertStringContainsString('mg_create_notification',$microgift);
        self::assertStringContainsString('microgift.issued.',$microgift);
        self::assertStringContainsString('mg_microgift_issue_signal',$issue);
    }
}
