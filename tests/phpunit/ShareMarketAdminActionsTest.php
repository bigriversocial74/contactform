<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/admin-actions.php';

final class ShareMarketAdminActionsTest extends TestCase
{
    private function superAdmin(): array
    {
        return [
            'id' => 7,
            'roles' => ['super_admin'],
            'permissions' => [],
        ];
    }

    private function shareMarketAdmin(): array
    {
        return [
            'id' => 8,
            'roles' => ['admin'],
            'permissions' => ['share_market.admin'],
        ];
    }

    public function testGenericAdminDoesNotInheritShareMarketAdminAccess(): void
    {
        self::assertFalse(mg_share_market_admin_authorized([
            'id' => 9,
            'roles' => ['admin'],
            'permissions' => ['admin.audit.view'],
        ]));
        self::assertTrue(mg_share_market_admin_authorized($this->shareMarketAdmin()));
        self::assertTrue(mg_share_market_admin_authorized($this->superAdmin()));
    }

    public function testValidMintProducesLockedDryRunManifest(): void
    {
        $result = mg_share_market_admin_validate_preview([
            'action' => 'mint_platform_shares',
            'target_id' => 'platform-master',
            'amount' => 10000,
            'current_state' => 'active',
            'reason_code' => 'supply_adjustment',
            'admin_note' => 'Prepare a reviewed 10,000-share issuance window.',
            'confirmation' => 'MINT SHARES',
        ], $this->superAdmin());

        self::assertSame('platform_pool_minted', $result['manifest']['event_type']);
        self::assertSame(10000, $result['manifest']['amount']);
        self::assertSame('dry_run', $result['manifest']['mode']);
        self::assertFalse($result['manifest']['mutation_enabled']);
        self::assertSame('validated_not_executed', $result['manifest']['execution_status']);
        self::assertSame(64, strlen($result['manifest']['payload_hash']));
        self::assertTrue($result['guardrails']['append_only_required']);
        self::assertFalse($result['guardrails']['database_mutation_performed']);
        self::assertTrue($result['guardrails']['second_approval_required']);
    }

    public function testTypedConfirmationMustMatchExactly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('typed confirmation');

        mg_share_market_admin_validate_preview([
            'action' => 'pause_platform_pool',
            'target_id' => 'platform-master',
            'current_state' => 'active',
            'reason_code' => 'security_review',
            'admin_note' => 'Pause while investigating a risk signal.',
            'confirmation' => 'pause pool',
        ], $this->shareMarketAdmin());
    }

    public function testInvalidStateTransitionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('current state');

        mg_share_market_admin_validate_preview([
            'action' => 'approve_series',
            'target_id' => 'series-123',
            'current_state' => 'live',
            'reason_code' => 'merchant_request',
            'admin_note' => 'Attempted approval from the wrong state.',
            'confirmation' => 'APPROVE SERIES',
        ], $this->shareMarketAdmin());
    }

    public function testCriticalActionRequiresSuperAdmin(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('super administrator');

        mg_share_market_admin_validate_preview([
            'action' => 'burn_platform_shares',
            'target_id' => 'platform-master',
            'amount' => 50,
            'current_state' => 'paused',
            'reason_code' => 'correction',
            'admin_note' => 'Retire invalid unallocated supply.',
            'confirmation' => 'BURN SHARES',
        ], $this->shareMarketAdmin());
    }

    public function testApprovedSeriesDoesNotAutomaticallyGoLive(): void
    {
        $result = mg_share_market_admin_validate_preview([
            'action' => 'approve_series',
            'target_id' => 'series-123',
            'current_state' => 'submitted',
            'reason_code' => 'merchant_request',
            'admin_note' => 'Series passed identity, utility, and supply review.',
            'confirmation' => 'APPROVE SERIES',
        ], $this->shareMarketAdmin());

        self::assertSame('approved', $result['manifest']['next_state']);
        self::assertNotSame('live', $result['manifest']['next_state']);
        self::assertFalse($result['manifest']['mutation_enabled']);
    }
}
