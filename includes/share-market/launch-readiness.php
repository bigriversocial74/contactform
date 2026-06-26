<?php
declare(strict_types=1);

require_once __DIR__ . '/credit-reserve-audit.php';

if (!function_exists('mg_share_market_latest_approved_reserve_for_user')) {
    function mg_share_market_latest_approved_reserve_for_user(PDO $pdo, int $userId): ?array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM share_market_approval_requests WHERE request_type='treasury' AND action_key='credit_reserve_request' AND requester_user_id=? ORDER BY FIELD(status,'approved','awaiting_first_approval','rejected'),updated_at DESC,id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? mg_share_market_credit_reserve_payload($row) : null;
        } catch (Throwable) { return null; }
    }
}

if (!function_exists('mg_share_market_series_redemption_ready')) {
    function mg_share_market_series_redemption_ready(?array $series): bool
    {
        if (!$series) return false;
        foreach (['redemption_type','redemption_title','redemption_details'] as $key) if (trim((string)($series[$key] ?? '')) === '') return false;
        return true;
    }
}

if (!function_exists('mg_share_market_launch_readiness_for_snapshot')) {
    function mg_share_market_launch_readiness_for_snapshot(PDO $pdo, int $userId, ?array $enrollment, ?array $latestSeries, array $treasury): array
    {
        $reserve = mg_share_market_latest_approved_reserve_for_user($pdo, $userId);
        $enrollmentStatus = (string)($enrollment['status'] ?? 'missing');
        $seriesState = (string)($latestSeries['state'] ?? 'missing');
        $seriesNote = trim((string)($latestSeries['admin_note'] ?? $latestSeries['review_note'] ?? ''));
        $reserveNote = trim((string)($reserve['admin_note'] ?? ''));
        $checks = [
            ['key'=>'approved_merchant','label'=>'Approved merchant','passed'=>(bool)$enrollment && in_array($enrollmentStatus, ['approved','active'], true),'status'=>$enrollmentStatus],
            ['key'=>'approved_credit_reserve','label'=>'Approved credit reserve','passed'=>(bool)$reserve && ((string)($reserve['review_status'] ?? '') === 'approved' || (string)($reserve['status'] ?? '') === 'approved'),'status'=>(string)($reserve['review_status'] ?? $reserve['status'] ?? 'missing')],
            ['key'=>'approved_series','label'=>'Approved controlled series','passed'=>(bool)$latestSeries && in_array($seriesState, ['approved','live'], true),'status'=>$seriesState],
            ['key'=>'treasury_available','label'=>'Treasury available credits','passed'=>mg_share_market_safe_count($treasury['credits_available'] ?? 0) > 0,'status'=>number_format(mg_share_market_safe_count($treasury['credits_available'] ?? 0)) . ' available'],
            ['key'=>'redemption_utility','label'=>'Redemption utility defined','passed'=>mg_share_market_series_redemption_ready($latestSeries),'status'=>mg_share_market_series_redemption_ready($latestSeries) ? 'defined' : 'missing'],
            ['key'=>'admin_notes','label'=>'Admin notes present','passed'=>$seriesNote !== '' || $reserveNote !== '','status'=>($seriesNote !== '' || $reserveNote !== '') ? 'recorded' : 'missing'],
            ['key'=>'execution_locked','label'=>'Execution locked','passed'=>true,'status'=>'locked'],
        ];
        $passed = count(array_filter($checks, static fn(array $check): bool => !empty($check['passed'])));
        $complete = $passed === count($checks);
        $next = 'Complete merchant review, reserve approval, and series approval before future execution review.';
        foreach ($checks as $check) { if (empty($check['passed'])) { $next = 'Resolve blocker: ' . $check['label'] . '.'; break; } }
        if ($complete) $next = 'Ready for future execution review. Actual launch remains locked.';
        return ['status'=>$complete ? 'ready_for_future_execution' : 'blocked','score'=>(int)round(($passed / max(1, count($checks))) * 100),'complete'=>$complete,'checks'=>$checks,'blockers'=>array_values(array_filter($checks, static fn(array $check): bool => empty($check['passed']))),'next_action'=>$next,'series'=>$latestSeries,'reserve'=>$reserve,'treasury'=>$treasury,'execution_enabled'=>false];
    }
}

if (!function_exists('mg_share_market_launch_readiness_for_user')) {
    function mg_share_market_launch_readiness_for_user(PDO $pdo, int $userId): array
    {
        $snapshot = mg_share_market_sql_user_snapshot($pdo, $userId);
        $enrollment = is_array($snapshot['enrollment'] ?? null) ? $snapshot['enrollment'] : null;
        $series = is_array($snapshot['series'] ?? null) ? $snapshot['series'] : [];
        return mg_share_market_launch_readiness_for_snapshot($pdo, $userId, $enrollment, mg_share_market_latest_series($series), mg_share_market_fetch_treasury_by_user($pdo, $userId));
    }
}

if (!function_exists('mg_share_market_launch_readiness_admin_queue')) {
    function mg_share_market_launch_readiness_admin_queue(PDO $pdo): array
    {
        $items = [];
        try {
            $stmt = $pdo->query("SELECT DISTINCT e.participant_user_id,u.email,u.display_name,u.full_name FROM share_market_enrollments e LEFT JOIN users u ON u.id=e.participant_user_id WHERE e.status IN ('approved','active') ORDER BY e.updated_at DESC,e.id DESC LIMIT 200");
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $row) {
                $userId = (int)$row['participant_user_id'];
                $ready = mg_share_market_launch_readiness_for_user($pdo, $userId);
                $series = is_array($ready['series'] ?? null) ? $ready['series'] : [];
                $items[] = ['participant_user_id'=>$userId,'merchant_label'=>trim((string)($row['display_name'] ?? $row['full_name'] ?? $row['email'] ?? 'Merchant')),'series_name'=>(string)($series['name'] ?? 'No series selected'),'series_public_id'=>(string)($series['public_id'] ?? $series['series_id'] ?? ''),'readiness'=>$ready,'execution_enabled'=>false];
            }
        } catch (Throwable) { $items = []; }
        return ['items'=>$items,'summary'=>['total'=>count($items),'ready'=>count(array_filter($items, static fn(array $i): bool => !empty($i['readiness']['complete']))),'blocked'=>count(array_filter($items, static fn(array $i): bool => empty($i['readiness']['complete']))),'execution_locked'=>count($items)],'execution_enabled'=>false];
    }
}

if (!function_exists('mg_share_market_launch_readiness_mark_ready')) {
    function mg_share_market_launch_readiness_mark_ready(PDO $pdo, array $input, array $user): array
    {
        if (!mg_share_market_admin_authorized($user)) throw new DomainException('Share Market Admin permission is required.');
        $participantUserId = filter_var($input['participant_user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($participantUserId === false || $participantUserId < 1) throw new InvalidArgumentException('Select a valid merchant.');
        $note = trim((string)($input['note'] ?? 'Ready for future execution review. Actual launch remains locked.'));
        if ($note === '' || mb_strlen($note) > 1000) throw new InvalidArgumentException('Admin note is required and cannot exceed 1,000 characters.');
        $ready = mg_share_market_launch_readiness_for_user($pdo, (int)$participantUserId);
        if (empty($ready['complete'])) throw new DomainException('Launch readiness is blocked. Resolve all blockers before marking ready.');
        $series = is_array($ready['series'] ?? null) ? $ready['series'] : [];
        $seriesId = (string)($series['public_id'] ?? $series['series_id'] ?? '');
        mg_share_market_sql_admin_event($pdo, (int)$user['id'], 'share_market.sql.series_ready_for_future_execution', 'series', $seriesId, (string)($series['state'] ?? 'approved'), 'ready_for_future_execution', $note);
        mg_share_market_notify_merchant($pdo, (int)$participantUserId, (int)$user['id'], 'DAVE series ready for future execution review', $note, 'series_ready_for_future_execution.' . $participantUserId . '.' . ($seriesId ?: 'series'), ['target_type'=>'series','target_public_id'=>$seriesId,'share_market_status'=>'ready_for_future_execution']);
        return mg_share_market_launch_readiness_admin_queue($pdo);
    }
}
