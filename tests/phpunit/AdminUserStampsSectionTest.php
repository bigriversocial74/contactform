<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminUserStampsSectionTest extends TestCase
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

    public function testUserCenterLoadsStampsDrawerModule(): void
    {
        $usersJs = $this->read('assets/js/admin-users.js');
        $stampsJs = $this->read('assets/js/admin-user-stamps.js');

        self::assertStringContainsString('/assets/js/admin-user-stamps.js', $usersJs);
        self::assertStringContainsString('data-admin-user-stamps-script', $usersJs);

        foreach ([
            'mg:admin-user-detail-loaded',
            'mg-admin-user-stamps-section',
            'Stamps',
            '/api/stamps/ledger.php?account_user_id=',
            '/api/stamps/adjustment.php',
            'Add Stamps',
            'data-admin-stamp-balance',
            'data-admin-stamp-ledger',
            'admin.stamps.view or admin.stamps.manage',
            'admin.stamps.manage',
            'Stamp ledger',
            'Package purchase support credit',
        ] as $marker) {
            self::assertStringContainsString($marker, $stampsJs);
        }
    }

    public function testExistingStampEndpointsSupportAdminLedgerAndAdjustment(): void
    {
        $ledger = $this->read('api/stamps/ledger.php');
        $adjustment = $this->read('api/stamps/adjustment.php');
        $library = $this->read('api/stamps/_stamps.php');

        foreach ([
            'admin.stamps.view',
            'admin.stamps.manage',
            'account_user_id',
            'mg_stamp_ledger_payload',
        ] as $marker) {
            self::assertStringContainsString($marker, $ledger);
        }

        foreach ([
            'admin.stamps.manage',
            'account_user_id',
            'delta',
            'reason_code',
            'admin_adjustment',
            'mg_stamp_post_entry',
            'mg_audit',
        ] as $marker) {
            self::assertStringContainsString($marker, $adjustment);
        }

        foreach ([
            'account_stamp_balances',
            'stamp_ledger_entries',
            'purchased_stamps',
            'used_stamps',
            'voided_stamps',
            'idempotency_key',
        ] as $marker) {
            self::assertStringContainsString($marker, $library);
        }
    }
}
