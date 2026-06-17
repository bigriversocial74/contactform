<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage5AMerchantWorkspaceTest extends TestCase
{
    public function testSchemaDefinesWorkspaceOnboardingLocationsTeamAndPaymentReadiness(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_5a_merchant_workspace.sql');
        self::assertIsString($sql);
        foreach (['merchant_workspaces','merchant_onboarding_steps','merchant_locations','merchant_team_members','merchant_payment_readiness'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
    }

    public function testWorkspaceInitializationCreatesOrderedActivationSteps(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/_merchant.php');
        self::assertIsString($source);
        foreach (['business_profile','eligibility','first_location','claim_configuration','first_product','storefront','payment_readiness','test_pppm','test_claim','analytics_verification','beta_readiness'] as $step) {
            self::assertStringContainsString("'{$step}'", $source);
        }
        self::assertStringContainsString('mg_merchant_recalculate_onboarding', $source);
    }

    public function testMerchantShellReusesAccountAppShellAndUniversalNavigation(): void
    {
        $shell = file_get_contents(dirname(__DIR__, 2) . '/includes/merchant-workspace.php');
        $page = file_get_contents(dirname(__DIR__, 2) . '/merchant.php');
        self::assertIsString($shell);
        self::assertIsString($page);
        self::assertStringContainsString('mg-app-shell', $shell);
        self::assertStringContainsString('mg-app-sidebar', $shell);
        self::assertStringContainsString('mg-app-workspace', $shell);
        self::assertStringContainsString("header_mode='account'", $page);
    }

    public function testPaymentReadinessDoesNotStoreSensitiveCardData(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_5a_merchant_workspace.sql');
        self::assertIsString($sql);
        self::assertStringContainsString('merchant_payment_readiness', $sql);
        self::assertStringNotContainsString('card_number', $sql);
        self::assertStringNotContainsString('payment_intent', $sql);
    }

    public function testStage5IPaymentWorkspaceReplacesReadinessPlaceholderSafely(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/includes/merchant-view.php');
        $payments = file_get_contents(dirname(__DIR__, 2) . '/includes/merchant-payments-view.php');
        self::assertIsString($view);
        self::assertIsString($payments);
        self::assertStringContainsString("merchantView==='payments'", $view);
        self::assertStringContainsString('merchant-payments-view.php', $view);
        self::assertStringContainsString('Payment', $payments);
        self::assertStringNotContainsString('card_number', $payments);
    }

    public function testTeamInvitesUseKeyedHashes(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/api/merchant/_merchant.php');
        self::assertIsString($source);
        self::assertStringContainsString("hash_hmac('sha256'", $source);
        self::assertStringContainsString('MG_MERCHANT_INVITE_SECRET', $source);
    }

    public function testReservedRoutesShareOneMerchantShell(): void
    {
        foreach (['merchant-products.php','merchant-storefront.php','merchant-pppm.php','merchant-distribution.php','merchant-claims.php','merchant-media.php','merchant-intelligence.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
            self::assertIsString($source);
            self::assertStringContainsString('includes/merchant-workspace.php', $source);
        }
    }
}
