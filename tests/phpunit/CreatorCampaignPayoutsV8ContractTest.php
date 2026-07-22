<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class CreatorCampaignPayoutsV8ContractTest extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=dirname(__DIR__,2);}
    public function testSchemaPreventsDuplicateClaimsAndDisputes():void{$sql=file_get_contents($this->root.'/database/20260722_creator_campaign_payouts_disputes_v8_single_install.sql');self::assertStringContainsString('uq_cc_payout_item_active_reservation',$sql);self::assertStringContainsString('uq_cc_dispute_active_source',$sql);self::assertStringContainsString('uq_cc_payout_event_idempotency',$sql);self::assertStringNotContainsString('creator_campaign_bank_accounts',$sql);}
    public function testServicesOwnTransactionsAndTransitions():void{$service=file_get_contents($this->root.'/includes/creator-campaigns/payout-service.php');$defs=file_get_contents($this->root.'/includes/creator-campaigns/payout-definitions.php');self::assertStringContainsString('mg_creator_campaign_assert_transaction_boundary',$service);self::assertStringContainsString('mg_creator_campaign_payout_assert_transition',$service);self::assertStringContainsString("'paid'=>['reversed']",$defs);self::assertStringContainsString('creator.campaign_disputes.manage_own',$service);}
    public function testRoutesAndLayoutsAreScoped():void{$merchant=file_get_contents($this->root.'/api/merchant/creator-campaign-payouts.php');$creator=file_get_contents($this->root.'/api/creator/campaign-payouts.php');$creatorPage=file_get_contents($this->root.'/creator-campaign-payouts.php');self::assertStringContainsString('mg_require_csrf_for_write',$merchant);self::assertStringContainsString('mg_require_csrf_for_write',$creator);self::assertStringContainsString('account-sidebar.php',$creatorPage);}
}
