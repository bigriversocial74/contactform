<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SubscriptionStripeWebhookActivationContractTest extends TestCase
{
    public function testSubscriptionStripeEventEndpointVerifiesSignatureAndProcessesEvent(): void
    {
        $root = dirname(__DIR__, 2);
        $endpoint = file_get_contents($root . '/api/subscriptions/stripe-events.php');

        self::assertIsString($endpoint);
        self::assertStringContainsString("mg_require_method('POST')", $endpoint);
        self::assertStringContainsString('HTTP_STRIPE_SIGNATURE', $endpoint);
        self::assertStringContainsString('mg_payment_verify_signature', $endpoint);
        self::assertStringContainsString('mg_subscription_stripe_process_webhook_event', $endpoint);
    }

    public function testStripeWebhookProcessorIsIdempotentAndOnlyHandlesSubscriptionPackageChanges(): void
    {
        $root = dirname(__DIR__, 2);
        $processor = file_get_contents($root . '/api/subscriptions/_stripe_webhook.php');

        self::assertIsString($processor);
        self::assertStringContainsString('payment_webhook_events', $processor);
        self::assertStringContainsString('provider_event_id', $processor);
        self::assertStringContainsString("['checkout.session.completed', 'checkout.session.async_payment_succeeded']", $processor);
        self::assertStringContainsString('mg_subscription_webhook_activate_package_change', $processor);
        self::assertStringContainsString("'duplicate' => true", $processor);
    }

    public function testActivationHelperCompletesPackageRequestAndUpdatesSubscriptionMetadata(): void
    {
        $root = dirname(__DIR__, 2);
        $helper = file_get_contents($root . '/api/subscriptions/_stripe_activation.php');

        self::assertIsString($helper);
        self::assertStringContainsString('source_type', $helper);
        self::assertStringContainsString('subscription_package_change', $helper);
        self::assertStringContainsString('package_change_request_id', $helper);
        self::assertStringContainsString("status='completed'", $helper);
        self::assertStringContainsString('stripe_activation', $helper);
        self::assertStringContainsString('stripe_checkout_webhook', $helper);
        self::assertStringContainsString('package_change.activated', $helper);
    }
}
