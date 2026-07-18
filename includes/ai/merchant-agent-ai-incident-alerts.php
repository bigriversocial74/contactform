<?php
declare(strict_types=1);

require_once __DIR__ . '/ai-credit-reconciliation.php';

function mg_merchant_ai_incident_alert_days(mixed $value): int
{
    $message = strtolower(trim((string)$value));
    if (preg_match('/(?:^|\s)(7|14|30|60|90|180|365)(?:\s+days?)?$/', $message, $match) === 1) return (int)$match[1];
    return 30;
}

function mg_merchant_ai_incident_alert_body(array $incident): string
{
    $parts = [];
    $parts[] = ucfirst(str_replace('_', ' ', (string)($incident['severity'] ?? 'medium'))) . ' severity';
    $parts[] = ucfirst(str_replace('_', ' ', (string)($incident['status'] ?? 'open')));
    $source = trim((string)($incident['source_type'] ?? ''));
    if ($source !== '') $parts[] = 'Source: ' . str_replace('_', ' ', preg_replace('/^merchant_agent_/', '', $source) ?? $source);
    $reference = trim((string)($incident['source_reference'] ?? ''));
    if ($reference !== '') $parts[] = 'Response: ' . $reference;
    $difference = (int)($incident['token_difference'] ?? 0);
    if ($difference !== 0) $parts[] = 'Token difference: ' . number_format($difference);
    $parts[] = 'Last detected: ' . (string)($incident['last_detected_at'] ?? 'unknown');
    return implode(' • ', $parts);
}

function mg_merchant_ai_incident_alert_chat_response(PDO $pdo, array $user, array $input): array
{
    $merchantId = (int)($user['id'] ?? 0);
    $message = mg_ai_chat_clean($input['message'] ?? 'AI Report Alerts', 2000) ?: 'AI Report Alerts';
    $days = mg_merchant_ai_incident_alert_days($message);
    $thread = mg_agent_thread_by_id($pdo, $merchantId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $incidents = mg_ai_reconciliation_user_incidents($pdo, $merchantId, $days, 20);
    $active = array_values(array_filter($incidents, static fn(array $row): bool => in_array((string)($row['status'] ?? ''), ['open','under_review'], true)));
    $critical = count(array_filter($active, static fn(array $row): bool => (string)($row['severity'] ?? '') === 'critical'));
    $high = count(array_filter($active, static fn(array $row): bool => (string)($row['severity'] ?? '') === 'high'));
    $difference = array_sum(array_map(static fn(array $row): int => abs((int)($row['token_difference'] ?? 0)), $active));
    $blocks = [[
        'type'=>'metric_grid',
        'title'=>'Automated AI accounting incidents',
        'body'=>"Active reconciliation incidents detected for this merchant owner during the last {$days} days. This database query used no AI credits.",
        'metrics'=>[
            ['label'=>'Active incidents','value'=>number_format(count($active))],
            ['label'=>'Critical','value'=>number_format($critical)],
            ['label'=>'High','value'=>number_format($high)],
            ['label'=>'Under review','value'=>number_format(count(array_filter($active, static fn(array $row): bool => (string)($row['status'] ?? '') === 'under_review')))],
            ['label'=>'Token difference','value'=>number_format($difference)],
            ['label'=>'Total history','value'=>number_format(count($incidents))],
        ],
    ]];
    if ($active === []) {
        $blocks[] = ['type'=>'insight','title'=>'No active accounting incidents','body'=>'Automated reconciliation has no open or under-review AI credit incidents for this merchant owner.'];
        $reply = "No active Merchant Agent AI accounting incidents were found for the last {$days} days. This database report did not consume AI credits.";
    } else {
        foreach (array_slice($active, 0, 5) as $incident) {
            $blocks[] = [
                'type'=>'warning',
                'title'=>ucwords(str_replace('_', ' ', (string)($incident['incident_type'] ?? 'AI accounting incident'))),
                'body'=>mg_merchant_ai_incident_alert_body($incident),
            ];
        }
        $reply = count($active) . " active Merchant Agent AI accounting incident" . (count($active) === 1 ? '' : 's') . " require review. This database report did not consume AI credits.";
    }
    $blocks = mg_agent_chat_normalize_blocks($blocks);
    $meta = ['scope'=>'ai_report','thread_public_id'=>$threadId,'skills'=>[],'source'=>'merchant_ai_credit_incident_report','database_only'=>true,'used_ai'=>false,'window_days'=>$days,'mode'=>'alerts'];
    try {
        $pdo->beginTransaction();
        $userMessageId = mg_ai_chat_record_message($pdo, $merchantId, 'user', $message, [], $meta);
        $assistantMessageId = mg_ai_chat_record_message($pdo, $merchantId, 'assistant', $reply, [], $meta + ['blocks'=>$blocks,'model'=>'database-ai-incident-report-v1']);
        if ($threadId !== '' && mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
            $pdo->prepare("UPDATE merchant_agent_threads SET title=IF(title='Current chat','AI Report Alerts',title),updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")->execute([$merchantId,$threadId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return [
        'database_only'=>true,'used_ai'=>false,
        'ai_report'=>['mode'=>'alerts','window_days'=>$days,'active_incidents'=>$active,'incident_history'=>$incidents,'blocks'=>$blocks],
        'user_message'=>['id'=>$userMessageId,'role'=>'user','body'=>$message,'cards'=>[],'blocks'=>[],'scope'=>'ai_report','thread_public_id'=>$threadId,'created_at'=>gmdate('c')],
        'assistant_message'=>['id'=>$assistantMessageId,'role'=>'assistant','body'=>$reply,'cards'=>[],'blocks'=>$blocks,'scope'=>'ai_report','thread_public_id'=>$threadId,'model'=>'database-ai-incident-report-v1','created_at'=>gmdate('c')],
        'state'=>mg_ai_chat_public_state($pdo, $merchantId),
    ];
}
