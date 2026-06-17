<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PPPMActivityCompatibilityTest extends TestCase
{
    public function testActivityMigrationDefinesPppmDeliveryClaimAndRedemptionTables(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/stage_3_pppm_activity_layer.sql');

        self::assertIsString($sql);
        foreach ([
            'pppm_deliveries',
            'pppm_merchant_eligibility',
            'pppm_claims',
            'pppm_claim_attempts',
            'pppm_redemptions',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        self::assertStringNotContainsString('PRIMARY KEY (pppm_item_id, merchant_user_id, merchant_location_id)', $sql);
        self::assertStringContainsString('ADD COLUMN pppm_item_id BIGINT UNSIGNED NULL', $sql);
    }

    public function testMigrationRunnerRegistersPppmActivityLayer(): void
    {
        $runner = file_get_contents(dirname(__DIR__, 2) . '/scripts/run_migrations.php');

        self::assertIsString($runner);
        self::assertStringContainsString("'stage_3_pppm_activity_layer.sql'", $runner);
    }

    public function testInboxSentAndClaimedUsePppmWithLegacyFallback(): void
    {
        $list = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/list.php');
        $activity = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/_activity.php');

        self::assertIsString($list);
        self::assertIsString($activity);
        self::assertStringContainsString('mg_pppm_activity_box', $list);
        self::assertStringContainsString('pppm_legacy_gift_map', $list);
        self::assertStringContainsString("'source' => 'pppm_with_legacy_fallback'", $list);
        self::assertStringContainsString("p.recipient_user_id = ?", $activity);
        self::assertStringContainsString("p.status = 'redeemed'", $activity);
        self::assertStringContainsString('p.issuer_user_id = ? OR p.owner_user_id = ?', $activity);
    }

    public function testLoadedItemDetailUsesPppmEventsAndLegacyMapping(): void
    {
        $item = file_get_contents(dirname(__DIR__, 2) . '/api/gifts/item.php');

        self::assertIsString($item);
        self::assertStringContainsString('mg_pppm_activity_find', $item);
        self::assertStringContainsString('pppm_item_events', $item);
        self::assertStringContainsString('pppm_legacy_gift_map', $item);
        self::assertStringContainsString("'source' => 'pppm'", $item);
        self::assertStringContainsString("'source' => 'legacy'", $item);
    }

    public function testPppmMerchantVerificationRequiresEligibilityAndLocationCode(): void
    {
        $verify = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/verify-merchant-claim.php');

        self::assertIsString($verify);
        self::assertStringContainsString("mg_require_permission('pppm.redeem')", $verify);
        self::assertStringContainsString('pppm_merchant_eligibility', $verify);
        self::assertStringContainsString('merchant_claim_codes', $verify);
        self::assertStringContainsString('pppm_claim_attempts', $verify);
        self::assertStringContainsString('$attempts >= 5', $verify);
        self::assertStringContainsString("status = 'verified'", $verify);
        self::assertStringContainsString('mg_pppm_record_event', $verify);
    }

    public function testPppmRedemptionCreatesImmutableRedemptionAndNotification(): void
    {
        $redeem = file_get_contents(dirname(__DIR__, 2) . '/api/pppm/redeem-merchant-claim.php');

        self::assertIsString($redeem);
        self::assertStringContainsString('INSERT INTO pppm_redemptions', $redeem);
        self::assertStringContainsString('usage_count = usage_count + 1', $redeem);
        self::assertStringContainsString("status = 'redeemed'", $redeem);
        self::assertStringContainsString('pppm_item_id', $redeem);
        self::assertStringContainsString('PPPM item redeemed.', $redeem);
    }

    public function testUiRoutesMappedAndNativePppmItemsCorrectly(): void
    {
        $items = file_get_contents(dirname(__DIR__, 2) . '/assets/js/agent-items.js');
        $claim = file_get_contents(dirname(__DIR__, 2) . '/assets/js/merchant-claim.js');

        self::assertIsString($items);
        self::assertIsString($claim);
        self::assertStringContainsString("modal.dataset.itemSource = gift.pppm_id ? 'pppm' : 'legacy'", $items);
        self::assertStringContainsString("modal.dataset.itemSource === 'pppm'", $claim);
        self::assertStringContainsString('/api/pppm/verify-merchant-claim.php', $claim);
        self::assertStringContainsString('/api/pppm/redeem-merchant-claim.php', $claim);
    }

    public function testMessageThreadsSupportPppmItems(): void
    {
        $send = file_get_contents(dirname(__DIR__, 2) . '/api/messages/send.php');

        self::assertIsString($send);
        self::assertStringContainsString('item_source', $send);
        self::assertStringContainsString('pppm_item_id', $send);
        self::assertStringContainsString('mg_pppm_activity_find', $send);
        self::assertStringContainsString('INSERT INTO pppm_item_events', $send);
        self::assertStringContainsString('New item message', $send);
    }
}
