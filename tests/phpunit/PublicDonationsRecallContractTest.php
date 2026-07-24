<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public-donations-recall.php';

final class PublicDonationsRecallContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function reward(array $changes = []): array
    {
        return array_replace([
            'status' => 'allocated',
            'original_community_user_id' => 20,
            'wallet_user_id' => 20,
            'wallet_status' => 'issued',
            'wallet_claimed_at' => null,
            'wallet_redeemed_at' => null,
            'wallet_expires_at' => null,
            'pppm_owner_user_id' => 20,
            'pppm_recipient_user_id' => 20,
            'pppm_status' => 'delivered',
            'pppm_expires_at' => null,
            'microgift_owner_user_id' => 20,
            'microgift_recipient_user_id' => 20,
            'microgift_status' => 'delivered',
            'microgift_claimed_at' => null,
            'microgift_redeemed_at' => null,
            'microgift_expires_at' => null,
        ], $changes);
    }

    public function testUntouchedOriginalOwnerIsRecallable(): void
    {
        self::assertSame('recallable', mg_public_donations_recall_classify($this->reward()));
        self::assertSame('recallable', mg_public_donations_recall_classify($this->reward([
            'wallet_status' => 'viewed',
            'pppm_status' => 'viewed',
        ])));
    }

    public function testDownstreamOwnerIsClassifiedAsRegifted(): void
    {
        self::assertSame('regifted', mg_public_donations_recall_classify($this->reward([
            'wallet_user_id' => 99,
        ])));
        self::assertSame('regifted', mg_public_donations_recall_classify($this->reward([
            'pppm_owner_user_id' => 99,
            'microgift_owner_user_id' => 99,
        ])));
    }

    public function testClaimedRedeemedExpiredCancelledAndRecalledAreExcluded(): void
    {
        self::assertSame('claimed', mg_public_donations_recall_classify($this->reward([
            'microgift_status' => 'redeemable',
            'microgift_claimed_at' => '2026-07-24 12:00:00',
        ])));
        self::assertSame('redeemed', mg_public_donations_recall_classify($this->reward([
            'wallet_status' => 'redeemed',
        ])));
        self::assertSame('expired', mg_public_donations_recall_classify($this->reward([
            'pppm_status' => 'expired',
        ])));
        self::assertSame('cancelled', mg_public_donations_recall_classify($this->reward([
            'microgift_status' => 'cancelled',
        ])));
        self::assertSame('already_recalled', mg_public_donations_recall_classify($this->reward([
            'status' => 'recalled',
        ])));
    }

    public function testPreviewCountsEveryClassification(): void
    {
        $batch = [
            'public_id' => '123e4567-e89b-42d3-a456-426614174001',
            'allocation_operation_public_id' => '123e4567-e89b-42d3-a456-426614174002',
            'quantity' => 4,
            'recalled_quantity' => 0,
            'status' => 'allocated',
            'created_at' => '2026-07-24 12:00:00',
            'campaign_public_id' => '123e4567-e89b-42d3-a456-426614174003',
            'public_slug' => 'community-impact',
            'campaign_title' => 'Community Impact',
            'template_public_id' => '123e4567-e89b-42d3-a456-426614174004',
            'template_title' => 'Community Meal',
            'value_amount_cents' => 2500,
            'currency' => 'USD',
            'assignment_public_id' => '123e4567-e89b-42d3-a456-426614174005',
            'community_account_id' => 'profile-account-1',
            'display_name' => 'Community One',
        ];
        $rows = [
            ['id' => 1] + $this->reward(),
            ['id' => 2] + $this->reward(['wallet_user_id' => 99]),
            ['id' => 3] + $this->reward(['microgift_status' => 'claimed']),
            ['id' => 4] + $this->reward(['status' => 'recalled']),
        ];
        $preview = mg_public_donations_recall_preview_from_rows($batch, $rows);
        self::assertSame(4, $preview['counts']['original']);
        self::assertSame(1, $preview['counts']['recallable']);
        self::assertSame(1, $preview['counts']['regifted']);
        self::assertSame(1, $preview['counts']['claimed']);
        self::assertSame(1, $preview['counts']['already_recalled']);
        self::assertSame([1], $preview['recallable_reward_ids']);
        self::assertTrue($preview['downstream_recipients_protected']);
    }

    public function testRecallInputValidation(): void
    {
        self::assertSame(2, mg_public_donations_recall_quantity('2'));
        self::assertSame('Inventory correction', mg_public_donations_recall_reason(' Inventory correction '));
        self::assertSame('public-donation-recall:test-0001', mg_public_donations_recall_idempotency_key('public-donation-recall:test-0001'));

        try {
            mg_public_donations_recall_quantity(0);
            self::fail('Expected quantity failure.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('between 1 and 1,000', $error->getMessage());
        }

        $this->expectException(RuntimeException::class);
        mg_public_donations_recall_reason('');
    }

    public function testSourceUsesCanonicalTerminalStatesAndNoPurchaseFlow(): void
    {
        $core = (string)file_get_contents($this->root . '/includes/public-donations-recall.php');
        $endpoint = (string)file_get_contents($this->root . '/api/merchant/public-donations-recall.php');
        self::assertStringContainsString('mg_microgift_apply_lifecycle', $core);
        self::assertStringContainsString('mg_action_center_project_lifecycle', $core);
        self::assertStringContainsString('mg_pppm_record_event', $core);
        self::assertStringContainsString("status='cancelled'", $core);
        self::assertStringContainsString("status='recalled'", $core);
        self::assertStringContainsString('GREATEST(issued_count-?,0)', $core);
        self::assertStringContainsString('beginTransaction()', $core);
        self::assertStringContainsString('FOR UPDATE', $core);
        self::assertStringContainsString('rollBack()', $core);
        self::assertStringContainsString("'downstream_recipients_affected' => false", $endpoint);
        self::assertDoesNotMatchRegularExpression('/\b(?:payment_intent|charge_customer|checkout_session)\b/i', $core . "\n" . $endpoint);
    }

    public function testClientUsesSafeDomConstruction(): void
    {
        $client = (string)file_get_contents($this->root . '/assets/js/public-donations-recall.js');
        self::assertStringNotContainsString('.innerHTML', $client);
        self::assertStringContainsString('textContent', $client);
        self::assertStringContainsString('replaceChildren', $client);
    }
}
