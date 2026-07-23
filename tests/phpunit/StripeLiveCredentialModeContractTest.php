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
            "\$payload['configured_modes']=mg_admin_payment_configured_modes(\$pdo)",
            'Test credentials are not required for a live-only setup.',
            'mode_storage_warning',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAdminApiVerifiesDatabasePersistenceBeforeReturningSuccess(): void
    {
        $source=$this->source('api/admin/payment-settings.php');

        foreach([
            'function mg_admin_payment_database_snapshot',
            'function mg_admin_payment_verify_persistence',
            'mg_admin_payment_verify_persistence($pdo,$input,$mode)',
            'Stripe settings failed database verification',
            "'persistence_verified'=>true",
            "\$payload['storage']=\$storage",
            "\$payload['environment_override']",
            'No unverified update was accepted.',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAdminBrowserDefaultsToAutoAndDoesNotHardcodeTestRuntime(): void
    {
        $source=$this->source('assets/js/admin-payments.js');

        self::assertStringContainsString("requestedMode = 'auto'",$source);
        self::assertStringContainsString('load(requestedMode)',$source);
        self::assertStringContainsString("sk_' + selected + '_… or rk_' + selected + '_…",$source);
        self::assertStringContainsString("putenv('MG_PAYMENT_MODE=",$source);
        self::assertStringContainsString("putenv('MG_APP_URL=",$source);
        self::assertStringNotContainsString("putenv('MG_PAYMENT_MODE=test')",$source);
        self::assertStringContainsString('Test credentials are not required when saving Live.',$source);
    }

    public function testPersistenceClientResolvesAuthoritativeModeBeforeWritingUrl(): void
    {
        $source=$this->source('assets/js/admin-payments-persistence.js');

        foreach([
            'localStorage.removeItem(legacyModeKey)',
            'function initializePersistence()',
            "payment-settings.php?mode=auto&verify=",
            'function responseMode(data)',
            'mode.value = resolved',
            'updateModeUrl(resolved)',
            'compareStorage',
            'verifyWhenSaveFinishes',
            'Save verification failed after reload',
            "Microgifter.get('/api/admin/payment-settings.php?mode='",
            'Secret fields remain blank after reload by design',
            'API key saved securely.',
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }

        $initialize=strpos($source,'async function initializePersistence()');
        $startup=strrpos($source,'initializePersistence();');
        self::assertNotFalse($initialize);
        self::assertNotFalse($startup);
        self::assertGreaterThan($initialize,$startup);
        self::assertStringNotContainsString("updateModeUrl();\n    window.setTimeout(function () { readBack(null);",$source);
    }

    public function testPersistenceWarningIsScopedToTheSelectedRecord(): void
    {
        $source=$this->source('assets/js/admin-payments-persistence.js');

        foreach([
            'function syncModeWarning()',
            '/stored in the (Test|Live) record/i',
            'warningMode !== selectedMode()',
            'MutationObserver(syncModeWarning)',
            "String(storage.mode || '') !== String(expected.mode || '')",
            "mismatches.push('configuration mode')",
        ] as $needle){
            self::assertStringContainsString($needle,$source);
        }
    }

    public function testAdminUiExplainsLiveOnlyConfigurationAndPersistence(): void
    {
        $source=$this->source('admin-payments.php');

        self::assertStringContainsString('A live-only setup does not require test credentials.',$source);
        self::assertStringContainsString('data-payment-mode-help',$source);
        self::assertStringContainsString('data-payment-mode-warning',$source);
        self::assertStringContainsString('data-payment-persistence-state',$source);
        self::assertStringContainsString('/assets/js/admin-payments-persistence.js',$source);
        self::assertStringContainsString('/assets/css/admin-payments-persistence.css',$source);
        self::assertStringContainsString('Test and Live credentials are independent.',$source);
        self::assertStringContainsString('Readiness applies to the selected Test or Live configuration.',$source);
    }
}
