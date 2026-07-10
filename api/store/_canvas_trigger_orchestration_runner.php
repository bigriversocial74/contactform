<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_trigger_orchestration.php';

/**
 * Runs the orchestration queue while preserving previewed events for a later
 * Notification-mode evaluation. Dry-run evaluations are durable evidence, but
 * they do not consume the canonical queue event.
 */
function mg_trigger_orchestration_process_queue_authorized(PDO $pdo, array $merchantUser, bool $forceDryRun = false, int $limit = 100): array
{
    $merchantUserId = (int)($merchantUser['id'] ?? 0);
    if ($merchantUserId < 1) throw new RuntimeException('Merchant account is required.');

    $before = $pdo->prepare('SELECT COALESCE(MAX(id),0) FROM mg_store_trigger_evaluations WHERE merchant_user_id=?');
    $before->execute([$merchantUserId]);
    $beforeEvaluationId = (int)$before->fetchColumn();

    $result = mg_trigger_orchestration_process_queue($pdo, $merchantUser, $forceDryRun, $limit);
    if ((string)($result['mode'] ?? '') !== 'dry_run') return $result;

    $select = $pdo->prepare("SELECT DISTINCT e.id
        FROM mg_store_trigger_evaluations ev
        JOIN mg_store_trigger_events e ON e.id=ev.event_id AND e.merchant_user_id=ev.merchant_user_id
        LEFT JOIN mg_store_trigger_evaluations live ON live.event_id=e.id AND live.execution_mode='notification'
        WHERE ev.merchant_user_id=? AND ev.id>? AND ev.execution_mode='dry_run'
          AND e.status='evaluated' AND live.id IS NULL");
    $select->execute([$merchantUserId,$beforeEvaluationId]);
    $eventIds = array_values(array_filter(array_map('intval',$select->fetchAll(PDO::FETCH_COLUMN) ?: []),static fn(int $id): bool => $id > 0));

    if ($eventIds !== []) {
        $placeholders = implode(',',array_fill(0,count($eventIds),'?'));
        $update = $pdo->prepare("UPDATE mg_store_trigger_events
            SET status='pending',processed_at=NULL,locked_at=NULL,locked_by=NULL,available_at=NOW(),
                last_error_code='dry_run_previewed',last_error_message='Dry-run evaluation completed; event remains queued for Notification mode.',updated_at=NOW()
            WHERE merchant_user_id=? AND id IN ({$placeholders})");
        $update->execute(array_merge([$merchantUserId],$eventIds));
    }

    $result['preview_requeued_count'] = count($eventIds);
    $result['preview_consumes_notification'] = false;
    $encoded = mg_store_trigger_engine_encode($result);
    $pdo->prepare('UPDATE mg_store_trigger_engine_settings SET last_run_summary_json=?,updated_at=NOW() WHERE merchant_user_id=?')
        ->execute([$encoded,$merchantUserId]);
    if (!empty($result['run_id'])) {
        $pdo->prepare('UPDATE mg_store_trigger_scheduler_runs SET summary_json=? WHERE public_id=? AND merchant_user_id=?')
            ->execute([$encoded,(string)$result['run_id'],$merchantUserId]);
    }
    mg_event('store_canvas.trigger_orchestration_preview_preserved',[
        'merchant_user_id'=>$merchantUserId,
        'preview_requeued_count'=>count($eventIds),
        'preview_consumes_notification'=>false,
        'reward_issued'=>false,
        'browser_overlap_used'=>false,
    ],$merchantUserId);
    return $result;
}
