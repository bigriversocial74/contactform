<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreatorCampaignAffiliateCommerceBridgeV1ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        require_once $this->root . '/includes/creator-campaigns/commerce-affiliate-foundation.php';
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testProrationIsExactAtIntegerLimits(): void
    {
        self::assertSame(3333, mg_creator_campaign_affiliate_prorated_minor(9999, 1, 3));
        self::assertSame(50, mg_creator_campaign_affiliate_prorated_minor(100, 50, 100));
        self::assertSame(PHP_INT_MAX - 1, mg_creator_campaign_affiliate_prorated_minor(PHP_INT_MAX, PHP_INT_MAX - 1, PHP_INT_MAX));
    }

    public function testCheckoutStoresOnlyPrivacySafeTrackingContext(): void
    {
        $bridge = $this->source('includes/creator-campaigns/commerce-affiliate-checkout.php');
        $checkout = $this->source('api/commerce/_checkout.php');
        self::assertStringContainsString("'session_hash' => \$sessionHash", $bridge);
        self::assertStringNotContainsString("'session_key'", $checkout);
        self::assertStringNotContainsString('mg_cc_session', $checkout);
        self::assertStringContainsString("'creator_affiliate'", $checkout);
    }

    public function testPaidOrderAutomaticallyCreatesAttributionEarningAndReservation(): void
    {
        $bridge = $this->source('includes/creator-campaigns/commerce-affiliate-checkout.php')
            . $this->source('includes/creator-campaigns/commerce-affiliate-earning.php')
            . $this->source('includes/creator-campaigns/commerce-affiliate-payment.php');
        $capture = $this->source('api/payments/_capture.php');
        self::assertStringContainsString('mg_creator_campaign_affiliate_record_paid_order', $capture);
        self::assertStringContainsString('mg_creator_campaign_attribution_decide', $bridge);
        self::assertStringContainsString('mg_creator_campaign_compensation_active_rule', $bridge);
        self::assertStringContainsString('mg_creator_campaign_affiliate_reserve_earning', $bridge);
        self::assertStringContainsString("'purchase.order.'", $bridge);
        self::assertStringContainsString("'affiliate:purchase:'", $bridge);
    }

    public function testAffiliateFailureCannotRollBackCanonicalPayment(): void
    {
        $bridge = $this->source('includes/creator-campaigns/commerce-affiliate-payment.php');
        self::assertStringContainsString('SAVEPOINT creator_affiliate_paid_order', $bridge);
        self::assertStringContainsString('ROLLBACK TO SAVEPOINT creator_affiliate_paid_order', $bridge);
        self::assertStringContainsString("'status' => 'failed'", $bridge);
        self::assertStringContainsString('creator_campaign.affiliate_purchase_failed', $bridge);
    }

    public function testRefundReconcilesEarningBudgetAndPayoutState(): void
    {
        $refundBridge = $this->source('includes/creator-campaigns/commerce-affiliate-refund.php')
            . $this->source('includes/creator-campaigns/commerce-affiliate-refund-reconciliation.php');
        $paymentRefund = $this->source('api/payments/_refund.php');
        self::assertStringContainsString('mg_creator_campaign_affiliate_record_refund', $paymentRefund);
        self::assertStringContainsString("'affiliate:refund:'", $refundBridge);
        self::assertStringContainsString("'affiliate:refund-budget:'", $refundBridge);
        self::assertStringContainsString('mg_creator_campaign_payout_append_event', $refundBridge);
        self::assertStringContainsString('mg_creator_campaign_affiliate_open_refund_dispute', $refundBridge);
    }

    public function testPayoutBridgeDoesNotBypassApprovalWithProviderTransfer(): void
    {
        $source = strtolower(
            $this->source('includes/creator-campaigns/commerce-affiliate-payment.php')
            . $this->source('includes/creator-campaigns/commerce-affiliate-refund.php')
            . $this->source('includes/creator-campaigns/commerce-affiliate-refund-reconciliation.php')
        );
        self::assertStringNotContainsString('/v1/transfers', $source);
        self::assertStringNotContainsString('stripe secret', $source);
        self::assertStringContainsString('creator_campaign_disputes', $source);
    }
}
