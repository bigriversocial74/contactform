<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StampReceiptNotificationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root() . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testReceiptNotificationHelperCreatesMerchantNotificationsOnly(): void
    {
        $helper = $this->read('api/stamps/_receipt_notifications.php');

        foreach ([
            'includes/pwa-push.php',
            'mg_stamp_receipt_notify_merchant',
            'notifications',
            'context_json',
            'event_key',
            'stamp_purchase_id',
            'receipt_url',
            'notification_surface',
            'merchant_notifications',
            'inbox_receipt',
            'mg_pwa_push_queue_for_notification',
            '/stamp-receipt.php?purchase=',
            'stamp_purchase_created',
            'stamp_purchase_credited',
            'stamp_purchase_failed',
            'stamp_purchase_cancelled',
            'stamp_purchase_receipt_sent',
        ] as $marker) {
            self::assertStringContainsString($marker, $helper);
        }

        self::assertStringNotContainsString('/inbox.php', $helper, 'Stamp receipt notifications must not create an Inbox receipt surface.');
    }

    public function testStampPurchaseLifecycleCreatesReceiptNotifications(): void
    {
        $purchase = $this->read('api/stamps/purchase.php');
        $helper = $this->read('api/stamps/_purchases.php');
        $reconciliation = $this->read('api/stamps/reconciliation-action.php');

        foreach (['mg_stamp_receipt_notify_merchant', "'created'", 'receipt_notification'] as $marker) {
            self::assertStringContainsString($marker, $purchase);
        }

        foreach (['require_once __DIR__ . \'/_receipt_notifications.php\'', 'mg_stamp_receipt_notify_merchant', "'credited'", 'receipt_notification'] as $marker) {
            self::assertStringContainsString($marker, $helper);
        }

        foreach (['mg_stamp_receipt_notify_merchant', "'failed'", "'cancelled'", 'receipt_notification', 'stamps.purchase_reconciliation_'] as $marker) {
            self::assertStringContainsString($marker, $reconciliation);
        }
    }

    public function testMerchantCanResendReceiptNotificationWithoutInboxReceipt(): void
    {
        $endpoint = $this->read('api/stamps/receipt-notification.php');
        $merchantJs = $this->read('assets/js/merchant-stamps.js');
        $notificationsView = $this->read('includes/merchant-notifications-view.php');

        foreach ([
            'mg_require_api_user',
            'mg_require_method(\'POST\')',
            'mg_require_csrf_for_write',
            'mg_stamp_purchase_load($pdo, $accountUserId',
            'mg_stamp_receipt_notify_merchant',
            "'receipt_sent'",
            'stamps.receipt_notification_resent',
            'merchant_notifications',
            'inbox_receipt',
        ] as $marker) {
            self::assertStringContainsString($marker, $endpoint);
        }

        foreach (['/api/stamps/receipt-notification.php', 'data-send-stamp-receipt', 'Receipt notification sent', 'Merchant Notifications'] as $marker) {
            self::assertStringContainsString($marker, $merchantJs);
        }

        foreach (['Stamp receipts', 'Stamp receipt links open the receipt page from notifications only', 'Merchant notification center'] as $marker) {
            self::assertStringContainsString($marker, $notificationsView);
        }

        self::assertStringNotContainsString('/inbox.php', $endpoint . $merchantJs . $notificationsView, 'Receipt notification flow must not link receipts through Inbox.');
    }
}
