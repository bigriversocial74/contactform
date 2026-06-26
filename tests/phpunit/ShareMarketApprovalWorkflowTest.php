<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/admin-actions.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/approval-workflow.php';

final class ShareMarketApprovalWorkflowTest extends TestCase
{
    private function manifest(string $action, int $actorId = 10): array
    {
        $user = [
            'id' => $actorId,
            'roles' => ['super_admin'],
            'permissions' => [],
        ];

        $input = [
            'action' => $action,
            'target_id' => 'platform-master',
            'reason_code' => 'supply_adjustment',
            'admin_note' => 'Reviewed Share Market administrative action.',
            'confirmation' => match ($action) {
                'mint_platform_shares' => 'MINT SHARES',
                'burn_platform_shares' => 'BURN SHARES',
                'pause_platform_pool' => 'PAUSE POOL',
                default => 'CREATE MASTER POOL',
            },
        ];

        if (in_array($action, ['create_master_pool', 'mint_platform_shares', 'burn_platform_shares'], true)) {
            $input['amount'] = 100;
        }
        if (in_array($action, ['mint_platform_shares', 'burn_platform_shares'], true)) {
            $input['current_state'] = 'active';
        }
        if ($action === 'pause_platform_pool') {
            $input['current_state'] = 'active';
            $input['reason_code'] = 'security_review';
        }

        return mg_share_market_admin_validate_preview($input, $user)['manifest'];
    }

    private function requestEvent(array $manifest, string $requestId = 'request-123'): array
    {
        return [
            'id' => 1,
            'event_type' => 'share_market.approval.requested',
            'user_id' => (int)$manifest['actor_user_id'],
            'created_at' => '2026-06-25 12:00:00',
            'payload' => [
                'request_id' => $requestId,
                'manifest' => $manifest,
                'projection' => ['type' => 'state_only', 'label' => 'No balance change', 'delta' => 0],
                'requester_user_id' => (int)$manifest['actor_user_id'],
                'required_approvals' => (int)$manifest['required_approvals'],
                'expires_at' => '2026-06-26T12:00:00+00:00',
                'note' => 'Approval request created.',
                'payload_hash' => str_repeat('a', 64),
            ],
        ];
    }

    public function testMintProjectionShowsProjectedSupply(): void
    {
        $projection = mg_share_market_approval_projection($this->manifest('mint_platform_shares'), 1000);

        self::assertSame('balance', $projection['type']);
        self::assertSame(1000, $projection['current_balance']);
        self::assertSame(100, $projection['delta']);
        self::assertSame(1100, $projection['projected_balance']);
    }

    public function testBurnCannotProduceNegativeBalance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        mg_share_market_approval_projection($this->manifest('burn_platform_shares'), 50);
    }

    public function testTwoDistinctApprovalsCompleteCriticalRequest(): void
    {
        $manifest = $this->manifest('mint_platform_shares', 10);
        $events = [
            $this->requestEvent($manifest),
            [
                'id' => 2,
                'event_type' => 'share_market.approval.approved',
                'user_id' => 20,
                'created_at' => '2026-06-25 12:10:00',
                'payload' => [
                    'request_id' => 'request-123',
                    'note' => 'First independent approval.',
                    'payload_hash' => str_repeat('b', 64),
                ],
            ],
            [
                'id' => 3,
                'event_type' => 'share_market.approval.approved',
                'user_id' => 30,
                'created_at' => '2026-06-25 12:20:00',
                'payload' => [
                    'request_id' => 'request-123',
                    'note' => 'Second independent approval.',
                    'payload_hash' => str_repeat('c', 64),
                ],
            ],
        ];

        $items = mg_share_market_approval_fold_events(
            $events,
            new DateTimeImmutable('2026-06-25T13:00:00+00:00')
        );

        self::assertCount(1, $items);
        self::assertSame('approved', $items[0]['status']);
        self::assertSame(2, $items[0]['approval_count']);
        self::assertSame([20, 30], array_column($items[0]['approvals'], 'actor_user_id'));
    }

    public function testOneApprovalLeavesCriticalRequestWaitingForSecond(): void
    {
        $manifest = $this->manifest('mint_platform_shares', 10);
        $events = [
            $this->requestEvent($manifest),
            [
                'id' => 2,
                'event_type' => 'share_market.approval.approved',
                'user_id' => 20,
                'created_at' => '2026-06-25 12:10:00',
                'payload' => [
                    'request_id' => 'request-123',
                    'note' => 'First independent approval.',
                    'payload_hash' => str_repeat('b', 64),
                ],
            ],
        ];

        $items = mg_share_market_approval_fold_events(
            $events,
            new DateTimeImmutable('2026-06-25T13:00:00+00:00')
        );

        self::assertSame('awaiting_second_approval', $items[0]['status']);
        self::assertSame(1, $items[0]['approval_count']);
    }

    public function testExpiredPendingRequestIsFoldedAsExpired(): void
    {
        $manifest = $this->manifest('pause_platform_pool', 10);
        $event = $this->requestEvent($manifest);
        $event['payload']['expires_at'] = '2026-06-25T12:30:00+00:00';

        $items = mg_share_market_approval_fold_events(
            [$event],
            new DateTimeImmutable('2026-06-25T13:00:00+00:00')
        );

        self::assertSame('expired', $items[0]['status']);
    }

    public function testRejectionClosesPendingRequest(): void
    {
        $manifest = $this->manifest('pause_platform_pool', 10);
        $events = [
            $this->requestEvent($manifest),
            [
                'id' => 2,
                'event_type' => 'share_market.approval.rejected',
                'user_id' => 20,
                'created_at' => '2026-06-25 12:15:00',
                'payload' => [
                    'request_id' => 'request-123',
                    'note' => 'Risk review did not pass.',
                    'payload_hash' => str_repeat('d', 64),
                ],
            ],
        ];

        $items = mg_share_market_approval_fold_events(
            $events,
            new DateTimeImmutable('2026-06-25T13:00:00+00:00')
        );

        self::assertSame('rejected', $items[0]['status']);
        self::assertSame(20, $items[0]['rejection']['actor_user_id']);
    }

    public function testApprovalQueueNeverEnablesExecution(): void
    {
        $manifest = $this->manifest('pause_platform_pool', 10);

        self::assertFalse($manifest['mutation_enabled']);
        self::assertSame('validated_not_executed', $manifest['execution_status']);
    }
}
