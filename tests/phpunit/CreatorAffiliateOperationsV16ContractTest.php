<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/creator-campaigns/operations-service.php';

final class CreatorAffiliateOperationsV16ContractTest extends TestCase
{
    private function source(string $path): string
    {
        $value=file_get_contents(dirname(__DIR__,2).'/'.$path);
        self::assertIsString($value,$path.' must be readable.');
        return $value;
    }

    public function testMigrationDefinesPolicyAndPersistentReconciliation(): void
    {
        $sql=$this->source('database/20260730_creator_affiliate_operations_experience_v16.sql');
        self::assertStringContainsString('creator_campaign_payout_policies',$sql);
        self::assertStringContainsString('creator_campaign_reconciliation_cases',$sql);
        self::assertStringContainsString('uq_cc_payout_policy_workspace_currency',$sql);
        self::assertStringContainsString('uq_cc_reconciliation_fingerprint',$sql);
        self::assertStringContainsString('CHECK (manual_approval_required = 1)',$sql);
        self::assertStringContainsString('20260730_creator_affiliate_operations_experience_v16',$sql);
    }

    public function testPayoutPolicyCannotBypassManualApproval(): void
    {
        $service=$this->source('includes/creator-campaigns/operations-service.php');
        $payout=$this->source('includes/creator-campaigns/payout-service.php');
        self::assertStringContainsString("'manual_approval_required'=>1",$service);
        self::assertStringContainsString('manual_approval_required=1',$service);
        self::assertStringContainsString("\$total,'draft'",$payout);
        self::assertStringContainsString('provider_reference is required before marking a payout paid',$payout);
        self::assertStringNotContainsString('stripe.transfers',$service);
        self::assertStringNotContainsString('Transfer::create',$service);
    }

    public function testPolicyEnforcesHoldAndEffectiveMinimum(): void
    {
        $source=$this->source('includes/creator-campaigns/payout-service.php');
        self::assertStringContainsString('r.committed_at<=?',$source);
        self::assertStringContainsString("max((int)\$profile['minimum_payout_minor'],\$policyMinimum)",$source);
        self::assertStringContainsString('The merchant payout policy is paused.',$source);
        self::assertStringContainsString('completed the payout hold period',$source);
    }

    public function testPayoutScheduleDatesAreStable(): void
    {
        $now=new DateTimeImmutable('2026-07-30 15:00:00',new DateTimeZone('UTC'));
        self::assertSame('2026-07-31',mg_creator_campaign_operations_next_payout_date(['status'=>'active','cadence'=>'weekly','payout_weekday'=>5],$now));
        self::assertSame('2026-07-31',mg_creator_campaign_operations_next_payout_date(['status'=>'active','cadence'=>'biweekly','payout_weekday'=>5,'created_at'=>'2026-07-01 00:00:00'],$now));
        self::assertSame('2026-08-15',mg_creator_campaign_operations_next_payout_date(['status'=>'active','cadence'=>'monthly','payout_day_of_month'=>15],$now));
        self::assertNull(mg_creator_campaign_operations_next_payout_date(['status'=>'active','cadence'=>'manual'],$now));
    }

    public function testReconciliationCoversAffiliateMoneyLifecycle(): void
    {
        $source=$this->source('includes/creator-campaigns/operations-service.php');
        foreach([
            'paid_order_incomplete',
            'attribution_missing_earning',
            'earning_missing_reservation',
            'refund_missing_adjustment',
            'payout_needs_attention',
            'active_dispute',
            'suspect_tracking_activity',
            'reconciliation_scan_error',
        ] as $marker)self::assertStringContainsString($marker,$source);
        self::assertStringContainsString('scan_token',$source);
        self::assertStringContainsString("status='resolved',resolved_at=NOW()",$source);
    }

    public function testMerchantOperationsExperienceIsGuided(): void
    {
        $page=$this->source('merchant-creator-affiliate-operations.php');
        $view=$this->source('includes/merchant-creator-affiliate-operations-view.php');
        $script=$this->source('assets/js/merchant-creator-affiliate-operations.js');
        self::assertStringContainsString('merchant-creator-affiliate-operations-view.php',$page);
        self::assertStringContainsString('data-caops-policy-form',$view);
        self::assertStringContainsString('Campaign readiness',$view);
        self::assertStringContainsString('data-profile-participant',$script);
        self::assertStringContainsString('data-payout-participant',$script);
        self::assertStringContainsString('data-case-action',$script);
        self::assertStringContainsString('/merchant-creator-affiliate-operations.php',$this->source('includes/merchant-navigation.php'));
    }

    public function testCreatorExperienceShowsPolicyAndFinanceLifecycle(): void
    {
        $earnings=$this->source('includes/creator-campaigns/compensation-query.php');
        $payouts=$this->source('includes/creator-campaigns/payout-query.php');
        $policyViews=$this->source('includes/creator-campaigns/operations-policy-query.php');
        self::assertStringContainsString('reservation_status',$earnings);
        self::assertStringContainsString('lifecycle_status',$earnings);
        self::assertStringContainsString('provider_reference',$earnings);
        self::assertStringContainsString('operations_creator_policy_views',$earnings);
        self::assertStringContainsString('operations_creator_policy_views',$payouts);
        self::assertStringContainsString('merchant_workspaces',$policyViews);
        self::assertStringContainsString("'merchant_name'",$policyViews);
        self::assertStringContainsString('merchant_name',$this->source('assets/js/creator-campaign-earnings.js'));
        self::assertStringContainsString('merchant_name',$this->source('assets/js/creator-campaign-payouts.js'));
        self::assertStringContainsString('status_guide',$payouts);
        self::assertStringContainsString('data-cce-policy',$this->source('includes/creator-campaign-earnings-view.php'));
        self::assertStringContainsString('data-ccpayout-policy',$this->source('includes/creator-campaign-payouts-view.php'));
    }

    public function testDocumentationPreservesProviderNeutralBoundary(): void
    {
        $docs=$this->source('docs/creator-campaigns/CREATOR_AFFILIATE_OPERATIONS_EXPERIENCE_V16.md');
        self::assertStringContainsString('manual merchant approval: required',$docs);
        self::assertStringContainsString('call Stripe transfers',$docs);
        self::assertStringContainsString('file or calculate tax forms',$docs);
        self::assertStringContainsString('Refund and clawback behavior',$docs);
        self::assertStringContainsString('Production smoke test',$docs);
    }
}
