<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SubscriptionCheckoutCompletionV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testAuthenticatedCheckoutReturnConfirmsCanonicalSubscription(): void
    {
        $source = file_get_contents($this->root . '/api/subscriptions/confirm-checkout.php');
        self::assertIsString($source);
        self::assertStringContainsString("mg_require_method('POST')", $source);
        self::assertStringContainsString('mg_require_csrf_for_write($input)', $source);
        self::assertStringContainsString("'/v1/checkout/sessions/'", $source);
        self::assertStringContainsString("'subscription.latest_invoice.payment_intent'", $source);
        self::assertStringContainsString('mg_subscription_package_webhook_complete(', $source);
        self::assertStringContainsString('provider_subscription_id=COALESCE', $source);
        self::assertStringContainsString('provider_customer_id=COALESCE', $source);
        self::assertStringContainsString('provider_latest_invoice_url=COALESCE', $source);
        self::assertStringContainsString("['paid', 'no_payment_required']", $source);
    }

    public function testCheckoutReturnHistoryReceiptIsIdempotent(): void
    {
        $source = file_get_contents($this->root . '/api/subscriptions/confirm-checkout.php');
        self::assertIsString($source);
        self::assertStringContainsString('event_type=? AND provider_key=? AND provider_event_id=?', $source);
        self::assertStringContainsString('platform_subscription.checkout_return_confirmed', $source);
        self::assertStringContainsString('checkout-return:', $source);
    }

    public function testSignedWebhookAddsCheckoutAndPaymentHistory(): void
    {
        $source = file_get_contents($this->root . '/api/subscriptions/_stripe_webhook.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_subscription_stripe_record_history', $source);
        self::assertStringContainsString('platform_subscription.checkout_completed', $source);
        self::assertStringContainsString('platform_subscription.payment_received', $source);
        self::assertStringContainsString('platform_subscription.payment_attention_required', $source);
        self::assertStringContainsString('invoice.payment_action_required', $source);
    }

    public function testAccountHistoryEndpointIsOwnerScoped(): void
    {
        $source = file_get_contents($this->root . '/api/subscriptions/history.php');
        self::assertIsString($source);
        self::assertStringContainsString('mg_require_api_user()', $source);
        self::assertStringContainsString('WHERE s.user_id=?', $source);
        self::assertStringContainsString('ORDER BY e.id DESC', $source);
        self::assertStringContainsString('LIMIT 24', $source);
        self::assertStringContainsString("'invoice_url'", $source);
        self::assertStringContainsString("'invoice_pdf'", $source);
    }

    public function testAccountPageLoadsCompletionControllerAndHistoryPresentation(): void
    {
        $page = file_get_contents($this->root . '/account-subscriptions.php');
        $controller = file_get_contents($this->root . '/assets/js/subscription-checkout-completion-v1.js');
        $css = file_get_contents($this->root . '/assets/css/subscription-checkout-completion-v1.css');
        self::assertIsString($page);
        self::assertIsString($controller);
        self::assertIsString($css);
        self::assertStringContainsString('/assets/css/subscription-checkout-completion-v1.css?v=1.0.0', $page);
        self::assertStringContainsString('/assets/js/subscription-checkout-completion-v1.js?v=1.0.0', $page);
        self::assertStringContainsString("MG.post('/api/subscriptions/confirm-checkout.php'", $controller);
        self::assertStringContainsString("MG.get('/api/subscriptions/history.php'", $controller);
        self::assertStringContainsString('data-subscription-history', $controller);
        self::assertStringContainsString('.mg-sub-history-row', $css);
        self::assertStringContainsString('.mg-sub-checkout-banner', $css);
    }
}
