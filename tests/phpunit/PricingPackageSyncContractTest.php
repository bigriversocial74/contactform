<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class PricingPackageSyncContractTest extends TestCase
{
    public function testSharedPricingPackageSourceExists(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/includes/pricing-packages.php');
        self::assertIsString($source);
        foreach([
            'function mg_pricing_packages(): array','function mg_public_pricing_packages(): array','function mg_pricing_package_summary(): array','paid Microgifts','promotional Rewards','monthly Stamps','max_microgifts','max_rewards','monthly_stamps_included','bulk_stamp_purchase_enabled','PKG-PRICING-GROWTH',
        ] as $needle){ self::assertStringContainsString($needle,$source); }
    }

    public function testStampDebitActionCatalogExists(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/includes/stamp-ledger-config.php');
        self::assertIsString($source);
        foreach(['function mg_stamp_debit_actions(): array','function mg_stamp_ledger_preview(string $scope =','function mg_stamp_debit_action_summary(): array','direct_microgift_send','direct_reward_send','campaign_feed_send','email_list_send','sms_send','\'stamp_value\' => 3','agentic_discovery_send'] as $needle){ self::assertStringContainsString($needle,$source); }
    }

    public function testStampLedgerDatabaseMigrationExists(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/database/stage_17_stamp_ledger.sql');
        self::assertIsString($source);
        foreach(['CREATE TABLE IF NOT EXISTS stamp_debit_actions','CREATE TABLE IF NOT EXISTS account_stamp_balances','CREATE TABLE IF NOT EXISTS stamp_ledger_entries','CREATE TABLE IF NOT EXISTS stamp_bundles','uq_stamp_ledger_account_idempotency','sms_send','admin.stamps.manage','stage_17_stamp_ledger'] as $needle){ self::assertStringContainsString($needle,$source); }
    }

    public function testStampServiceAndApiEndpointsExist(): void
    {
        $service = file_get_contents(dirname(__DIR__,2).'/api/stamps/_stamps.php');
        self::assertIsString($service);
        foreach(['function mg_stamp_balance','function mg_stamp_ledger_payload','function mg_stamp_post_entry','function mg_stamp_debit','function mg_stamp_credit','Insufficient Stamps','idempotency_key'] as $needle){ self::assertStringContainsString($needle,$service); }

        $actions = file_get_contents(dirname(__DIR__,2).'/api/stamps/actions.php');
        $ledger = file_get_contents(dirname(__DIR__,2).'/api/stamps/ledger.php');
        $debit = file_get_contents(dirname(__DIR__,2).'/api/stamps/debit.php');
        $credit = file_get_contents(dirname(__DIR__,2).'/api/stamps/credit.php');
        self::assertStringContainsString('mg_stamp_action_rows', (string)$actions);
        self::assertStringContainsString('mg_stamp_ledger_payload', (string)$ledger);
        self::assertStringContainsString('mg_stamp_debit', (string)$debit);
        self::assertStringContainsString('mg_stamp_credit', (string)$credit);
        self::assertStringContainsString('mg_require_csrf_for_write', (string)$debit);
        self::assertStringContainsString('reason_code is required', (string)$credit);
    }

    public function testPublicPricingPageReadsSharedPackageSource(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/pricing.php');
        self::assertIsString($source);
        foreach(["require_once __DIR__ . '/includes/pricing-packages.php" ,'$plans = mg_public_pricing_packages();','$summary = mg_pricing_package_summary();','data-package-id','Admin synced source','foreach ($plans as $plan)'] as $needle){ self::assertStringContainsString($needle,$source); }
        self::assertStringNotContainsString("['Starter','$29'",$source);
    }

    public function testAdminPackageModerationHasTabsActionsAndLedgers(): void
    {
        $source = file_get_contents(dirname(__DIR__,2).'/admin/package-moderation.php');
        self::assertIsString($source);
        foreach(["require_once dirname(__DIR__) . '/includes/stamp-ledger-config.php",'$stampActions = mg_stamp_debit_actions();','pkg-tab-actions','pkg-tab-admin-ledger','pkg-tab-merchant-ledger','Stamp value','stamp_value[','Admin ledger','Merchant ledger','/merchant-stamps.php'] as $needle){ self::assertStringContainsString($needle,$source); }
    }

    public function testMerchantStampLedgerPageExists(): void
    {
        $page = file_get_contents(dirname(__DIR__,2).'/merchant-stamps.php');
        $view = file_get_contents(dirname(__DIR__,2).'/includes/merchant-stamps-view.php');
        $workspace = file_get_contents(dirname(__DIR__,2).'/includes/merchant-workspace.php');
        self::assertIsString($page); self::assertIsString($view); self::assertIsString($workspace);
        self::assertStringContainsString('$merchantView=\'stamps\';',$page);
        self::assertStringContainsString('Merchant Stamp balance',$view);
        self::assertStringContainsString('mg_stamp_ledger_preview', $view);
        self::assertStringContainsString("'stamps'=>['Stamp Ledger'",$workspace);
    }
}