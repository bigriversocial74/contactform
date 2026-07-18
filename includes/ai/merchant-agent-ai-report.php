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
function mg_merchant_ai_report_command(mixed $value): array
{
    $message = strtolower(trim((string)$value));
    $message = preg_replace('/\s+/', ' ', $message) ?? $message;
    if (preg_match('/^\/?ai report(?:\s+(details|alerts|recent))?(?:\s+(7|14|30|60|90|180|365)(?:\s+days?)?)?$/', $message, $match) !== 1) {
        return [];
    }
    return [
        'mode'=>(string)($match[1] ?? '') !== '' ? (string)$match[1] : 'summary',
        'days'=>isset($match[2]) && $match[2] !== '' ? (int)$match[2] : 30,
    ];
}

function mg_merchant_ai_report_is_keyword(mixed $value): bool
{
    return mg_merchant_ai_report_command($value) !== [];
}

function mg_merchant_ai_report_mode(mixed $value): string
{
    $command = mg_merchant_ai_report_command($value);
    return (string)($command['mode'] ?? 'summary');
}

function mg_merchant_ai_report_days(mixed $value, int $fallback = 30): int
{
    $command = mg_merchant_ai_report_command($value);
    return max(7, min(365, (int)($command['days'] ?? $fallback)));
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

function mg_merchant_ai_report_time(mixed $value): string
{
    $value = trim((string)$value);
    if ($value === '') return 'Unknown time';
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('M j, Y g:i A') . ' UTC';
    } catch (Throwable) {
        return $value;
    }
}

function mg_merchant_ai_report_context(mixed $value): array
{
    if (is_array($value)) return $value;
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_merchant_ai_report_context_value(array $context, string $key): string
{
    $value = $context[$key] ?? '';
    if (is_scalar($value)) return trim((string)$value);
    return '';
}

function mg_merchant_ai_report_ledger_body(array $row): string
{
    $source = mg_merchant_ai_report_source_label((string)($row['source_type'] ?? ''));
    $reference = trim((string)($row['source_reference'] ?? '')) ?: 'Missing response reference';
    $model = trim((string)($row['model_name'] ?? $row['model_key'] ?? '')) ?: 'Unknown model';
    $input = (int)($row['input_tokens'] ?? 0);
    $output = (int)($row['output_tokens'] ?? 0);
    $tokens = (int)($row['tokens'] ?? ($input + $output));
    return mg_merchant_ai_report_time($row['created_at'] ?? '') . " • {$source} • {$model} • {$input} input + {$output} output = {$tokens} debited tokens • Ref: {$reference}";
}

function mg_merchant_ai_report_provider_body(array $row): string
{
    $metadata = mg_merchant_ai_report_context($row['metadata_json'] ?? []);
    $source = mg_merchant_ai_report_source_label(mg_merchant_ai_report_context_value($metadata, 'source'));
    $model = trim((string)($row['model_name'] ?? $row['model_key'] ?? '')) ?: 'Unknown model';
    $status = ucfirst(strtolower(trim((string)($row['request_status'] ?? 'unknown'))));
    $input = (int)($row['input_tokens'] ?? 0);
    $output = (int)($row['output_tokens'] ?? 0);
    $tokens = $input + $output;
    return mg_merchant_ai_report_time($row['created_at'] ?? '') . " • {$status} • {$source} • {$model} • {$input} input + {$output} output = {$tokens} provider tokens";
}

function mg_merchant_ai_report_alert_body(array $row): string
{
    $context = mg_merchant_ai_report_context($row['context_json'] ?? []);
    $parts = [mg_merchant_ai_report_time($row['created_at'] ?? '')];
    $severity = strtoupper(trim((string)($row['severity'] ?? 'warning')));
    if ($severity !== '') $parts[] = $severity;
    $message = trim((string)($row['message'] ?? 'Accounting alert recorded.'));
    if ($message !== '') $parts[] = $message;
    foreach (['response_id'=>'Response', 'source_type'=>'Source', 'phase'=>'Phase', 'exception_type'=>'Exception'] as $key => $label) {
        $value = mg_merchant_ai_report_context_value($context, $key);
        if ($value !== '') $parts[] = $label . ': ' . $value;
    }
    return implode(' • ', $parts);
}

function mg_merchant_ai_report_blocks(array $report, string $mode): array
{
    $days = (int)($report['window_days'] ?? 30);
    $credits = is_array($report['ai_credits'] ?? null) ? $report['ai_credits'] : [];
    $aiStatus = is_array($report['ai_status'] ?? null) ? $report['ai_status'] : [];
    $ledger = is_array($report['ledger'] ?? null) ? $report['ledger'] : [];
    $provider = is_array($report['provider_usage'] ?? null) ? $report['provider_usage'] : [];
    $alerts = is_array($report['accounting_alerts'] ?? null) ? $report['accounting_alerts'] : [];
    $difference = (int)($report['token_difference'] ?? 0);
    $balanced = !empty($report['balanced']);
    $schemaReady = !empty($report['schema_ready']);
    $blocks = [];

    if ($mode === 'summary') {
        $blocks[] = [
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
        ];
        $blocks[] = [
            'type'=>'metric_grid',
            'title'=>'Merchant Agent ledger reconciliation',
            'body'=>'Compares completed Merchant Agent provider usage records with recorded owner-credit ledger debits.',
            'metrics'=>[
                ['label'=>'Completed responses','value'=>number_format((int)($provider['completed_count'] ?? 0))],
                ['label'=>'Ledger debits','value'=>number_format((int)($ledger['debit_count'] ?? 0))],
                ['label'=>'Provider tokens','value'=>number_format((int)($provider['completed_tokens'] ?? 0))],
                ['label'=>'Debited tokens','value'=>number_format((int)($ledger['debited_tokens'] ?? 0))],
                ['label'=>'Token difference','value'=>number_format($difference)],
                ['label'=>'Accounting alerts','value'=>number_format((int)($alerts['total'] ?? 0))],
            ],
        ];
        $sourceChart = [];
        foreach (($report['usage_by_source'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $sourceChart[] = [
                'label'=>mg_merchant_ai_report_source_label((string)($row['source_type'] ?? '')),
                'value'=>(int)($row['tokens'] ?? 0),
            ];
        }
        if ($sourceChart !== []) {
            $blocks[] = [
                'type'=>'chart',
                'title'=>'AI usage by Merchant Agent source',
                'body'=>"Recorded ledger token usage during the last {$days} days.",
                'chart_type'=>'bar',
                'data'=>$sourceChart,
            ];
        }
    } elseif ($mode === 'details') {
        $blocks[] = [
            'type'=>'metric_grid',
            'title'=>'AI reconciliation details',
            'body'=>"Exact recent provider and ledger records for the last {$days} days. This is a database-only report.",
            'metrics'=>[
                ['label'=>'Provider records','value'=>number_format((int)($provider['completed_count'] ?? 0))],
                ['label'=>'Ledger debits','value'=>number_format((int)($ledger['debit_count'] ?? 0))],
                ['label'=>'Provider tokens','value'=>number_format((int)($provider['completed_tokens'] ?? 0))],
                ['label'=>'Debited tokens','value'=>number_format((int)($ledger['debited_tokens'] ?? 0))],
                ['label'=>'Difference','value'=>number_format($difference)],
                ['label'=>'Missing refs','value'=>number_format((int)($ledger['missing_references'] ?? 0))],
            ],
        ];
        $providerRows = array_slice(is_array($report['provider_records'] ?? null) ? $report['provider_records'] : [], 0, 2);
        foreach ($providerRows as $index => $row) {
            if (!is_array($row)) continue;
            $blocks[] = [
                'type'=>'insight',
                'title'=>'Provider record ' . ($index + 1),
                'body'=>mg_merchant_ai_report_provider_body($row),
            ];
        }
        $ledgerRows = array_slice(is_array($report['recent_ledger'] ?? null) ? $report['recent_ledger'] : [], 0, 2);
        foreach ($ledgerRows as $index => $row) {
            if (!is_array($row)) continue;
            $blocks[] = [
                'type'=>trim((string)($row['source_reference'] ?? '')) === '' ? 'warning' : 'insight',
                'title'=>'Ledger debit ' . ($index + 1),
                'body'=>mg_merchant_ai_report_ledger_body($row),
            ];
        }
    } elseif ($mode === 'alerts') {
        $metrics = [['label'=>'Total alerts','value'=>number_format((int)($alerts['total'] ?? 0))]];
        foreach (array_slice(is_array($alerts['by_type'] ?? null) ? $alerts['by_type'] : [], 0, 5) as $row) {
            if (!is_array($row)) continue;
            $metrics[] = [
                'label'=>mg_merchant_ai_report_source_label((string)($row['event_type'] ?? 'alert')),
                'value'=>number_format((int)($row['total'] ?? 0)),
            ];
        }
        $blocks[] = [
            'type'=>'metric_grid',
            'title'=>'AI accounting alerts',
            'body'=>"Merchant Agent accounting security events recorded during the last {$days} days.",
            'metrics'=>$metrics,
        ];
        $alertRows = array_slice(is_array($report['recent_alerts'] ?? null) ? $report['recent_alerts'] : [], 0, 4);
        if ($alertRows === []) {
            $blocks[] = [
                'type'=>'insight',
                'title'=>'No accounting alerts found',
                'body'=>"No Merchant Agent debit, preflight-state, or accounting-context alerts were recorded during the last {$days} days.",
            ];
        } else {
            foreach ($alertRows as $row) {
                if (!is_array($row)) continue;
                $blocks[] = [
                    'type'=>'warning',
                    'title'=>(string)($row['event_type'] ?? 'Merchant Agent accounting alert'),
                    'body'=>mg_merchant_ai_report_alert_body($row),
                ];
            }
        }
    } elseif ($mode === 'recent') {
        $recent = is_array($report['recent_ledger'] ?? null) ? $report['recent_ledger'] : [];
        $blocks[] = [
            'type'=>'metric_grid',
            'title'=>'Recent Merchant Agent AI usage',
            'body'=>'The newest owner-credit ledger debits, including source, model, tokens, and provider response reference.',
            'metrics'=>[
                ['label'=>'Recent entries shown','value'=>number_format(min(4, count($recent)))],
                ['label'=>'Window','value'=>$days . ' days'],
                ['label'=>'Total debits','value'=>number_format((int)($ledger['debit_count'] ?? 0))],
                ['label'=>'Debited tokens','value'=>number_format((int)($ledger['debited_tokens'] ?? 0))],
                ['label'=>'Missing refs','value'=>number_format((int)($ledger['missing_references'] ?? 0))],
                ['label'=>'Balance','value'=>$balanced ? 'Balanced' : 'Review'],
            ],
        ];
        if ($recent === []) {
            $blocks[] = [
                'type'=>'insight',
                'title'=>'No recent Merchant Agent AI usage',
                'body'=>"No Merchant Agent owner-credit debits were recorded during the last {$days} days.",
            ];
        } else {
            foreach (array_slice($recent, 0, 4) as $row) {
                if (!is_array($row)) continue;
                $blocks[] = [
                    'type'=>trim((string)($row['source_reference'] ?? '')) === '' ? 'warning' : 'insight',
                    'title'=>mg_merchant_ai_report_source_label((string)($row['source_type'] ?? 'AI usage')),
                    'body'=>mg_merchant_ai_report_ledger_body($row),
                ];
            }
        }
    }

    if ($mode !== 'alerts') {
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
    }

    return mg_agent_chat_normalize_blocks($blocks);
}

function mg_merchant_ai_report_build(PDO $pdo, array $user, array $packageContext, int $days = 30, string $mode = 'summary'): array
{
    $userId = (int)($user['id'] ?? 0);
    $days = max(7, min(365, $days));
    $mode = in_array($mode, ['summary','details','alerts','recent'], true) ? $mode : 'summary';
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
    $providerRecords = [];
    $recentAlerts = [];

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
            "SELECT l.public_id,l.source_type,l.source_reference,l.input_tokens,l.output_tokens,
                    ABS(l.token_delta) tokens,l.created_at,
                    COALESCE(m.display_name,m.model_key,'Unknown model') model_name,
                    COALESCE(m.model_key,'') model_key
             FROM ai_credit_ledger l
             LEFT JOIN ai_models m ON m.id=l.model_id
             WHERE l.user_id=? AND l.provider_key='anthropic' AND l.entry_type='usage_debit'
               AND LEFT(l.source_type,15)='merchant_agent_'
               AND l.created_at>={$windowSql}
             ORDER BY l.id DESC LIMIT 8",
            [$userId],
            8
        );

        $providerRecords = mg_merchant_ai_report_rows(
            $pdo,
            "SELECT e.id,e.request_status,e.input_tokens,e.output_tokens,e.metadata_json,e.created_at,
                    COALESCE(m.display_name,m.model_key,'Unknown model') model_name,
                    COALESCE(m.model_key,'') model_key
             FROM ai_usage_events e
             INNER JOIN ai_providers p ON p.id=e.provider_id
             LEFT JOIN ai_models m ON m.id=e.model_id
             WHERE e.user_id=? AND p.provider_key='anthropic'
               AND e.created_at>={$windowSql}
               AND e.request_status IN ('completed','failed')
               AND e.metadata_json REGEXP '\"source\"[[:space:]]*:[[:space:]]*\"merchant_agent_'
             ORDER BY e.id DESC LIMIT 8",
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
            $recentAlerts = mg_merchant_ai_report_rows(
                $pdo,
                "SELECT id,severity,event_type,request_id,message,context_json,created_at
                 FROM security_logs
                 WHERE user_id=? AND created_at>={$windowSql}
                   AND event_type IN (
                     'merchant_agent.ai_credit_debit_failed',
                     'merchant_agent.ai_preflight_state_missing',
                     'merchant_agent.ai_call_context_missing'
                   )
                 ORDER BY id DESC LIMIT 8",
                [$userId],
                8
            );
        }
    }

    $difference = $provider['completed_tokens'] - $ledger['debited_tokens'];
    $balanced = $schemaReady
        && $difference === 0
        && $ledger['missing_references'] === 0
        && $alerts['total'] === 0;

    $report = [
        'generated_at'=>gmdate('c'),
        'window_days'=>$days,
        'mode'=>$mode,
        'database_only'=>true,
        'used_ai'=>false,
        'schema_ready'=>$schemaReady,
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
        'provider_records'=>array_map(static fn(array $row): array => [
            'id'=>(int)($row['id'] ?? 0),
            'request_status'=>(string)($row['request_status'] ?? ''),
            'input_tokens'=>(int)($row['input_tokens'] ?? 0),
            'output_tokens'=>(int)($row['output_tokens'] ?? 0),
            'metadata_json'=>(string)($row['metadata_json'] ?? ''),
            'model_name'=>(string)($row['model_name'] ?? ''),
            'model_key'=>(string)($row['model_key'] ?? ''),
            'created_at'=>(string)($row['created_at'] ?? ''),
        ], $providerRecords),
        'recent_ledger'=>array_map(static fn(array $row): array => [
            'id'=>(string)($row['public_id'] ?? ''),
            'source_type'=>(string)($row['source_type'] ?? ''),
            'source_reference'=>(string)($row['source_reference'] ?? ''),
            'input_tokens'=>(int)($row['input_tokens'] ?? 0),
            'output_tokens'=>(int)($row['output_tokens'] ?? 0),
            'tokens'=>(int)($row['tokens'] ?? 0),
            'model_name'=>(string)($row['model_name'] ?? ''),
            'model_key'=>(string)($row['model_key'] ?? ''),
            'created_at'=>(string)($row['created_at'] ?? ''),
        ], $recentLedger),
        'recent_alerts'=>array_map(static fn(array $row): array => [
            'id'=>(int)($row['id'] ?? 0),
            'severity'=>(string)($row['severity'] ?? ''),
            'event_type'=>(string)($row['event_type'] ?? ''),
            'request_id'=>(string)($row['request_id'] ?? ''),
            'message'=>(string)($row['message'] ?? ''),
            'context_json'=>(string)($row['context_json'] ?? ''),
            'created_at'=>(string)($row['created_at'] ?? ''),
        ], $recentAlerts),
        'privacy'=>[
            'merchant_owner_scoped'=>true,
            'external_ai_called'=>false,
            'customer_details_included'=>false,
            'payment_credentials_included'=>false,
        ],
    ];
    $report['blocks'] = mg_merchant_ai_report_blocks($report, $mode);
    return $report;
}

function mg_merchant_ai_report_chat_response(PDO $pdo, array $user, array $packageContext, array $input): array
{
    $merchantId = (int)($user['id'] ?? 0);
    $message = mg_ai_chat_clean($input['message'] ?? 'AI Report', 2000) ?: 'AI Report';
    $mode = mg_merchant_ai_report_mode($message);
    $days = mg_merchant_ai_report_days($message, (int)($input['days'] ?? 30));
    $thread = mg_agent_thread_by_id($pdo, $merchantId, mg_ai_chat_clean($input['thread_id'] ?? '', 80));
    $threadId = (string)($thread['id'] ?? '');
    $report = mg_merchant_ai_report_build($pdo, $user, $packageContext, $days, $mode);
    if ($mode === 'alerts') {
        $reply = (int)($report['accounting_alerts']['total'] ?? 0) > 0
            ? "Your Merchant Agent AI report found accounting alerts during the last {$days} days. The exact alert records are shown below without using AI credits."
            : "No Merchant Agent AI accounting alerts were found during the last {$days} days. This database report did not consume AI credits.";
    } elseif ($mode === 'recent') {
        $reply = "Here are your most recent Merchant Agent AI ledger transactions. This database report did not consume AI credits.";
    } elseif ($mode === 'details') {
        $reply = "Here are the Merchant Agent AI provider and ledger reconciliation details for the last {$days} days. This database report did not consume AI credits.";
    } else {
        $reply = !empty($report['balanced'])
            ? "Your Merchant Agent AI ledger is balanced for the last {$days} days. This report used only Microgifter's database and did not consume AI credits."
            : "Your Merchant Agent AI report found an accounting item that needs review for the last {$days} days. This database report did not consume AI credits.";
    }
    $meta = [
        'scope'=>'ai_report',
        'thread_public_id'=>$threadId,
        'skills'=>[],
        'source'=>'merchant_ai_credit_report',
        'database_only'=>true,
        'used_ai'=>false,
        'window_days'=>$days,
        'report_mode'=>$mode,
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
            $meta + ['blocks'=>$report['blocks'],'model'=>'database-ai-report-v2']
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
            'model'=>'database-ai-report-v2',
            'created_at'=>gmdate('c'),
        ],
        'state'=>mg_ai_chat_public_state($pdo, $merchantId),
    ];
}
