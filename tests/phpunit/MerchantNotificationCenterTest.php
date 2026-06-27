<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantNotificationCenterTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source, $path . ' should be readable.');
        return $source;
    }

    public function testMerchantNotificationPageUsesMerchantShell(): void
    {
        $page = $this->source('merchant-notifications.php');
        self::assertStringContainsString('$merchantView=\'notifications\'', $page);
        self::assertStringContainsString('includes/merchant-workspace.php', $page);
        self::assertStringContainsString('merchant-notifications.css', $page);
    }

    public function testMerchantNavigationIncludesNotificationCenter(): void
    {
        $shell = $this->source('includes/merchant-workspace.php');
        self::assertStringContainsString("'notifications'=>['Notifications'", $shell);
        self::assertStringContainsString('/merchant-notifications.php', $shell);
        self::assertStringContainsString('Tips, voucher messages, alerts', $shell);
    }

    public function testMerchantViewRoutesNotificationPanel(): void
    {
        $view = $this->source('includes/merchant-view.php');
        $panel = $this->source('includes/merchant-notifications-view.php');
        self::assertStringContainsString("merchantView==='notifications'", $view);
        self::assertStringContainsString('merchant-notifications-view.php', $view);
        foreach (['data-merchant-notification-feed','data-merchant-notification-tabs','data-filter="tips"','data-filter="messages"','data-filter="redemptions"'] as $needle) {
            self::assertStringContainsString($needle, $panel);
        }
    }

    public function testMerchantNotificationApiMergesAlertsAndNotifications(): void
    {
        $api = $this->source('api/merchant/notifications.php');
        foreach ([
            "require_once __DIR__ . '/_merchant.php';",
            'mg_merchant_ensure_workspace($pdo, $user)',
            'operational_alerts',
            'notifications',
            'wallet_reward_message',
            'tip_received',
            'mg_merchant_notification_action_url',
            '/merchant-notifications.php?filter=tips',
            '/messages.php?thread=',
            '/merchant-claims.php',
            'mg_require_csrf_for_write($input)',
            'UPDATE notifications SET read_at=COALESCE(read_at,NOW())',
            'UPDATE operational_alerts SET status=?',
        ] as $needle) {
            self::assertStringContainsString($needle, $api);
        }
    }

    public function testMerchantTipNotificationsRouteToMerchantNotificationCenter(): void
    {
        $source = $this->source('api/tips/_notifications.php');
        self::assertStringContainsString("'/merchant-notifications.php?filter=tips'", $source);
        self::assertStringContainsString("recipient_wallet_owner_type", $source);
        self::assertStringContainsString('mg_tip_notify_recipient', $source);
    }

    public function testMerchantWorkspaceJavascriptLoadsAndAcknowledgesNotificationFeed(): void
    {
        $js = $this->source('assets/js/merchant-workspace.js');
        foreach ([
            'async function loadNotifications()',
            '/api/merchant/notifications.php?filter=',
            'data-merchant-notification-feed',
            'data-notification-done',
            "if(view==='notifications')return loadNotifications();",
        ] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
    }
}
