<?php
declare(strict_types=1);

require_once __DIR__ . '/sql-adapter.php';

if (!function_exists('mg_share_market_safe_count')) {
    function mg_share_market_safe_count(mixed $value): int
    {
        return max(0, (int)($value ?? 0));
    }
}

if (!function_exists('mg_share_market_default_treasury')) {
    function mg_share_market_default_treasury(): array
    {
        return [
            'status' => 'not_created',
            'credits_allocated' => 0,
            'credits_available' => 0,
            'credits_assigned' => 0,
            'credits_circulating' => 0,
            'credits_redeemed' => 0,
            'credits_burned' => 0,
            'credits_frozen' => 0,
            'updated_at' => '',
        ];
    }
}

if (!function_exists('mg_share_market_fetch_treasury_by_user')) {
    function mg_share_market_fetch_treasury_by_user(PDO $pdo, int $userId): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) return mg_share_market_default_treasury();
        try {
            $stmt = $pdo->prepare(
                'SELECT t.* FROM share_market_credit_treasuries t
                 INNER JOIN share_market_enrollments e ON e.id=t.enrollment_id
                 WHERE e.participant_user_id=?
                 ORDER BY t.updated_at DESC,t.id DESC LIMIT 1'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return mg_share_market_default_treasury();
            return [
                'public_id' => (string)$row['public_id'],
                'status' => (string)$row['status'],
                'credits_allocated' => mg_share_market_safe_count($row['credits_allocated'] ?? 0),
                'credits_available' => mg_share_market_safe_count($row['credits_available'] ?? 0),
                'credits_assigned' => mg_share_market_safe_count($row['credits_assigned'] ?? 0),
                'credits_circulating' => mg_share_market_safe_count($row['credits_circulating'] ?? 0),
                'credits_redeemed' => mg_share_market_safe_count($row['credits_redeemed'] ?? 0),
                'credits_burned' => mg_share_market_safe_count($row['credits_burned'] ?? 0),
                'credits_frozen' => mg_share_market_safe_count($row['credits_frozen'] ?? 0),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        } catch (Throwable) {
            return mg_share_market_default_treasury();
        }
    }
}

if (!function_exists('mg_share_market_latest_series')) {
    function mg_share_market_latest_series(array $series): ?array
    {
        if (!$series) return null;
        return is_array($series[0] ?? null) ? $series[0] : null;
    }
}

if (!function_exists('mg_share_market_workflow_from_snapshot')) {
    function mg_share_market_workflow_from_snapshot(?array $enrollment, ?array $latestSeries, array $treasury): array
    {
        $enrollmentStatus = (string)($enrollment['status'] ?? 'not_enrolled');
        $seriesState = (string)($latestSeries['state'] ?? 'none');
        $treasuryFunded = mg_share_market_safe_count($treasury['credits_allocated'] ?? 0) > 0 || mg_share_market_safe_count($treasury['credits_available'] ?? 0) > 0;

        $current = 'not_enrolled';
        if ($enrollmentStatus === 'under_review') $current = 'review_requested';
        if (in_array($enrollmentStatus, ['approved','active'], true)) $current = 'approved';
        if ($treasuryFunded) $current = 'credits_purchased';
        if ($seriesState === 'draft') $current = 'series_draft';
        if ($seriesState === 'submitted') $current = 'series_submitted';
        if ($seriesState === 'changes_requested') $current = 'changes_requested';
        if ($seriesState === 'approved') $current = 'approved_to_launch';
        if ($seriesState === 'live') $current = 'live';
        if (in_array($enrollmentStatus, ['paused','suspended'], true) || in_array($seriesState, ['paused','frozen'], true)) $current = 'paused';
        if ($enrollmentStatus === 'rejected' || $seriesState === 'rejected') $current = 'rejected';
        if ($enrollmentStatus === 'closed' || $seriesState === 'closed') $current = 'closed';

        return [
            'current' => $current,
            'enrollment_status' => $enrollmentStatus,
            'series_state' => $seriesState,
            'review_status' => $enrollment ? ($seriesState === 'submitted' ? 'Series submitted' : ucfirst(str_replace('_', ' ', $enrollmentStatus))) : 'Not submitted',
            'treasury_funded' => $treasuryFunded,
            'can_request_review' => !$enrollment || in_array($enrollmentStatus, ['not_enrolled','interested','rejected','closed'], true),
            'can_buy_credits' => in_array($enrollmentStatus, ['approved','active'], true),
            'can_draft_series' => (bool)$enrollment && !in_array($enrollmentStatus, ['rejected','closed','suspended'], true),
            'can_submit_series' => (bool)$latestSeries && in_array($seriesState, ['draft','changes_requested'], true),
            'admin_gate_required' => !in_array($seriesState, ['live'], true),
        ];
    }
}

if (!function_exists('mg_share_market_merchant_state')) {
    function mg_share_market_merchant_state(PDO $pdo, int $userId): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) {
            $treasury = mg_share_market_default_treasury();
            return [
                'enrollment' => null,
                'series' => [],
                'latest_series' => null,
                'treasury' => $treasury,
                'workflow' => mg_share_market_workflow_from_snapshot(null, null, $treasury),
                'execution_enabled' => false,
                'storage_mode' => 'share_market_sql_unavailable',
            ];
        }

        $snapshot = mg_share_market_sql_user_snapshot($pdo, $userId);
        $enrollment = is_array($snapshot['enrollment'] ?? null) ? $snapshot['enrollment'] : null;
        $series = is_array($snapshot['series'] ?? null) ? $snapshot['series'] : [];
        $latestSeries = mg_share_market_latest_series($series);
        $treasury = mg_share_market_fetch_treasury_by_user($pdo, $userId);

        $snapshot['latest_series'] = $latestSeries;
        $snapshot['treasury'] = $treasury;
        $snapshot['workflow'] = mg_share_market_workflow_from_snapshot($enrollment, $latestSeries, $treasury);
        return $snapshot;
    }
}
