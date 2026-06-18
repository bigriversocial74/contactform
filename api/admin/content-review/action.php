<?php
declare(strict_types=1);

require_once __DIR__ . '/_actions.php';

mg_require_method('POST');
$user = mg_content_review_require(true);
$input = mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('admin.content_review.action', 'user:' . (int)$user['id'], 40, 300);

$action = strtolower(trim((string)($input['action'] ?? '')));
$reportPublicId = mg_content_review_reference($input['report_id'] ?? '');
$reason = mb_substr(trim((string)($input['reason'] ?? '')), 0, 5000);
$allowed = ['claim','note','dismiss','resolve','hide_content','restore_content','quarantine_media','warn_user','restrict_posting','suspend_user','reactivate_user'];
if (!in_array($action, $allowed, true)) mg_fail('Invalid review action.', 422);
if ($action !== 'claim' && $reason === '') mg_fail('A reason is required for this action.', 422);

$pdo = mg_db();
$pdo->beginTransaction();
try {
    $report = mg_content_review_report($pdo, $reportPublicId, true);
    $actorId = (int)$user['id'];
    $targetId = (int)($report['subject_user_id'] ?? 0);
    if ($targetId === $actorId && in_array($action, ['warn_user','restrict_posting','suspend_user','reactivate_user'], true)) {
        throw new RuntimeException('You cannot apply this account action to yourself.');
    }

    $previousState = null;
    $resultingState = null;
    $metadata = ['subject_type'=>(string)$report['subject_type']];

    if ($action === 'claim') {
        if (!in_array((string)$report['status'], ['open','reviewing'], true)) throw new RuntimeException('Closed reports cannot be claimed.');
        $previousState = (string)$report['status'];
        $resultingState = 'reviewing';
        $pdo->prepare("UPDATE social_reports SET assigned_user_id=?,status='reviewing',reviewed_at=COALESCE(reviewed_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$report['id']]);
    } elseif ($action === 'note') {
        $previousState = (string)$report['status'];
        $resultingState = (string)$report['status'];
    } elseif ($action === 'dismiss' || $action === 'resolve') {
        $previousState = (string)$report['status'];
        $resultingState = $action === 'dismiss' ? 'dismissed' : 'resolved';
        $pdo->prepare(
            'UPDATE social_reports SET status=?,reviewed_by_user_id=?,assigned_user_id=COALESCE(assigned_user_id,?),
             resolution_note=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?'
        )->execute([$resultingState,$actorId,$actorId,$reason,(int)$report['id']]);
        mg_content_review_clear_flag_if_resolved($pdo, $report);
    } elseif (in_array($action, ['hide_content','restore_content','quarantine_media'], true)) {
        if ($action === 'quarantine_media' && (string)$report['subject_type'] !== 'media') {
            throw new RuntimeException('Only uploaded media can be quarantined.');
        }
        $state = mg_content_review_set_content_state($pdo, $report, $action === 'quarantine_media' ? 'hide_content' : $action);
        $previousState = $state['previous'];
        $resultingState = $state['result'];
        $pdo->prepare("UPDATE social_reports SET status='reviewing',assigned_user_id=COALESCE(assigned_user_id,?),reviewed_at=COALESCE(reviewed_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$report['id']]);
    } elseif ($action === 'warn_user') {
        if ($targetId < 1) throw new RuntimeException('The report has no linked account.');
        if (mg_content_review_target_is_super_admin($pdo, $targetId)) throw new RuntimeException('Super administrator accounts cannot be warned here.');
        $metadata['notification_id'] = mg_content_review_warn_user($pdo, $report, $actorId, $reason);
        $previousState = (string)$report['status'];
        $resultingState = 'reviewing';
        $pdo->prepare("UPDATE social_reports SET status='reviewing',assigned_user_id=COALESCE(assigned_user_id,?),reviewed_at=COALESCE(reviewed_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$report['id']]);
    } elseif ($action === 'restrict_posting') {
        if ($targetId < 1 || mg_content_review_target_is_super_admin($pdo, $targetId)) throw new RuntimeException('This account cannot be restricted here.');
        $metadata['restriction_id'] = mg_content_review_restrict_posting($pdo, $report, $actorId, $reason);
        $previousState = 'unrestricted';
        $resultingState = 'posting_restricted';
        $pdo->prepare("UPDATE social_reports SET status='reviewing',assigned_user_id=COALESCE(assigned_user_id,?),reviewed_at=COALESCE(reviewed_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$report['id']]);
        mg_create_notification($pdo,$targetId,'system','Posting restricted',mb_substr($reason,0,500),'/notifications.php',[
            'actor_user_id'=>$actorId,
            'event_key'=>'review.restriction.' . strtolower((string)$report['public_id']),
            'report_id'=>(string)$report['public_id'],
        ]);
    } elseif ($action === 'suspend_user' || $action === 'reactivate_user') {
        $state = mg_content_review_set_user_status($pdo, $report, $action, $actorId);
        $previousState = $state['previous'];
        $resultingState = $state['result'];
        $pdo->prepare("UPDATE social_reports SET status='reviewing',assigned_user_id=COALESCE(assigned_user_id,?),reviewed_at=COALESCE(reviewed_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$actorId,(int)$report['id']]);
        mg_create_notification($pdo,$targetId,'system',$action === 'suspend_user' ? 'Account suspended' : 'Account reactivated',mb_substr($reason,0,500),'/notifications.php',[
            'actor_user_id'=>$actorId,
            'event_key'=>'review.account.' . $action . '.' . strtolower((string)$report['public_id']),
            'report_id'=>(string)$report['public_id'],
        ]);
    }

    $actionId = mg_content_review_record_action(
        $pdo,(int)$report['id'],$actorId,$action,$reason,$previousState,$resultingState,$metadata
    );
    $pdo->commit();

    mg_audit('admin.content_review.' . $action, 'social_report', [
        'report_id'=>$reportPublicId,
        'action_id'=>$actionId,
        'subject_type'=>(string)$report['subject_type'],
        'subject_reference'=>(string)$report['subject_reference'],
        'previous_state'=>$previousState,
        'resulting_state'=>$resultingState,
    ], $actorId);
    mg_event('admin.content_review.' . $action, [
        'report_id'=>$reportPublicId,
        'subject_type'=>(string)$report['subject_type'],
    ], $actorId);
    mg_ok([
        'report_id'=>$reportPublicId,
        'action'=>$action,
        'action_id'=>$actionId,
        'previous_state'=>$previousState,
        'resulting_state'=>$resultingState,
    ], 'Review action completed.');
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'admin.content_review.action_failed', 'Content review action failed.', [
        'action'=>$action,
        'exception_class'=>$error::class,
    ], (int)$user['id']);
    mg_fail('Unable to complete the review action.', 500);
}
