<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-actions.php';
require_once __DIR__ . '/credit-reserve.php';

if (!function_exists('mg_share_market_credit_reserve_preflight')) {
    function mg_share_market_credit_reserve_preflight(PDO $pdo, array $reserve): array
    {
        $userId = (int)($reserve['requester_user_id'] ?? 0);
        $enrollment = $userId > 0 ? mg_share_market_sql_fetch_enrollment_by_user($pdo, $userId) : null;
        $treasury = $userId > 0 ? mg_share_market_fetch_treasury_by_user($pdo, $userId) : mg_share_market_default_treasury();
        $series = [];
        try {
            $stmt = $pdo->prepare("SELECT public_id,name,state,review_note,updated_at FROM share_market_series WHERE participant_user_id=? ORDER BY FIELD(state,'approved','submitted','changes_requested','draft','live','paused','rejected'),updated_at DESC,id DESC LIMIT 5");
            $stmt->execute([$userId]);
            $series = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $series = [];
        }
        $approvedSeries = array_values(array_filter($series, static fn(array $row): bool => in_array((string)($row['state'] ?? ''), ['approved','live'], true)));
        $adminNote = trim((string)($reserve['admin_note'] ?? ''));
        $checks = [
            ['key'=>'approved_merchant','label'=>'Approved merchant opt-in','passed'=>(bool)$enrollment && in_array((string)($enrollment['status'] ?? ''), ['approved','active'], true),'status'=>(string)($enrollment['status'] ?? 'missing')],
            ['key'=>'approved_reserve','label'=>'Approved credit reserve','passed'=>(string)($reserve['review_status'] ?? '') === 'approved' || (string)($reserve['status'] ?? '') === 'approved','status'=>(string)($reserve['review_status'] ?? $reserve['status'] ?? 'missing')],
            ['key'=>'approved_series','label'=>'Approved controlled series','passed'=>count($approvedSeries) > 0,'status'=>count($approvedSeries) > 0 ? 'approved' : 'missing'],
            ['key'=>'treasury_available','label'=>'Treasury has available credits','passed'=>mg_share_market_safe_count($treasury['credits_available'] ?? 0) > 0,'status'=>number_format(mg_share_market_safe_count($treasury['credits_available'] ?? 0)) . ' available'],
            ['key'=>'admin_note','label'=>'Admin note recorded','passed'=>$adminNote !== '','status'=>$adminNote !== '' ? 'recorded' : 'missing'],
            ['key'=>'execution_locked','label'=>'Execution remains locked','passed'=>empty($reserve['execution_enabled']),'status'=>empty($reserve['execution_enabled']) ? 'locked' : 'unlocked'],
        ];
        $passed = count(array_filter($checks, static fn(array $check): bool => !empty($check['passed'])));
        return [
            'checks' => $checks,
            'score' => (int)round(($passed / max(1, count($checks))) * 100),
            'complete' => $passed === count($checks),
            'blockers' => array_values(array_filter($checks, static fn(array $check): bool => empty($check['passed']))),
            'treasury' => $treasury,
            'enrollment' => $enrollment ? ['public_id'=>(string)$enrollment['public_id'],'status'=>(string)$enrollment['status']] : null,
            'series' => array_map(static fn(array $row): array => ['public_id'=>(string)$row['public_id'],'name'=>(string)$row['name'],'state'=>(string)$row['state'],'updated_at'=>(string)$row['updated_at']], $series),
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_credit_reserve_audit_history')) {
    function mg_share_market_credit_reserve_audit_history(PDO $pdo): array
    {
        $queue = mg_share_market_credit_reserve_admin_queue($pdo);
        $items = [];
        foreach (($queue['credit_reserve_requests'] ?? []) as $reserve) {
            $preflight = mg_share_market_credit_reserve_preflight($pdo, $reserve);
            $items[] = array_merge($reserve, [
                'preflight' => $preflight,
                'execution_status' => 'locked_not_executed',
                'status_label' => (string)($reserve['review_status'] ?? $reserve['status'] ?? 'pending'),
            ]);
        }
        $summary = [
            'total' => count($items),
            'approved' => count(array_filter($items, static fn(array $i): bool => (string)($i['review_status'] ?? '') === 'approved' || (string)($i['status'] ?? '') === 'approved')),
            'changes_requested' => count(array_filter($items, static fn(array $i): bool => (string)($i['review_status'] ?? '') === 'changes_requested')),
            'rejected' => count(array_filter($items, static fn(array $i): bool => (string)($i['status'] ?? '') === 'rejected')),
            'preflight_ready' => count(array_filter($items, static fn(array $i): bool => !empty($i['preflight']['complete']))),
            'execution_locked' => count(array_filter($items, static fn(array $i): bool => empty($i['execution_enabled']))),
        ];
        return ['items'=>$items,'summary'=>$summary,'execution_enabled'=>false];
    }
}
