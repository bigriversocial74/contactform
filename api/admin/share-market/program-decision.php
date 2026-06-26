<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/share-market/program-workflow.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
if (!mg_share_market_admin_authorized($user)) mg_fail('Share Market Admin permission is required.', 403);
mg_rate_limit('share_market.program.decision', 'user:' . (int)$user['id'], 20, 300);

try {
    $targetType = strtolower(trim((string)($input['target_type'] ?? '')));
    $decision = strtolower(trim((string)($input['decision'] ?? '')));
    $note = trim((string)($input['note'] ?? ''));
    if ($note === '' || mb_strlen($note) > 1000) throw new InvalidArgumentException('Decision note is required and cannot exceed 1,000 characters.');
    $pdo = mg_db();
    $event = null;
    $payload = ['admin_user_id' => (int)$user['id'], 'note' => $note, 'created_at' => gmdate('c'), 'execution_enabled' => false];
    if ($targetType === 'enrollment') {
        $participantId = trim((string)($input['participant_id'] ?? ''));
        if ($participantId === '' || preg_match('/^[A-Za-z0-9:_-]{8,120}$/', $participantId) !== 1) throw new InvalidArgumentException('Valid participant ID is required.');
        $payload['participant_id'] = $participantId;
        $payload['participant_user_id'] = (int)($input['participant_user_id'] ?? 0);
        $event = match ($decision) {
            'approve' => 'share_market.program.enrollment_approved',
            'reject' => 'share_market.program.enrollment_rejected',
            'pause' => 'share_market.program.enrollment_paused',
            'suspend' => 'share_market.program.enrollment_suspended',
            default => null,
        };
    } elseif ($targetType === 'series') {
        $seriesId = trim((string)($input['series_id'] ?? ''));
        if ($seriesId === '' || preg_match('/^sm_[A-Za-z0-9]{20,64}$/', $seriesId) !== 1) throw new InvalidArgumentException('Valid series ID is required.');
        $payload['series_id'] = $seriesId;
        $payload['participant_user_id'] = (int)($input['participant_user_id'] ?? 0);
        $event = match ($decision) {
            'approve' => 'share_market.program.series_approved',
            'reject' => 'share_market.program.series_rejected',
            'changes' => 'share_market.program.series_changes_requested',
            'pause' => 'share_market.program.series_paused',
            default => null,
        };
    } else {
        throw new InvalidArgumentException('Select enrollment or series review.');
    }
    if (!$event) throw new InvalidArgumentException('Select a valid review decision.');
    mg_share_market_program_append_event($pdo, $event, $payload, (int)$user['id']);
    mg_ok(mg_share_market_admin_review_snapshot($pdo), 'Share Market review decision recorded. No market was launched.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'share_market.program_decision_failed', 'Unable to record Share Market review decision.', ['exception_class' => $e::class], (int)$user['id']);
    mg_fail('Unable to record Share Market review decision.', 500);
}
