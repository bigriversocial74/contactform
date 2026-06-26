<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-checklist.php';
require_once __DIR__ . '/credit-reserve.php';

if (!function_exists('mg_share_market_readiness_summary_stage')) {
    function mg_share_market_readiness_summary_stage(string $key, string $label, string $status, bool $complete, string $detail = ''): array
    {
        return ['key'=>$key,'label'=>$label,'status'=>$status,'complete'=>$complete,'detail'=>$detail];
    }
}

if (!function_exists('mg_share_market_readiness_summary_next_action')) {
    function mg_share_market_readiness_summary_next_action(array $workflow, ?array $reserve, array $launch, array $lockbox, array $operator): array
    {
        if (!empty($workflow['can_request_review'])) return ['label'=>'Request review','href'=>'#review','type'=>'merchant'];
        if (($workflow['enrollment_status'] ?? '') === 'under_review') return ['label'=>'Wait for admin review','href'=>'#review','type'=>'locked'];
        if (in_array((string)($workflow['enrollment_status'] ?? ''), ['rejected','closed','suspended'], true)) return ['label'=>'Review admin feedback','href'=>'#feedback','type'=>'attention'];
        if (!$reserve || !in_array((string)($reserve['review_status'] ?? $reserve['status'] ?? ''), ['approved'], true)) return ['label'=>'Reserve share credits','href'=>'#treasury','type'=>'merchant'];
        if (($workflow['series_state'] ?? 'none') === 'none') return ['label'=>'Create series','href'=>'#series','type'=>'merchant'];
        if (!empty($workflow['can_submit_series'])) return ['label'=>'Submit series','href'=>'#series','type'=>'merchant'];
        if (($workflow['series_state'] ?? '') === 'submitted') return ['label'=>'Wait for admin review','href'=>'#review','type'=>'locked'];
        if (($workflow['series_state'] ?? '') === 'changes_requested') return ['label'=>'Update and resubmit series','href'=>'#feedback','type'=>'attention'];
        if (empty($launch['complete']) || ($lockbox['status'] ?? '') !== 'lockbox_ready') return ['label'=>'Wait for admin readiness review','href'=>'#review','type'=>'locked'];
        if (empty($operator['complete'])) return ['label'=>'Operator review in progress','href'=>'#review','type'=>'locked'];
        return ['label'=>'Ready for future execution review — launch still locked','href'=>'#review','type'=>'ready'];
    }
}

if (!function_exists('mg_share_market_readiness_summary')) {
    function mg_share_market_readiness_summary(PDO $pdo, int $userId): array
    {
        $state = mg_share_market_merchant_state($pdo, $userId);
        $workflow = is_array($state['workflow'] ?? null) ? $state['workflow'] : [];
        $enrollment = is_array($state['enrollment'] ?? null) ? $state['enrollment'] : null;
        $series = is_array($state['latest_series'] ?? null) ? $state['latest_series'] : null;
        $reserveSnapshot = mg_share_market_credit_reserve_user_snapshot($pdo, $userId);
        $reserve = is_array($reserveSnapshot['latest_reserve_request'] ?? null) ? $reserveSnapshot['latest_reserve_request'] : null;
        $launch = mg_share_market_launch_readiness_for_user($pdo, $userId);
        $lockbox = mg_share_market_lockbox_for_user($pdo, $userId);
        $operator = mg_share_market_operator_checklist_status($pdo, $userId);
        $reserveStatus = (string)($reserve['review_status'] ?? $reserve['status'] ?? 'not_requested');
        $stages = [
            mg_share_market_readiness_summary_stage('opt_in','Merchant opt-in',(string)($workflow['enrollment_status'] ?? 'not_enrolled'),(bool)$enrollment && in_array((string)($workflow['enrollment_status'] ?? ''), ['approved','active'], true),(string)($state['review_feedback']['title'] ?? '')),
            mg_share_market_readiness_summary_stage('credit_reserve','Share credit reserve',$reserveStatus,in_array($reserveStatus, ['approved'], true),$reserve ? number_format((int)($reserve['credits_requested'] ?? 0)) . ' credits requested' : 'No reserve request yet'),
            mg_share_market_readiness_summary_stage('series','Controlled series',(string)($workflow['series_state'] ?? 'none'),(bool)$series && in_array((string)($workflow['series_state'] ?? ''), ['approved','live'], true),(string)($series['name'] ?? 'No series yet')),
            mg_share_market_readiness_summary_stage('launch_readiness','Launch readiness',(string)($launch['status'] ?? 'blocked'),!empty($launch['complete']),(string)($launch['next_action'] ?? '')),
            mg_share_market_readiness_summary_stage('lockbox','Execution lockbox',(string)($lockbox['status'] ?? 'lockbox_blocked'),(string)($lockbox['status'] ?? '') === 'lockbox_ready',(string)($lockbox['packet']['packet_hash'] ?? '')),
            mg_share_market_readiness_summary_stage('operator','Operator checklist',(string)($operator['status'] ?? 'operator_review_in_progress'),!empty($operator['complete']),(string)($operator['merchant_message'] ?? 'Operator review in progress.')),
        ];
        $complete = count(array_filter($stages, static fn(array $stage): bool => !empty($stage['complete'])));
        $nextAction = mg_share_market_readiness_summary_next_action($workflow, $reserve, $launch, $lockbox, $operator);
        return [
            'headline' => $nextAction['label'],
            'progress_percent' => (int)round(($complete / max(1, count($stages))) * 100),
            'stages' => $stages,
            'next_action' => $nextAction,
            'current_workflow' => (string)($workflow['current'] ?? 'not_enrolled'),
            'review_feedback' => $state['review_feedback'] ?? null,
            'latest_note' => (string)($state['review_feedback']['latest_note'] ?? ''),
            'operator_message' => (string)($operator['merchant_message'] ?? ''),
            'execution_enabled' => false,
            'locks' => ['public_launch'=>false,'resale'=>false,'live_issuance'=>false,'ledger_execution'=>false],
        ];
    }
}
