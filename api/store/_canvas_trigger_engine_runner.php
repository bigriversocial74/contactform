<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_trigger_engine.php';

/**
 * Executes the production trigger engine with execution-mode-aware normalized
 * event keys. A dry preview and a later notification run are separate,
 * auditable evaluations for the same underlying server signal.
 */
function mg_store_trigger_engine_run_authorized(PDO $pdo, array $merchantUser, bool $forceDryRun = false): array
{
    mg_store_trigger_engine_require_schema($pdo);
    $merchantUserId = (int)($merchantUser['id'] ?? 0);
    if ($merchantUserId < 1) throw new RuntimeException('Merchant account is required.');

    $settingsRow = mg_store_trigger_engine_settings($pdo, $merchantUserId, true);
    $mode = $forceDryRun ? 'dry_run' : (string)$settingsRow['execution_mode'];
    if ($mode === 'paused') throw new RuntimeException('Trigger engine is paused. Select Dry Run or Notification mode first.');
    if (!in_array($mode, ['dry_run', 'notification'], true)) throw new RuntimeException('Invalid trigger engine mode.');

    $pdo->prepare("UPDATE mg_store_trigger_engine_settings SET last_run_at=NOW(),last_run_status='running',updated_at=NOW() WHERE merchant_user_id=?")
        ->execute([$merchantUserId]);

    $ruleStmt = $pdo->prepare("SELECT * FROM mg_store_trigger_engine_rules WHERE merchant_user_id=? AND status='enabled' ORDER BY priority DESC,updated_at DESC,id DESC LIMIT 100");
    $ruleStmt->execute([$merchantUserId]);
    $rules = $ruleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $sessions = mg_store_trigger_engine_active_sessions($pdo, $merchantUserId, 100);

    $summary = [
        'mode' => $mode,
        'rules' => count($rules),
        'active_sessions' => count($sessions),
        'events' => 0,
        'evaluations' => 0,
        'matched' => 0,
        'delivered' => 0,
        'skipped' => 0,
        'blocked' => 0,
        'errors' => 0,
        'duplicates' => 0,
        'reward_issued' => false,
        'browser_overlap_used' => false,
        'started_at' => date('c'),
    ];
    $recent = [];
    $maxDeliveries = max(1, min(100, (int)$settingsRow['max_notifications_per_run']));

    try {
        foreach ($rules as $rule) {
            foreach ($sessions as $session) {
                $candidate = mg_store_trigger_engine_candidate($rule, $session);
                if (!$candidate) continue;

                // Dry-run and notification mode retain separate normalized event
                // records so previewing never consumes the later live evaluation.
                $candidate['event_key'] = substr((string)$candidate['event_key'] . ':mode:' . $mode, 0, 190);
                $candidate['evidence']['execution_mode'] = $mode;
                $candidate['evidence']['preview_consumes_notification'] = false;

                $event = mg_store_trigger_engine_event($pdo, $merchantUserId, $session, $candidate);
                $summary['events']++;

                if (mg_store_trigger_engine_existing_evaluation($pdo, (int)$event['id'], (int)$rule['id'])) {
                    $summary['duplicates']++;
                    continue;
                }

                $evaluation = mg_store_trigger_engine_evaluate(
                    $pdo,
                    $merchantUser,
                    $rule,
                    $session,
                    $event,
                    $candidate,
                    $mode,
                    $summary['delivered'] >= $maxDeliveries
                );
                $summary['evaluations']++;
                $decision = (string)$evaluation['decision'];
                if ($decision === 'matched') $summary['matched']++;
                elseif ($decision === 'delivered') $summary['delivered']++;
                elseif ($decision === 'skipped') $summary['skipped']++;
                elseif ($decision === 'blocked') $summary['blocked']++;
                elseif ($decision === 'error') $summary['errors']++;

                $recent[] = [
                    'rule_id' => (string)$rule['public_id'],
                    'rule_name' => (string)$rule['name'],
                    'event_type' => (string)$candidate['event_type'],
                    'customer_user_id' => (int)$session['customer_user_id'],
                    'decision' => $decision,
                    'reason_code' => (string)$evaluation['reason_code'],
                    'reason_text' => (string)$evaluation['reason_text'],
                    'probability' => (float)$candidate['probability'],
                    'confidence' => (float)$candidate['confidence'],
                ];

                $pdo->prepare("UPDATE mg_store_trigger_events SET status=?,updated_at=NOW() WHERE id=?")
                    ->execute([$decision === 'error' ? 'error' : 'evaluated', (int)$event['id']]);
                $pdo->prepare("UPDATE mg_store_trigger_engine_rules SET last_evaluated_at=NOW(),last_matched_at=IF(? IN ('matched','delivered'),NOW(),last_matched_at),last_delivered_at=IF(?='delivered',NOW(),last_delivered_at),updated_at=NOW() WHERE id=?")
                    ->execute([$decision, $decision, (int)$rule['id']]);

                if ($decision === 'delivered' && !empty($rule['trigger_zone_public_id'])) {
                    $pdo->prepare('UPDATE mg_store_trigger_zones SET last_triggered_at=NOW(),updated_at=NOW() WHERE public_id=? AND merchant_user_id=?')
                        ->execute([(string)$rule['trigger_zone_public_id'], $merchantUserId]);
                }
            }
        }

        $summary['completed_at'] = date('c');
        $summary['status'] = $summary['errors'] > 0 ? 'partial' : 'completed';
        $pdo->prepare('UPDATE mg_store_trigger_engine_settings SET last_run_status=?,last_run_summary_json=?,updated_at=NOW() WHERE merchant_user_id=?')
            ->execute([$summary['status'], mg_store_trigger_engine_encode($summary), $merchantUserId]);
    } catch (Throwable $error) {
        $summary['completed_at'] = date('c');
        $summary['status'] = 'failed';
        $summary['failure'] = $error->getMessage();
        $pdo->prepare("UPDATE mg_store_trigger_engine_settings SET last_run_status='failed',last_run_summary_json=?,updated_at=NOW() WHERE merchant_user_id=?")
            ->execute([mg_store_trigger_engine_encode($summary), $merchantUserId]);
        throw $error;
    }

    mg_event('store_canvas.trigger_engine_run', [
        'mode' => $mode,
        'rules' => $summary['rules'],
        'active_sessions' => $summary['active_sessions'],
        'evaluations' => $summary['evaluations'],
        'delivered' => $summary['delivered'],
        'blocked' => $summary['blocked'],
        'errors' => $summary['errors'],
        'reward_issued' => false,
        'browser_overlap_used' => false,
        'preview_consumes_notification' => false,
    ], $merchantUserId);

    return ['summary' => $summary, 'recent' => array_slice($recent, -50)];
}
