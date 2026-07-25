<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/merchant-crm-reporting.php';

final class MerchantCrmReportingMakeGoodV1Test extends TestCase
{
    public function testReportingWindowsAreStrictlyBounded(): void
    {
        self::assertSame(7, mg_merchant_crm_reporting_days(7));
        self::assertSame(30, mg_merchant_crm_reporting_days(30));
        self::assertSame(90, mg_merchant_crm_reporting_days(90));
        self::assertSame(30, mg_merchant_crm_reporting_days(14));
        self::assertSame(30, mg_merchant_crm_reporting_days('invalid'));
    }

    public function testTrendCalculationIsExplainable(): void
    {
        self::assertSame(['current'=>12,'previous'=>8,'change'=>4,'percent'=>50,'direction'=>'up'], mg_merchant_crm_reporting_trend(12, 8));
        self::assertSame('down', mg_merchant_crm_reporting_trend(3, 6)['direction']);
        self::assertSame('flat', mg_merchant_crm_reporting_trend(0, 0)['direction']);
    }

    public function testPipelineUsesCanonicalLifecycleEvidence(): void
    {
        $window = time() - 86400;
        self::assertSame('converted', mg_merchant_crm_reporting_pipeline_bucket(['total_purchase_cents'=>100], 20, $window));
        self::assertSame('converted', mg_merchant_crm_reporting_pipeline_bucket(['total_rewards_redeemed'=>1], 20, $window));
        self::assertSame('ready', mg_merchant_crm_reporting_pipeline_bucket([], 80, $window));
        self::assertSame('nurturing', mg_merchant_crm_reporting_pipeline_bucket(['total_rewards_issued'=>1], 40, $window));
        self::assertSame('engaged', mg_merchant_crm_reporting_pipeline_bucket(['last_engaged_at'=>date('c')], 20, $window));
        self::assertSame('new', mg_merchant_crm_reporting_pipeline_bucket([], 10, $window));
    }

    public function testFollowupStatusNeverReopensCompletedWork(): void
    {
        self::assertSame('completed', mg_merchant_crm_reporting_followup_status(['status'=>'completed']));
        self::assertSame('completed', mg_merchant_crm_reporting_followup_status(['completed_at'=>date('c')]));
        self::assertSame('snoozed', mg_merchant_crm_reporting_followup_status(['snoozed_until'=>date('c', strtotime('+2 days'))]));
        self::assertSame('overdue', mg_merchant_crm_reporting_followup_status(['due_at'=>date('c', strtotime('-2 days'))]));
    }
}
