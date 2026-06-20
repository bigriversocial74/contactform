<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StageFStripeContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testMigrationAddsStripeCredentialsConnectAndFeeFields(): void
    {
        $sql=$this->source('database/stage_v1f_stripe_payments.sql');
        foreach([
            'payment_platform_credentials',
            'platform_fee_bps SMALLINT UNSIGNED NOT NULL DEFAULT 1500',
            'provider_checkout_url',
            'application_fee_cents',
            'destination_account_reference',
            'onboarding_status',
            'requirements_due_json',
        ] as $needle){
            self::assertStringContainsString($needle,$sql);
        }
        $manifest=require dirname(__DIR__,2).'/config/migrations.php';
        self::assertContains('stage_v1f_stripe_payments.sql',$manifest['ordered_files']);
    }

    public function testHostedCheckoutUsesDestinationChargeAndPlatformFee(): void
    {
        $stripe=$this->source('api/payments/_stripe.php');
        $session=$this->source('api/payments/_checkout_session.php');
        foreach([
            "'application_fee_amount'=>(int)\$order['platform_fee_cents']",
            "'transfer_data'=>['destination'=>(string)\$account['provider_account_reference']]",
            "'/v1/checkout/sessions'",
            "'provider_checkout_url'",
        ] as $needle){
            self::assertStringContainsString($needle,$stripe.$session);
        }
        self::assertStringContainsString('mg_payment_assert_checkout_ready($pdo,$order,$provider)',$session);
    }

    public function testStripeWebhookUsesRawSignatureAndIdempotentCapture(): void
    {
        $endpoint=$this->source('api/payments/webhook.php');
        $service=$this->source('api/payments/_webhook.php');
        self::assertStringContainsString("HTTP_STRIPE_SIGNATURE",$endpoint);
        self::assertStringContainsString('mg_payment_verify_signature($provider,$payload,$signature,$pdo)',$endpoint);
        self::assertStringContainsString('mg_payment_process_webhook_event(',$endpoint);
        foreach([
            "'checkout.session.completed'",
            "'checkout.session.async_payment_succeeded'",
            "'payment_intent.succeeded'",
            'Webhook event conflicts with an existing provider event.',
            'mg_payment_webhook_assert_amount(',
            'mg_finance_record_paid_order(',
        ] as $needle){
            self::assertStringContainsString($needle,$service);
        }
    }

    public function testPlatformFeeIsAnIncludedRevenueSplit(): void
    {
        $draft=$this->source('api/commerce/checkout-draft.php');
        $posting=$this->source('api/finance/_posting.php');
        self::assertStringContainsString('$platformFee=mg_payment_platform_fee_cents($pdo,$subtotal)',$draft);
        self::assertStringContainsString('$total=$subtotal',$draft);
        self::assertStringContainsString("'platform_fee_revenue'",$posting);
        self::assertStringContainsString('$merchantNet=$total-$fee',$posting);
        self::assertStringContainsString('Reverse platform fee',$posting);
    }

    public function testAdminAndMerchantHavePaymentReadinessInterfaces(): void
    {
        $admin=$this->source('admin-payments.php');
        $adminApi=$this->source('api/admin/payment-settings.php');
        $merchant=$this->source('includes/merchant-payments-view.php');
        $connect=$this->source('assets/js/merchant-connect.js');
        self::assertStringContainsString('Stripe settings &amp; readiness',$admin);
        self::assertStringContainsString("mg_require_permission('admin.settings.manage')",$adminApi);
        self::assertStringContainsString('Stripe Connect',$merchant);
        self::assertStringContainsString('/api/merchant/payment-connect.php',$connect);
        self::assertStringContainsString('/api/merchant/payment-account.php',$connect);
        self::assertStringContainsString('/admin-payments.php',$this->source('includes/admin-sidebar.php'));
    }

    public function testCredentialsAreNotReturnedAsSecrets(): void
    {
        $credentials=$this->source('api/payments/_provider_credentials.php');
        $adminApi=$this->source('api/admin/payment-settings.php');
        self::assertStringContainsString('sodium_crypto_secretbox',$credentials);
        self::assertStringContainsString('secret_hint',$credentials);
        self::assertStringContainsString('webhook_hint',$credentials);
        self::assertStringNotContainsString("['secret_key'=>",$adminApi);
        self::assertStringNotContainsString("['webhook_secret'=>",$adminApi);
    }
}
