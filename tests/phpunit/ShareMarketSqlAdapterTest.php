<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/approval-sql-adapter.php';

final class ShareMarketSqlAdapterTest extends TestCase
{
    public function testEnrollmentPayloadExposesPublicIdAsParticipantId(): void
    {
        $payload = mg_share_market_sql_enrollment_payload([
            'id' => 7,
            'public_id' => '11111111-1111-1111-1111-111111111111',
            'participant_user_id' => 44,
            'participant_type' => 'merchant',
            'legal_name' => 'Example Cafe LLC',
            'public_name' => 'Example Cafe',
            'website' => 'https://example.test',
            'use_case' => 'A local Buy-In program.',
            'utility_plan' => 'Redeem for local utility.',
            'status' => 'under_review',
            'review_note' => null,
            'submitted_at' => '2026-06-26 01:00:00',
            'updated_at' => '2026-06-26 01:00:00',
            'created_at' => '2026-06-26 01:00:00',
            'metadata_json' => '{"last_event_type":"share_market.sql.enrollment_submitted"}',
        ]);

        self::assertSame('11111111-1111-1111-1111-111111111111', $payload['participant_id']);
        self::assertSame('under_review', $payload['status']);
        self::assertFalse($payload['execution_enabled']);
        self::assertSame('share_market.sql.enrollment_submitted', $payload['last_event_type']);
    }

    public function testSeriesPayloadIncludesRedemptionUtilityAndExecutionLock(): void
    {
        $payload = mg_share_market_sql_series_payload([
            'id' => 9,
            'public_id' => 'sm_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'participant_user_id' => 44,
            'name' => 'First 10,000',
            'description' => 'A submitted Buy-In series.',
            'state' => 'submitted',
            'supply' => 10000,
            'launch_price_cents' => 500,
            'currency' => 'USD',
            'max_per_buyer' => 25,
            'redemption_enabled' => 1,
            'resale_enabled' => 0,
            'reissue_milestone' => 'After sellout.',
            'review_note' => null,
            'updated_at' => '2026-06-26 01:00:00',
            'created_at' => '2026-06-26 01:00:00',
            'metadata_json' => '{"last_event_type":"share_market.sql.series_submitted"}',
        ], [
            'id' => 11,
            'public_id' => '22222222-2222-2222-2222-222222222222',
            'redemption_type' => 'microgift_card',
            'title' => 'Microgift card',
            'details' => 'Redeem into a Microgift card.',
            'share_cost' => 100,
            'status' => 'submitted',
        ]);

        self::assertSame('sm_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $payload['series_id']);
        self::assertSame('submitted', $payload['state']);
        self::assertSame('microgift_card', $payload['redemption']['type']);
        self::assertFalse($payload['execution_enabled']);
    }

    public function testApprovalRequestTypeMapsTargetsToSqlScope(): void
    {
        self::assertSame('platform_pool', mg_share_market_approval_sql_request_type(['target_type' => 'platform_pool']));
        self::assertSame('treasury', mg_share_market_approval_sql_request_type(['target_type' => 'merchant_treasury']));
        self::assertSame('series', mg_share_market_approval_sql_request_type(['target_type' => 'market_series']));
        self::assertSame('dave_score', mg_share_market_approval_sql_request_type(['target_type' => 'dave_score']));
        self::assertSame('participant', mg_share_market_approval_sql_request_type(['target_type' => 'unknown']));
    }

    public function testApprovalPayloadKeepsExecutionDisabled(): void
    {
        $row = [
            'id' => 4,
            'public_id' => '33333333-3333-3333-3333-333333333333',
            'requester_user_id' => 10,
            'status' => 'awaiting_first_approval',
            'required_approvals' => 2,
            'expires_at' => '2099-01-01 00:00:00',
            'manifest_json' => json_encode([
                'action' => 'mint_platform_shares',
                'event_type' => 'share_market.platform_shares_minted',
                'target_type' => 'platform_pool',
                'target_id' => 'platform-master',
                'payload_hash' => str_repeat('a', 64),
                'super_admin_required' => true,
            ], JSON_THROW_ON_ERROR),
            'projection_json' => json_encode(['type' => 'balance', 'current_balance' => 0, 'delta' => 100, 'projected_balance' => 100], JSON_THROW_ON_ERROR),
            'payload_hash' => str_repeat('b', 64),
            'created_at' => '2026-06-26 01:00:00',
        ];

        $payload = mg_share_market_approval_sql_request_payload($row, [], ['id' => 20, 'roles' => ['super_admin'], 'permissions' => []]);

        self::assertSame('33333333-3333-3333-3333-333333333333', $payload['request_id']);
        self::assertTrue($payload['permissions']['can_approve']);
        self::assertFalse($payload['permissions']['can_execute']);
        self::assertSame('share_market_sql', 'share_market_sql');
    }
}
