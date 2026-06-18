<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class Stage5HCommunicationsTest extends TestCase
{
    public function testSchemaDefinesPreferencesDeliveryJobsAlertsAndThreadSettings(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_5h_notifications_messaging_alerts.sql');
        self::assertIsString($sql);
        foreach (['notification_preferences','notification_delivery_jobs','operational_alerts','message_thread_settings'] as $table) {
            self::assertStringContainsString($table, $sql);
        }
    }

    public function testCommunicationsDashboardIsUserScoped(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/communications/dashboard.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_require_permission('notification.view')", $source);
        self::assertStringContainsString('WHERE n.user_id=?', $source);
        self::assertStringContainsString('WHERE mtp.user_id=?', $source);
        self::assertStringContainsString('WHERE user_id=?', $source);
    }

    public function testPreferencesRequirePermissionAndCsrf(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/communications/preferences.php');
        self::assertIsString($source);
        self::assertStringContainsString('notification.preferences.manage', $source);
        self::assertStringContainsString('mg_require_csrf_for_write', $source);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $source);
    }

    public function testOperationalUpdatesAndThreadSettingsAreOwnerScoped(): void
    {
        $alert = file_get_contents(dirname(__DIR__, 2) . '/api/communications/operational-status.php');
        $thread = file_get_contents(dirname(__DIR__, 2) . '/api/communications/thread-settings.php');
        self::assertIsString($alert);
        self::assertIsString($thread);
        self::assertStringContainsString('operational.alerts.manage', $alert);
        self::assertStringContainsString('AND user_id=?', $alert);
        self::assertStringContainsString('message_thread_participants', $thread);
        self::assertStringContainsString('mtp.user_id=?', $thread);
        self::assertStringContainsString('mg_require_csrf_for_write', $thread);
    }

    public function testExistingHeaderSignalsAreEnhancedNotReplaced(): void
    {
        $header = file_get_contents(dirname(__DIR__, 2) . '/assets/js/header-signals.js');
        self::assertIsString($header);
        self::assertStringContainsString('/api/notifications/index.php', $header);
        self::assertStringContainsString('/api/messages/threads.php', $header);
        self::assertStringContainsString('/notifications.php', $header);
        self::assertStringContainsString('/notification-preferences.php', $header);
        self::assertStringContainsString('/messages.php?thread=', $header);
        self::assertStringContainsString('mg:notifications:refresh', $header);
        self::assertStringContainsString('setNotificationCount', $header);
        self::assertStringContainsString('setMessageCount', $header);
    }

    public function testGiftInboxAndCommunicationsUseSeparatedWorkspaces(): void
    {
        $root = dirname(__DIR__, 2);
        $inbox = file_get_contents($root . '/inbox.php');
        $messages = file_get_contents($root . '/messages.php');
        $notifications = file_get_contents($root . '/notifications.php');
        $preferences = file_get_contents($root . '/notification-preferences.php');
        self::assertIsString($inbox);
        self::assertIsString($messages);
        self::assertIsString($notifications);
        self::assertIsString($preferences);
        self::assertStringContainsString('includes/gift-action-center.php', $inbox);
        self::assertStringContainsString('gift-action-center.css', $inbox);
        self::assertStringContainsString('gift-action-center.js', $inbox);
        self::assertStringContainsString('data-messages-center', $messages);
        self::assertStringContainsString('messages-center.js', $messages);
        self::assertStringContainsString('data-notifications-page', $notifications);
        self::assertStringContainsString('notifications-page.js', $notifications);
        self::assertStringContainsString('data-notification-preferences', $preferences);
        self::assertStringNotContainsString('communications-workspace.php', $inbox);
        self::assertStringNotContainsString('data-preferences-modal', $preferences);
    }
}
