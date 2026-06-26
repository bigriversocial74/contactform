<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/share-market/admin-actions.php';
require_once dirname(__DIR__, 2) . '/includes/share-market/program-workflow.php';

final class ShareMarketProgramWorkflowTest extends TestCase
{
    private function user(int $id = 44): array
    {
        return ['id' => $id, 'roles' => ['customer'], 'permissions' => []];
    }

    public function testEnrollmentRequiresOptionalConfirmations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('optional participation');

        mg_share_market_validate_enrollment_request([
            'participant_type' => 'artist',
            'legal_name' => 'Example Artist LLC',
            'public_name' => 'Example Artist',
            'use_case' => 'Fans can support the first limited access series.',
            'utility_plan' => 'Shares can redeem for tickets, merch, and VIP access.',
        ], $this->user());
    }

    public function testValidEnrollmentCreatesUnderReviewPayload(): void
    {
        $payload = mg_share_market_validate_enrollment_request([
            'participant_type' => 'merchant',
            'legal_name' => 'Example Cafe LLC',
            'public_name' => 'Example Cafe',
            'website' => 'https://example.test',
            'use_case' => 'Customers can buy into a small utility-backed local reward series.',
            'utility_plan' => 'Holders can redeem for Microgift cards and local in-store experiences.',
            'accept_optional' => '1',
            'accept_review' => '1',
        ], $this->user(55));

        self::assertSame('participant_user_55', $payload['participant_id']);
        self::assertSame('under_review', $payload['requested_state']);
        self::assertFalse($payload['terms']['execution_enabled']);
    }

    public function testSeriesDraftValidatesSupplyPriceAndRedemption(): void
    {
        $series = mg_share_market_validate_series_draft([
            'name' => 'First 10,000',
            'description' => 'A limited utility series for early supporters with tracked redemption utility.',
            'supply' => '10000',
            'launch_price' => '5.00',
            'max_per_buyer' => '25',
            'redemption_enabled' => '1',
            'resale_enabled' => '1',
            'redemption_type' => 'event_ticket',
            'redemption_title' => 'Launch event ticket',
            'redemption_details' => 'Redeem shares for a ticket to the approved local launch event.',
            'redemption_share_cost' => '100',
            'reissue_milestone' => 'Only after sellout and admin review.',
        ], $this->user(77), false);

        self::assertStringStartsWith('sm_', $series['series_id']);
        self::assertSame('draft', $series['state']);
        self::assertSame(500, $series['launch_price_cents']);
        self::assertTrue($series['redemption_enabled']);
        self::assertFalse($series['execution_enabled']);
    }

    public function testSubmittedSeriesStopsBeforeLiveState(): void
    {
        $series = mg_share_market_validate_series_draft([
            'name' => 'First 10,000',
            'description' => 'A limited utility series for early supporters with tracked redemption utility.',
            'supply' => '10000',
            'launch_price' => '5.00',
            'max_per_buyer' => '25',
            'redemption_type' => 'microgift_card',
            'redemption_title' => 'Microgift redemption card',
            'redemption_details' => 'Redeem shares into a tracked Microgift card after admin review.',
            'redemption_share_cost' => '100',
        ], $this->user(77), true);

        self::assertSame('submitted', $series['state']);
        self::assertNotSame('live', $series['state']);
        self::assertFalse($series['execution_enabled']);
    }

    public function testProgramEventsFoldEnrollmentAndSeriesReviewStates(): void
    {
        $events = [
            [
                'id' => 1,
                'event_type' => 'share_market.program.enrollment_submitted',
                'user_id' => 12,
                'created_at' => '2026-06-25 10:00:00',
                'payload' => [
                    'participant_id' => 'participant_user_12',
                    'participant_user_id' => 12,
                    'participant_type' => 'artist',
                    'legal_name' => 'Artist LLC',
                    'public_name' => 'Artist',
                    'utility_plan' => 'Utility backed access.',
                ],
            ],
            [
                'id' => 2,
                'event_type' => 'share_market.program.enrollment_approved',
                'user_id' => 99,
                'created_at' => '2026-06-25 11:00:00',
                'payload' => ['participant_id' => 'participant_user_12', 'participant_user_id' => 12, 'note' => 'Approved for drafting.'],
            ],
            [
                'id' => 3,
                'event_type' => 'share_market.program.series_submitted',
                'user_id' => 12,
                'created_at' => '2026-06-25 12:00:00',
                'payload' => [
                    'series_id' => 'sm_12345678901234567890123456789012',
                    'participant_user_id' => 12,
                    'name' => 'First 10,000',
                    'state' => 'submitted',
                    'supply' => 10000,
                    'launch_price_cents' => 500,
                ],
            ],
            [
                'id' => 4,
                'event_type' => 'share_market.program.series_changes_requested',
                'user_id' => 99,
                'created_at' => '2026-06-25 13:00:00',
                'payload' => ['series_id' => 'sm_12345678901234567890123456789012', 'participant_user_id' => 12, 'note' => 'Add redemption details.'],
            ],
        ];

        $folded = mg_share_market_program_fold($events);
        self::assertSame('approved', $folded['enrollments'][0]['status']);
        self::assertSame('changes_requested', $folded['series'][0]['state']);
        self::assertSame('Add redemption details.', $folded['series'][0]['admin_note']);
    }

    public function testAdminReviewSnapshotOnlyReturnsReviewItems(): void
    {
        $events = [
            [
                'id' => 1,
                'event_type' => 'share_market.program.enrollment_submitted',
                'user_id' => 12,
                'created_at' => '2026-06-25 10:00:00',
                'payload' => ['participant_id' => 'participant_user_12', 'participant_user_id' => 12, 'participant_type' => 'artist', 'public_name' => 'Artist'],
            ],
            [
                'id' => 2,
                'event_type' => 'share_market.program.series_draft_saved',
                'user_id' => 12,
                'created_at' => '2026-06-25 10:30:00',
                'payload' => ['series_id' => 'sm_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'participant_user_id' => 12, 'name' => 'Draft', 'state' => 'draft'],
            ],
            [
                'id' => 3,
                'event_type' => 'share_market.program.series_submitted',
                'user_id' => 12,
                'created_at' => '2026-06-25 11:00:00',
                'payload' => ['series_id' => 'sm_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'participant_user_id' => 12, 'name' => 'Submitted', 'state' => 'submitted'],
            ],
        ];

        $folded = mg_share_market_program_fold($events);
        $reviewEnrollments = array_values(array_filter($folded['enrollments'], static fn(array $e): bool => ($e['status'] ?? '') === 'under_review'));
        $reviewSeries = array_values(array_filter($folded['series'], static fn(array $s): bool => ($s['state'] ?? '') === 'submitted'));

        self::assertCount(1, $reviewEnrollments);
        self::assertCount(1, $reviewSeries);
        self::assertSame('Submitted', $reviewSeries[0]['name']);
    }
}
