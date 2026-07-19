<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantBundleCommissionAuthorityV1ContractTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function file(string $path): string
    {
        $content = file_get_contents($this->root . '/' . ltrim($path, '/'));
        self::assertIsString($content, 'Unable to read ' . $path);
        return $content;
    }

    public function testMigrationCreatesCanonicalCommissionTablesAndPermissions(): void
    {
        $sql = $this->file('database/20260719_merchant_bundle_commission_authority_v1.sql');
        foreach (['commission_platform_settings','merchant_commission_profiles','merchant_commission_history','bundle_commission_profiles','bundle_commission_participant_terms','checkout_draft_commission_snapshots','commerce_order_commission_snapshots','commerce_order_item_commission_snapshots'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString("'admin.payments.commissions.manage'", $sql);
        self::assertStringContainsString("'merchant.payments.commissions.view'", $sql);
        self::assertStringContainsString("'20260719_merchant_bundle_commission_authority_v1'", $sql);
    }

    public function testServiceSupportsPlatformMerchantAndBundleResolution(): void
    {
        $service = $this->file('api/payments/_commissions.php');
        foreach (['mg_commission_platform_starting_bps','mg_commission_initialize_merchant_profile','mg_commission_save_merchant_profile','mg_commission_resolve_merchant_rate','mg_commission_save_bundle_profile','mg_commission_save_bundle_participant_terms','mg_commission_resolve_bundle_rate','mg_commission_quote_order','mg_commission_quote_component'] as $function) {
            self::assertStringContainsString('function ' . $function, $service);
        }
        foreach (['fixed_merchant_rate','follow_platform_default','promotional_rate','contract_rate','merchant_default','bundle_starting_rate','custom_participant_rates','accepted_bundle_participant_terms'] as $mode) {
            self::assertStringContainsString($mode, $service);
        }
        self::assertStringContainsString("\$context['include_fixed_fee'] = false", $service);
        self::assertStringContainsString('intdiv(($amountCents * $bps) + 5000,10000)', $service);
    }

    public function testCheckoutUsesResolvedRateAndImmutableSnapshots(): void
    {
        $draft = $this->file('api/commerce/_cart_checkout.php');
        $order = $this->file('api/commerce/_checkout.php');
        self::assertStringContainsString('mg_commission_quote_order', $draft);
        self::assertStringContainsString('mg_commission_snapshot_checkout_draft', $draft);
        self::assertStringContainsString('mg_commission_promote_draft_to_order', $order);
        self::assertStringContainsString('commission_rate_bps', $order);
        self::assertStringContainsString('merchant_net_amount_cents', $order);
        self::assertStringContainsString('Multi-merchant checkout must use the controlled bundle-commerce flow.', $draft);
    }

    public function testMerchantActivationInitializesExplicitProfile(): void
    {
        $merchantApi = $this->file('api/merchant/commission-profile.php');
        $checkout = $this->file('api/commerce/_cart_checkout.php');
        self::assertStringContainsString('mg_commission_public_profile($pdo, $userId, true)', $merchantApi);
        self::assertStringContainsString("'actor_user_id' => \$buyerUserId", $checkout);
    }

    public function testAdminAndMerchantInterfacesArePermissionProtected(): void
    {
        $adminApi = $this->file('api/admin/merchant-commissions.php');
        $merchantApi = $this->file('api/merchant/commission-profile.php');
        $adminPage = $this->file('admin-commissions.php');
        self::assertStringContainsString("mg_require_permission('admin.payments.commissions.manage')", $adminApi);
        self::assertStringContainsString('CONFIRM COMMISSION CHANGE', $adminApi);
        self::assertStringContainsString("mg_require_permission('merchant.payments.commissions.view')", $merchantApi);
        self::assertStringContainsString('merchant_editable', $merchantApi);
        self::assertStringContainsString('/admin-payments.php', $adminPage);
    }

    public function testRefundsRemainBoundToFrozenOrderCommission(): void
    {
        $posting = $this->file('api/finance/_posting.php');
        self::assertStringContainsString("\$order['platform_fee_cents']", $posting);
        self::assertStringContainsString('platform_fee_reversal_cents', $posting);
        self::assertStringNotContainsString('mg_commission_resolve_merchant_rate', $posting);
    }

    public function testMigrationManifestIncludesCommissionAuthority(): void
    {
        $manifest = $this->file('config/migrations.php');
        self::assertStringContainsString("'20260719_merchant_bundle_commission_authority_v1.sql'", $manifest);
    }
}
