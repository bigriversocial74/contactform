<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StripeConnectOAuthOnboardingContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root=dirname(__DIR__,2);
    }

    private function source(string $path): string
    {
        $source=file_get_contents($this->root.'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testMerchantPaymentsExposesOfficialStripeConnectFlow(): void
    {
        $page=$this->source('merchant-payments.php');
        $view=$this->source('includes/merchant-payments-view.php');
        $js=$this->source('assets/js/merchant-stripe-connect.js');

        self::assertStringContainsString('merchant-stripe-connect.css',$page);
        self::assertStringContainsString('merchant-stripe-connect.js',$page);
        self::assertStringContainsString('Connect or create Stripe account',$view);
        self::assertStringContainsString('data-stripe-connect-start',$view);
        self::assertStringContainsString('data-stripe-connect-sync',$view);
        self::assertStringContainsString('data-stripe-connect-disconnect',$view);
        self::assertStringContainsString("postAction('oauth_start'",$js);
        self::assertStringContainsString("https://connect.stripe.com/oauth/authorize?",$js);
        self::assertStringContainsString('window.location.assign(url)',$js);
    }

    public function testOauthStateAndCallbackAreReplaySafeAndPermissionBound(): void
    {
        $connect=$this->source('api/payments/_connect.php');
        $callback=$this->source('api/merchant/stripe-connect-callback.php');

        self::assertStringContainsString('random_bytes(32)',$connect);
        self::assertStringContainsString("hash('sha256',\$state)",$connect);
        self::assertStringContainsString('time()+600',$connect);
        self::assertStringContainsString('consumed_at IS NULL',$connect);
        self::assertStringContainsString('mg_payment_connect_oauth_consume_state',$callback);
        self::assertStringContainsString("mg_require_permission('merchant.payments.manage')",$callback);
        self::assertStringNotContainsString('access_token',$callback);
        self::assertStringNotContainsString('refresh_token',$callback);
    }

    public function testOAuthUsesModeMatchedOfficialStripeEndpointsAndStandardSecret(): void
    {
        $connect=$this->source('api/payments/_connect.php');
        $transport=$this->source('api/payments/_stripe_connect_oauth.php');

        self::assertStringContainsString('https://connect.stripe.com/oauth/authorize?',$connect);
        self::assertStringContainsString("mg_payment_secret_key_type(\$secret)!=='secret'",$connect);
        self::assertStringContainsString("str_starts_with(trim((string)\$config['connect_client_id']),'ca_')",$connect);
        self::assertStringContainsString("https://connect.stripe.com/oauth/",$transport);
        self::assertStringContainsString("'grant_type'=>'authorization_code'",$transport);
        self::assertStringContainsString("'client_id'=>\$clientId",$transport);
        self::assertStringNotContainsString('access_token',$connect);
        self::assertStringNotContainsString('refresh_token',$connect);
    }

    public function testConnectionOwnershipReadinessAndLifecycleWebhooksAreEnforced(): void
    {
        $connect=$this->source('api/payments/_connect.php');
        $methods=$this->source('api/merchant/payment-methods.php');
        $webhook=$this->source('api/payments/_connect_webhook.php');
        $entry=$this->source('api/payments/webhook.php');

        self::assertStringContainsString('already connected to another Microgifter merchant account',$connect);
        self::assertStringContainsString("connection_method='standard_oauth'",$connect);
        self::assertStringContainsString('mg_payment_connect_update_readiness',$connect);
        self::assertStringContainsString('mg_payment_connect_status',$methods);
        self::assertStringContainsString("'connected' => !empty(\$stripeAccount['connected'])",$methods);
        self::assertStringContainsString("'ready' => !empty(\$stripeAccount['ready'])",$methods);
        self::assertStringContainsString("'account.updated','account.application.deauthorized'",$entry);
        self::assertStringContainsString("status='disabled'",$webhook);
        self::assertStringContainsString('payment_webhook_events',$webhook);
    }

    public function testMigrationAndDeploymentGuideCoverProductionRequirements(): void
    {
        $migration=$this->source('database/stage_v1g_stripe_connect_oauth.sql');
        $docs=$this->source('docs/payments/stripe-connect-oauth-onboarding.md');

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS payment_connect_oauth_states',$migration);
        self::assertStringContainsString('state_hash CHAR(64)',$migration);
        self::assertStringContainsString('connection_method',$migration);
        self::assertStringContainsString("'merchant.payments.manage'",$migration);
        self::assertStringContainsString("'stage_v1g_stripe_connect_oauth'",$migration);
        self::assertStringContainsString('/api/merchant/stripe-connect-callback.php',$docs);
        self::assertStringContainsString('standard `sk_test_...` or `sk_live_...`',$docs);
        self::assertStringContainsString('Staging QA',$docs);
    }

    public function testLegacyAccountLinkContractRemainsAvailable(): void
    {
        $connect=$this->source('api/payments/_connect.php');
        self::assertStringContainsString('function mg_payment_connect_start',$connect);
        self::assertStringContainsString('mg_stripe_create_connected_account',$connect);
        self::assertStringContainsString('mg_stripe_create_account_link',$connect);
    }
}
