<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RecipientNotificationPipelineContractTest extends TestCase
{
    public function testNotificationMigrationAddsActorDedupeAndAggregationMetadata(): void
    {
        $root=dirname(__DIR__,2);
        $manifest=file_get_contents($root.'/config/migrations.php');
        $migration=file_get_contents($root.'/database/stage_18i_recipient_notifications.sql');
        self::assertIsString($manifest);
        self::assertIsString($migration);
        self::assertStringContainsString("'stage_18i_recipient_notifications.sql'",$manifest);
        self::assertGreaterThan(
            strpos($manifest,"'stage_18h_feed_media_assets.sql'"),
            strpos($manifest,"'stage_18i_recipient_notifications.sql'")
        );
        foreach([
            'actor_user_id',
            'event_key',
            'occurrence_count',
            'context_json',
            'uq_notifications_user_event',
            'fk_notifications_actor',
            "'stage_18i_recipient_notifications'",
        ] as $needle) self::assertStringContainsString($needle,$migration);
    }

    public function testNotificationServiceHonorsPreferencesSchedulingAndDedupe(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/api/communications/_communications.php');
        self::assertIsString($source);
        foreach([
            'mg_notification_recipient_is_active',
            'mg_notification_safe_action_url',
            'mg_notification_event_key',
            'mg_notification_enabled_channels',
            'mg_notification_apply_quiet_hours',
            'mg_notification_delivery_time',
            "'digest_mode'",
            "'quiet_hours_start'",
            "'quiet_hours_end'",
            'ON DUPLICATE KEY UPDATE',
            'LAST_INSERT_ID(id)',
            'occurrence_count=occurrence_count+1',
            'read_at=NULL',
            'mg_queue_notification_deliveries',
        ] as $needle) self::assertStringContainsString($needle,$source);
    }

    public function testRecipientSignalsUseDurableEventKeysAndMessageAggregation(): void
    {
        $root=dirname(__DIR__,2);
        $follow=file_get_contents($root.'/api/social/_follow_notification.php');
        $message=file_get_contents($root.'/api/messages/send.php');
        $gift=file_get_contents($root.'/api/gifts/_gift.php');
        $microgift=file_get_contents($root.'/api/microgifts/_issue_signal.php');
        foreach([$follow,$message,$gift,$microgift] as $source) self::assertIsString($source);
        self::assertStringContainsString('social.follow.',$follow);
        self::assertStringContainsString('event_version',$follow);
        self::assertStringContainsString('message.thread.',$message);
        self::assertStringContainsString("'aggregate'=>true",$message);
        self::assertStringContainsString("'message_id'=>\$messagePublicId",$message);
        self::assertStringContainsString('gift.sent.',$gift);
        self::assertStringContainsString('microgift.issued.',$microgift);
        foreach([$follow,$message,$gift,$microgift] as $source) self::assertStringContainsString('actor_user_id',$source);
    }

    public function testNotificationCenterAndSettingsExposeRecipientActivity(): void
    {
        $root=dirname(__DIR__,2);
        $index=file_get_contents($root.'/api/notifications/index.php');
        $read=file_get_contents($root.'/api/notifications/read.php');
        $preferences=file_get_contents($root.'/api/communications/preferences.php');
        $page=file_get_contents($root.'/notifications.php');
        $settings=file_get_contents($root.'/notification-preferences.php');
        $client=file_get_contents($root.'/assets/js/notifications-page.js');
        $settingsClient=file_get_contents($root.'/assets/js/notification-preferences.js');
        foreach([$index,$read,$preferences,$page,$settings,$client,$settingsClient] as $source) self::assertIsString($source);
        self::assertStringContainsString('occurrence_count',$index);
        self::assertStringContainsString('actor_profile.avatar_url',$index);
        self::assertStringContainsString("'context'=>\$context",$index);
        self::assertStringContainsString("COALESCE(np.in_app_enabled,1)=1",$read);
        self::assertStringContainsString('Gifts and Microgifts',$preferences);
        self::assertStringContainsString('New followers',$preferences);
        self::assertStringContainsString('new DateTimeZone',$preferences);
        self::assertStringContainsString('/assets/css/recipient-notifications.css',$page);
        self::assertStringContainsString('/assets/css/recipient-notifications.css',$settings);
        self::assertStringContainsString('mg-notification-count',$client);
        self::assertStringContainsString('data-notification-open',$client);
        self::assertStringContainsString('Quiet hours and timezone',$settingsClient);
    }
}
