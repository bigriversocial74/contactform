<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class V1ReleaseHardeningContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($source,$path);
        return $source;
    }

    public function testActiveRootRunsBrowserValidationForPullRequestsAndMain(): void
    {
        $workflow=$this->source('.github/workflows/browser-validation.yml');
        foreach([
            'name: Browser Validation',
            'pull_request:',
            'push:',
            'branches: [main]',
            'npm run test:browser:v1',
            'tests/browser/**',
            'php-version: \'8.3\'',
        ] as $needle){
            self::assertStringContainsString($needle,$workflow);
        }
        $package=json_decode($this->source('package.json'),true,512,JSON_THROW_ON_ERROR);
        self::assertSame('playwright test tests/browser/v1-release-golden-path.spec.js',$package['scripts']['test:browser:v1']??null);
        $smoke=$this->source('tests/browser/v1-release-golden-path.spec.js');
        self::assertStringContainsString('V1 release browser golden path',$smoke);
        self::assertStringContainsString('/api/payments/order-checkout-session.php',$smoke);
        self::assertStringContainsString('https://checkout.stripe.test/c/pay/release-smoke',$smoke);
        self::assertStringContainsString('[data-cart-page] [data-cart-summary]',$smoke);
    }

    public function testLaunchReadinessUsesCanonicalManifestAndStripeSellingMerchantReadiness(): void
    {
        $readiness=$this->source('api/payments/_readiness.php');
        $launch=$this->source('scripts/validate_launch_readiness.php');
        $admin=$this->source('api/admin/operations-readiness.php');
        self::assertStringContainsString('function mg_payment_selling_merchant_readiness',$readiness);
        self::assertStringContainsString("cp.status='published'",$readiness);
        self::assertStringContainsString("ppa.charges_enabled=1",$readiness);
        self::assertStringContainsString("ppa.payouts_enabled=1",$readiness);
        self::assertStringContainsString("'launch_ready'",$readiness);
        self::assertStringContainsString('mg_migration_status($pdo)',$launch);
        foreach(["'stripe_platform'","'stripe_selling_merchants'",'payment_platform_credentials','payment_provider_accounts'] as $needle){
            self::assertStringContainsString($needle,$launch);
            self::assertStringContainsString($needle,$admin);
        }
    }

    public function testCriticalAndHighGoldenPathFindingsGateTheRecoveryBaseline(): void
    {
        $composer=json_decode($this->source('composer.json'),true,512,JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('audit-product-pppm-golden-path-gate',$composer['scripts']);
        $baseline=$this->source('scripts/recovery_baseline.sh');
        self::assertStringContainsString('composer audit-product-pppm-golden-path-gate',$baseline);
        $gate=$this->source('scripts/validate_product_pppm_golden_path_gate.php');
        self::assertStringContainsString("in_array(\$severity,['critical','high'],true)",$gate);
        self::assertStringContainsString("exit(\$blocking===[]?0:1)",$gate);
        self::assertStringContainsString("'purchased_gift_claim_bridge'",$gate);
        self::assertStringContainsString("'direct_merchant_claim'",$gate);
        self::assertStringContainsString("'post_claim_message_recipient'",$gate);
        self::assertStringContainsString("'original_issuer_preservation'",$gate);
    }

    public function testCanonicalPppmTransferEnforcesOwnerTerminalStateAndDeliveryLifecycle(): void
    {
        $ownership=$this->source('api/pppm/_ownership.php');
        foreach([
            'Only the current PPPM owner can transfer this item.',
            "['redeemed','expired','cancelled','refunded','voided']",
            'PPPM item cannot be transferred from its current state.',
            "status=?,sent_at=COALESCE(sent_at,NOW()),delivered_at=COALESCE(delivered_at,NOW())",
            "\$sourceType==='microgift_claim'",
            "\$metadata['microgift_instance_id']",
        ] as $needle){
            self::assertStringContainsString($needle,$ownership);
        }
        self::assertStringNotContainsString('duplicate_entitlement_transfer',$ownership);
    }

    public function testCanonicalLocationPolicySupportsPublishedSelectedLocationsAndFailsClosed(): void
    {
        $lifecycle=$this->source('api/microgifts/_lifecycle.php');
        self::assertStringContainsString("\$policy['location_ids']",$lifecycle);
        self::assertStringContainsString("['allow_list','selected_locations']",$lifecycle);
        self::assertStringContainsString("['exclude_list','all_except']",$lifecycle);
        self::assertStringContainsString('return false;',$lifecycle);
    }

    public function testRealStripeTestBoundaryIsAvailableWithoutTheStub(): void
    {
        $workflow=$this->source('.github/workflows/stripe-test-integration.yml');
        $validator=$this->source('scripts/validate_stripe_test_provider.php');
        self::assertStringContainsString('workflow_dispatch:',$workflow);
        self::assertStringContainsString("MG_STRIPE_TEST_STUB: '0'",$workflow);
        self::assertStringContainsString('STRIPE_TEST_CONNECTED_ACCOUNT_ID',$workflow);
        self::assertStringContainsString("mg_stripe_api_request(\$pdo,'GET','/v1/account')",$validator);
        self::assertStringContainsString("'/v1/checkout/sessions'",$validator);
        self::assertStringContainsString("'/expire'",$validator);
    }

    public function testRepositorySourceOfTruthIsTheActiveRoot(): void
    {
        $map=$this->source('docs/architecture/current_active_file_map.md');
        self::assertStringContainsString('bigriversocial74/contactform',$map);
        self::assertStringContainsString('repository root is the only active runtime source',$map);
        self::assertStringContainsString('V1 Release Hardening',$map);
        $archive=$this->source('microgifter-main/ARCHIVED_COPY_DO_NOT_USE.md');
        self::assertStringContainsString('not an active runtime source',$archive);
    }
}
