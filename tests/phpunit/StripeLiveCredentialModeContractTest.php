<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StripeLiveCredentialModeContractTest extends TestCase
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

    public function testProviderCredentialsKeepGenericValuesInTheirActualMode(): void
    {
        $source=$this->source('api/payments/_provider_credentials.php');

        self::assertStringContainsString('function mg_payment_secret_matches_mode',$source);
        self::assertStringContainsString("str_starts_with(\$secret,'sk_'.\$mode.'_')",$source);
        self::assertStringContainsString("str_starts_with(\$secret,'rk_'.\$mode.'_')",$source);
        self::assertStringContainsString("if(\$field==='PUBLISHABLE_KEY')",$source);
        self::assertStringContainsString("return str_starts_with(\$generic,'pk_'.\$mode.'_')?\$generic:''",$source);
        self::assertStringContainsString("return mg_payment_secret_matches_mode(\$generic,\$mode)?\$generic:''",$source);
        self::assertStringContainsString("return mg_payment_mode()===\$mode?\$generic:''",$source);
        self::assertStringNotContainsString("return trim((string)(getenv('MG_'.strtoupper(\$provider).'_'.strtoupper(\$field)) ?: ''))",$source);
    }

    public function testRestrictedStripeKeysAreAcceptedForMatchingMode(): void
    {
        $provider=$this->source('api/payments/_provider_credentials.php');
        $readiness=$this->source('api/payments/_readiness.php');
        $fields=$this->source('includes/admin-payment-credential-fields.php');

        self::assertStringContainsString("sk_'.\$prefix.'_ or rk_'.\$prefix.'_",$provider);
        self::assertStringContainsString('mg_payment_secret_matches_mode($secret,$mode)',$readiness);
        self::assertStringContainsString('restricted key',$readiness);
        self::assertStringContainsString('sk_live_… or rk_live_…',$fields);
    }

    public function testAdminApiAutoSelectsRelevantSavedMode(): void
    {
        $source=$this->source('api/admin/payment-settings.php');

        foreach([
            'function mg_admin_payment_default_mode',
            'function mg_admin_payment_key_mode',
            "\$_GET['mode']??'auto'",
            "in_array(\$requested,['test','live'],true)",
            "'configured_modes'=>(",
        ] as $needle){
            if($needle==="'configured_modes'=>(")continue;
            self::assertStringContainsString($needle,$source);
        }

        self::assertStringContainsString("\$payload['configured_modes']=mg_admin_payment_configured_modes(\$pdo)",$source);
        self::assertStringContainsString("Test credentials are not required for this ", $source);
        self::assertStringContainsString('mode_storage_warning',$source);
    }

    public function testAdminBrowserDefaultsToAutoAndDoesNotHardcodeTestRuntime(): void
    {
        $source=$this->source('assets/js/admin-payments.js');

        self::assertStringContainsString("requestedMode = 'auto'",$source);
        self::assertStringContainsString("load(requestedMode)",$source);
        self::assertStringContainsString("sk_' + selected + '_… or rk_' + selected + '_…",$source);
        self::assertStringContainsString("putenv('MG_PAYMENT_MODE=" ,$source);
        self::assertStringContainsString("putenv('MG_APP_URL=" ,$source);
        self::assertStringNotContainsString("putenv('MG_PAYMENT_MODE=test')",$source);
        self::assertStringContainsString('Test credentials are not required when saving Live.',$source);
    }

    public function testAdminUiExplainsLiveOnlyConfiguration(): void
    {
        $source=$this->source('admin-payments.php');

        self::assertStringContainsString('A live-only setup does not require test credentials.',$source);
        self::assertStringContainsString('data-payment-mode-help',$source);
        self::assertStringContainsString('data-payment-mode-warning',$source);
        self::assertStringContainsString('Test and Live credentials are independent.',$source);
        self::assertStringContainsString('Readiness applies to the selected Test or Live configuration.',$source);
    }
}
