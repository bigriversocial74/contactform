<?php
declare(strict_types=1);

require_once __DIR__ . '/user-credit-service.php';

/**
 * Database-only Merchant Agent AI credit and ledger report.
 *
 * This service never calls Anthropic or another external model. It reconciles
 * the signed-in merchant owner's current credit account, Merchant Agent usage
 * ledger, provider completion records, and accounting security alerts.
 */
function mg_merchant_ai_report_is_keyword(mixed $value): bool
{
    $message = strtolower(trim((string)$value));
    $message = preg_replace('/\s+/', ' ', $message) ?? $message;
    return preg_match('/^(?:\/?ai report)(?:\s+(?:7|14|30|60|90|180|365)(?:\s+days?)?)?$/', $message) === 1;
}

function mg_merchant_ai_report_days(mixed $value, int $fallback = 30): int
{
    $message = strtolower(trim((string)$value));
    if (preg_match('/(?:^|\s)(7|14|30|60|90|180|365)(?:\s+days?)?$/', $message, $match) === 1) {
        return (int)$match[1];
    }
    return max(7, min(365, $fallback));
}

function mg_merchant_ai_report_row(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'merchant_agent.ai_report_query_failed', 'A Merchant Agent AI report query could not be completed.', ['exception_class'=>$error::class]);
        }
        return [];
    }
}

function mg_merchant_ai_report_rows(PDO $pdo, string $sql, array $params = [], int $limit = 20): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_slice($rows, 0, max(1, min(100, $limit)));
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'merchant_agent.ai_report_query_failed', 'A Merchant Agent AI report query could not be completed.', ['exception_class'=>$error::class]);
        }
        return [];
    }
}

function mg_merchant_ai_report_token_value(mixed $value): string
{
    return $value === null ? 'Unlimited' : number_format(max(0, (int)$value));
}

function mg_merchant_ai_report_source_label(string $source): string
{
    $source = preg_replace('/^merchant_agent_/', '', strtolower(trim($source))) ?? $source;
    return ucwords(str_replace(['_', '.'], ' ', $source !== '' ? $source : 'other'));
}

function mg_merchant_ai_report_build(PDO $pdo, array $user, array $packageContext, int $days = 30): array
{
    $userId = (int)($user['id'] ?? 0);
    $days = max(7, min(365, $days));
    $credits = mg_merchant_agent_ai_credit_apply_package_gate(
        mg_ai_credit_snapshot($pdo, $userId, 'anthropic'),
        $packageContext
    );
    $aiStatus = mg_merchant_agent_ai_status($pdo, $user, $packageContext);
    $schemaReady = !empty($credits['schema_ready']);
    $windowSql = "DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$days} DAY)";

    $ledger = [
        'debit_count'=>0,
        'input_tokens'=>0,
        'output_tokens'=>0,
        'debited_tokens'=>0,
        'package_tokens'=>0,
        'manual_tokens'=>0,
        'missing_references'=>0,
    ];
    $provider = [
        'completed_count'=>0,
        'failed_count'=>0,
        'input_tokens'=>0,
        'output_tokens'=>0,
        'completed_tokens'=>0,
    ];
    $alerts = ['total'=>0,'by_type'=>[]];
    $sourceRows = [];
    $recentLedger = [];

    if ($schemaReady) {
        $ledgerRow = mg_merchant_ai_report_row(
            $pdo,
            "SELECT COUNT(*) debit_count,
                    COALESCE(SUM(input_tokens),0) input_tokens,
                    COALESCE(SUM(output_tokens),0) output_tokens,
                    COALESCE(SUM(ABS(token_delta)),0) debited_tokens,
                    COALESCE(SUM(ABS(package_token_delta)),0) package_tokens,
                    COALESCE(SUM(ABS(manual_token_delta)),0) manual_tokens,
                    COALESCE(SUM(source_reference IS NULL OR source_reference=''),0) missing_references
             FROM ai_credit_ledger
             WHERE user_id=? AND provider_key='anthropic' AND entry_type='usage_debit'
               AND LEFT(source_type,15)='merchant_agent_'
               AND created_at>={$windowSql}",
            [$userId]
        );
        foreach (array_keys($ledger) as $key) $ledger[$key] = (int)($ledgerRow[$key] ?? 0);

        $providerRow = mg_merchant_ai_report_row(
            $pdo,
            "SELECT COALESCE(SUM(request_status='completed'),0) completed_count,
                    COALESCE(SUM(request_status='failed'),0) failed_count,
                    COALESCE(SUM(CASE WHEN request_status='completed' THEN input_tokens ELSE 0 END),0) input_tokens,
                    COALESCE(SUM(CASE WHEN request_status='completed' THEN output_tokens ELSE 0 END),0) output_tokens
             FROM ai_usage_events e
             INNER JOIN ai_providers p ON p.id=e.provider_id
             WHERE e.user_id=? AND p.provider_key='anthropic'
               AND e.created_at>={$windowSql}
               AND e.metadata_json REGEXP '\"source\"[[:space:]]*:[[:space:]]*\"merchant_agent_'",
            [$userId]
        );
        $provider['completed_count'] = (int)($providerRow['completed_count'] ?? 0);
        $provider['failed_count'] = (int)($providerRow['failed_count'] ?? 0);
        $provider['input_tokens'] = (int)($providerRow['input_tokens'] ?? 0);
        $provider['output_tokens'] = (int)($providerRow['output_tokens'] ?? 0);
        $provider['completed_tokens'] = $provider['input_tokens'] + $provider['output_tokens'];

        $sourceRows = mg_merchant_ai_report_rows(
            $pdo,
            "SELECT source_type,COUNT(*) request_count,COALESCE(SUM(ABS(token_delta)),0) tokens,
                    COALESCE(SUM(input_tokens),0) input_tokens,COALESCE(SUM(output_tokens),0) output_tokens
             FROM ai_credit_ledger
             WHERE user_id=? AND provider_key='anthropic' AND entry_type='usage_debit'
               AND LEFT(source_type,15)='merchant_agent_'
               AND created_at>={$windowSql}
             GROUP BY source_type ORDER BY tokens DESC,source_type ASC",
            [$userId],
            20
        );

        $recentLedger = mg_merchant_ai_report_rows(
            $pdo,
            "SELECT public_id,source_type,source_reference,input_tokens,output_tokens,ABS(token_delta) tokens,created_at
             FROM ai_credit_ledger
             WHERE user_id=? AND provider_key='anthropic' AND entry_type='usage_debit'
               AND LEFT(source_type,15)='merchant_agent_'
             ORDER BY id DESC LIMIT 8",
            [$userId],
            8
        );

        if (mg_ai_credit_table_exists($pdo, 'security_logs')) {
            $alertRows = mg_merchant_ai_report_rows(
                $pdo,
                "SELECT event_type,COUNT(*) total
                 FROM security_logs
                 WHERE user_id=? AND created_at>={$windowSql}
                   AND event_type IN (
                     'merchant_agent.ai_credit_debit_failed',
                     'merchant_agent.ai_preflight_state_missing',
                     'merchant_agent.ai_call_context_missing'
                   )
                 GROUP BY event_type ORDER BY total DESC,event_type ASC",
                [$userId],
                10
            );
            foreach ($alertRows as $row) {
                $count = (int)($row['total'] ?? 0);
                $alerts['total'] += $count;
                $alerts['by_type'][] = ['event_type'=>(string)($row['event_type'] ?? ''),'total'=>$count];
            }
        }
    }

    $difference = $provider['completed_tokens'] - $ledger['debited_tokens'];
    $balanced = $schemaReady
        && $difference === 0
        && $ledger['missing_references'] === 0
        && $alerts['total'] === 0;

    $sourceChart = [];
    foreach ($sourceRows as $row) {
        $source = (string)($row['source_type'] ?? '');
        $sourceChart[] = [
            'label'=>mg_merchant_ai_report_source_label($source),
            'value'=>(int)($row['tokens'] ?? 0),
        ];
    }

    $blocks = [
        [
            'type'=>'metric_grid',
            'title'=>'AI credits and package status',
            'body'=>"Current signed-in merchant owner account. Database report for the last {$days} days; no external AI request was used.",
            'metrics'=>[
                ['label'=>'AI status','value'=>(string)($aiStatus['label'] ?? 'Unavailable')],
                ['label'=>'Available tokens','value'=>mg_merchant_ai_report_token_value(array_key_exists('available_tokens', $credits) ? $credits['available_tokens'] : 0)],
                ['label'=>'Package remaining','value'=>mg_merchant_ai_report_token_value(array_key_exists('package_tokens_remaining', $credits) ? $credits['package_tokens_remaining'] : 0)],
                ['label'=>'Manual credits','value'=>number_format((int)($credits['manual_tokens_remaining'] ?? 0))],
                ['label'=>'Current-period usage','value'=>number_format((int)($credits['usage']['month'] ?? 0))],
                ['label'=>'Package','value'=>(string)($credits['package']['name'] ?? 'Unknown')],
            ],
        ],
        [
            'type'=>'metric_grid',
            'title'=>'Merchant Agent ledger reconciliation',
            'body'=>'Compares completed Merchant Agent provider usage records with recorded owner-credit ledger debits.',
            'metrics'=>[
                ['label'=>'Completed responses','value'=>number_format($provider['completed_count'])],
                ['label'=>'Ledger debits','value'=>number_format($ledger['debit_count'])],
                ['label'=>'Provider tokens','value'=>number_format($provider['completed_tokens'])],
                ['label'=>'Debited tokens','value'=>number_format($ledger['debited_tokens'])],
                ['label'=>'Token difference','value'=>number_format($difference)],
                ['label'=>'Accounting alerts','value'=>number_format($alerts['total'])],
            ],
        ],
    ];

    if ($sourceChart !== []) {
        $blocks[] = [
            'type'=>'chart',
            'title'=>'AI usage by Merchant Agent source',
            'body'=>"Recorded ledger token usage during the last {$days} days.",
            'chart_type'=>'bar',
            'data'=>$sourceChart,
        ];
    }

    $statusBody = !$schemaReady
        ? 'The AI credit schema is unavailable, so ledger reconciliation could not be completed.'
        : ($balanced
            ? 'Provider token totals and Merchant Agent ledger debits are balanced. Every debit has a response reference and no accounting alerts were found.'
            : 'Review required: provider and ledger totals differ, a debit is missing its response reference, or an accounting alert was recorded.');
    $blocks[] = [
        'type'=>$balanced ? 'insight' : 'warning',
        'title'=>$balanced ? 'AI accounting is balanced' : 'AI accounting needs review',
        'body'=>$statusBody,
    ];

    return [
        'generated_at'=>gmdate('c'),
        'window_days'=>$days,
        'database_only'=>true,
        'used_ai'=>false,
        'balanced'=>$balanced,
        'token_difference'=>$difference,
        'ai_status'=>$aiStatus,
        'ai_credits'=>$credits,
        'ledger'=>$ledger,
        'provider_usage'=>$provider,
        'accounting_alerts'=>$alerts,
        'usage_by_source'=>array_map(static fn(array $row): array => [
            'source_type'=>(string)($row['source_type'] ?? ''),
            'request_count'=>(int)($row['request_count'] ?? 0),
            'tokens'=>(int)($row['tokens'] ?? 0),
            'input_tokens'=>(int)($row['input_tokens'] ?? 0),
            'output_tokens'=>(int)($row['output_tokens'] ?? 0),
        ], $sourceRows),
        'recent_ledger'=>array_map(static fn(array $row): array => [
            'id'=>(string)($row['public_id'] ?? ''),
            'source_type'=>(string)($row['source_type'] ?? ''),
            'source_reference'=>(string)($row['source_reference'] ?? ''),
            'input_tokens'=>(int)($row['input_tokens'] ?? 0),
            'output_tokens'=>(int)($row['output_tokens'] ?? 0),
            'tokens'=>(int)($row['tokens'] ?? 0),
            'created_at'=>(string)($row['created_at'] ?? ''),
        ], $recentLedger),
        'blocks'=>mg_agent_chat_normalize_blocks($blocks),
        'privacy'=>[
            'merchant_owner_scoped'=>true,
            'external_ai_called'=>false,
            'customer_details_included'=>false,
            'payment_credentials_included'=>false,
        ],
    ];
}

function mg_merchant_ai_report_chat_response(PDO $pdo, array $user, array $packageContext, array $input): array
{
    $merchantId = (int)($user['id'] ?? 0);
    $message = mg_ai_chat_clean($input['message'] ?? 'AI Report', 2000) ?: 'AI Report';
    $days = mg_merchant_ai_report_days($message, (int)($input['days'] ?? 30));
    $thread = mg_agent_thread_by_id($pdo, $merchantId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $report = mg_merchant_ai_report_build($pdo, $user, $packageContext, $days);
    $reply = !empty($report['balanced'])
        ? "Your Merchant Agent AI ledger is balanced for the last {$days} days. This report used only Microgifter's database and did not consume AI credits."
        : "Your Merchant Agent AI report found an accounting item that needs review for the last {$days} days. This database report did not consume AI credits.";
    $meta = [
        'scope'=>'ai_report',
        'thread_public_id'=>$threadId,
        'skills'=>[],
        'source'=>'merchant_ai_credit_report',
        'database_only'=>true,
        'used_ai'=>false,
        'window_days'=>$days,
    ];

    try {
        $pdo->beginTransaction();
        $userMessageId = mg_ai_chat_record_message($pdo, $merchantId, 'user', $message, [], $meta);
        $assistantMessageId = mg_ai_chat_record_message(
            $pdo,
            $merchantId,
            'assistant',
            $reply,
            [],
            $meta + ['blocks'=>$report['blocks'],'model'=>'database-ai-report-v1']
        );
        if ($threadId !== '' && mg_agent_table_exists($pdo, 'merchant_agent_threads')) {
            $pdo->prepare("UPDATE merchant_agent_threads SET title=IF(title='Current chat','AI Report',title),updated_at=NOW() WHERE merchant_user_id=? AND public_id=?")
                ->execute([$merchantId, $threadId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    return [
        'database_only'=>true,
        'used_ai'=>false,
        'ai_report'=>$report,
        'user_message'=>[
            'id'=>$userMessageId,
            'role'=>'user',
            'body'=>$message,
            'cards'=>[],
            'blocks'=>[],
            'scope'=>'ai_report',
            'thread_public_id'=>$threadId,
            'created_at'=>gmdate('c'),
        ],
        'assistant_message'=>[
            'id'=>$assistantMessageId,
            'role'=>'assistant',
            'body'=>$reply,
            'cards'=>[],
            'blocks'=>$report['blocks'],
            'scope'=>'ai_report',
            'thread_public_id'=>$threadId,
            'model'=>'database-ai-report-v1',
            'created_at'=>gmdate('c'),
        ],
        'state'=>mg_ai_chat_public_state($pdo, $merchantId),
    ];
}
