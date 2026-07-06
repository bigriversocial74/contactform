<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MerchantSponsoredRegiftStampsTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root() . '/' . $path);
        self::assertIsString($source, $path);
        return $source;
    }

    public function testCustomerRegiftsUseMerchantSponsoredStampDebit(): void
    {
        $source = $this->read('api/account/action-center-send.php');

        foreach ([
            'mg_action_center_merchant_sponsored_regift_stamp',
            'merchant_sponsored_wallet_regift',
            'merchant_sponsored_microgift_regift',
            'regift_send',
            'sponsor_user_id',
            'actor_user_id',
            'customer_actor_user_id',
            'stamp_sponsor_user_id',
            'stamp_debit_status',
            'merchant_sponsored_regift',
            'Customer regift sponsored by original merchant Stamp balance.',
        ] as $marker) {
            self::assertStringContainsString($marker, $source);
        }

        self::assertStringNotContainsString("mg_stamp_debit_send(\$pdo,(int)\$user['id'],(int)\$user['id'],'regift_send'", $source);
        self::assertStringNotContainsString("mg_stamp_debit_send($pdo,(int)$user['id'],(int)$user['id'],'regift_send'", $source);
    }

    public function testMerchantShortfallDoesNotBlockCustomerRegift(): void
    {
        $source = $this->read('api/account/action-center-send.php');

        foreach ([
            'merchant_sponsor_shortfall',
            'Merchant Stamp balance was insufficient; customer regift was allowed',
            'stamps.merchant_sponsored_regift_shortfall',
            'free_no_merchant_sponsor',
            'free_customer_self_sponsored',
            'Customer-side sends are free unless a merchant sponsor is attached.',
            'mg_stamp_balance($pdo, $sponsorUserId, true)',
            'available < $required',
        ] as $marker) {
            self::assertStringContainsString($marker, $source);
        }
    }

    public function testRegiftTrackingCarriesStampSponsorMetadata(): void
    {
        $source = $this->read('api/account/action-center-send.php');

        foreach ([
            'wallet_item.regifted',
            'microgift.regifted',
            'action_center.wallet_item_regifted',
            'action_center.microgift_regifted',
            'stamp_ledger_entry_id',
            'stamp_sponsor_user_id',
            'stamp_shortfall',
            'stamp_ledger' => 'stamp_ledger',
        ] as $marker) {
            self::assertStringContainsString((string)$marker, $source);
        }
    }
}
