<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class CreatorCampaignCompensationV6ContractTest extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=dirname(__DIR__,2);}
    public function testSchemaIsImmutableAndIdempotent():void{
        $sql=file_get_contents($this->root.'/database/20260722_creator_campaign_compensation_earnings_v6_single_install.sql');
        self::assertStringContainsString('creator_campaign_compensation_rule_versions',$sql);
        self::assertStringContainsString('uq_cc_comp_version_hash',$sql);
        self::assertStringContainsString('uq_cc_earning_idempotency',$sql);
        self::assertStringContainsString('uq_cc_earning_reversal',$sql);
        self::assertStringNotContainsString('creator_campaign_budget_ledger',$sql);
        self::assertStringNotContainsString('creator_campaign_payouts',$sql);
    }
    public function testServicesOwnTransactionsAndReversals():void{
        $service=file_get_contents($this->root.'/includes/creator-campaigns/compensation-service.php');
        self::assertStringContainsString('mg_creator_campaign_assert_transaction_boundary',$service);
        self::assertStringContainsString('reversal_of_event_id',$service);
        self::assertStringContainsString('idempotent',$service);
    }
    public function testRoutesAndServicesAreScoped():void{
        $merchantApi=file_get_contents($this->root.'/api/merchant/creator-campaign-compensation.php');
        $service=file_get_contents($this->root.'/includes/creator-campaigns/compensation-service.php');
        $creatorQuery=file_get_contents($this->root.'/includes/creator-campaigns/compensation-query.php');
        self::assertStringContainsString('mg_require_csrf_for_write',$merchantApi);
        self::assertStringContainsString('merchant.creator_compensation.manage',$service);
        self::assertStringContainsString('creator.campaign_earnings.view_own',$creatorQuery);
    }
}
