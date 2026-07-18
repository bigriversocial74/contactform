<?php
declare(strict_types=1);

require_once __DIR__ . '/user-credit-service.php';

function mg_ai_reconciliation_schema_ready(PDO $pdo): bool
{
    foreach (['ai_credit_provider_responses','ai_credit_reconciliation_runs','ai_credit_reconciliation_incidents','ai_credit_reconciliation_actions'] as $table) {
        if (!mg_ai_credit_table_exists($pdo, $table)) return false;
    }
    return mg_ai_credit_schema_ready($pdo);
}

function mg_ai_reconciliation_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_ai_reconciliation_text(mixed $value, int $max = 1000): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return mb_substr($text, 0, $max);
}

function mg_ai_reconciliation_incident_key(string $type, int $userId, string $reference): string
{
    return hash('sha256', strtolower(trim($type)) . '|' . $userId . '|' . trim($reference));
}

function mg_ai_reconciliation_source(array $metadata, string $fallback = 'merchant_agent_unknown'): string
{
    $source = mg_ai_reconciliation_text($metadata['source'] ?? $metadata['source_type'] ?? $fallback, 80);
    return str_starts_with($source, 'merchant_agent_') ? $source : $fallback;
}

function mg_ai_reconciliation_response_reference(array $metadata): string
{
    foreach (['anthropic_response_id','response_id','provider_response_id','response_reference'] as $key) {
        $value = mg_ai_reconciliation_text($metadata[$key] ?? '', 190);
        if ($value !== '') return $value;
    }
    return '';
}

function mg_ai_reconciliation_severity(string $type, int $difference = 0): string
{
    if (in_array($type, ['credit_debit_failed','call_context_missing'], true)) return 'critical';
    if (in_array($type, ['provider_without_ledger','token_mismatch','preflight_state_missing'], true)) return abs($difference) >= 10000 ? 'critical' : 'high';
    if ($type === 'missing_response_reference') return 'medium';
    return 'low';
}

function mg_ai_reconciliation_action(PDO $pdo, int $incidentId, string $actionType, ?int $adminUserId = null, string $note = '', array $metadata = []): void
{
    $stmt = $pdo->prepare('INSERT INTO ai_credit_reconciliation_actions (public_id,incident_id,action_type,admin_user_id,note,metadata_json,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_ai_credit_uuid(),
        $incidentId,
        mg_ai_reconciliation_text($actionType, 60),
        $adminUserId,
        ($clean = mg_ai_reconciliation_text($note, 1000)) !== '' ? $clean : null,
        $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function mg_ai_reconciliation_capture_provider_response(PDO $pdo, int $userId, string $providerKey, string $modelKey, string $sourceType, array $response, array $metadata = []): array
{
    $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
    $inputTokens = max(0, (int)($usage['input_tokens'] ?? 0));
    $outputTokens = max(0, (int)($usage['output_tokens'] ?? 0));
    $reference = mg_ai_reconciliation_text($response['id'] ?? '', 190);
    if ($reference === '') {
        $reference = 'merchant-agent-' . substr(hash('sha256', json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: microtime(true)), 0, 40);
    }
    if (!mg_ai_reconciliation_schema_ready($pdo)) {
        return ['response_reference'=>$reference,'captured'=>false];
    }
    $providerKey = mg_ai_credit_provider_key($providerKey);
    $sourceType = mg_ai_reconciliation_text($sourceType, 80) ?: 'merchant_agent_unknown';
    $stmt = $pdo->prepare('SELECT m.id model_id,m.provider_id FROM ai_models m INNER JOIN ai_providers p ON p.id=m.provider_id WHERE m.model_key=? AND p.provider_key=? LIMIT 1');
    $stmt->execute([$modelKey, $providerKey]);
    $model = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $storedMetadata = $metadata + [
        'model_key'=>$modelKey,
        'anthropic_response_id'=>$reference,
        'merchant_agent_owner_only'=>true,
    ];
    $stmt = $pdo->prepare("INSERT INTO ai_credit_provider_responses (public_id,response_reference,user_id,provider_key,provider_id,model_id,source_type,input_tokens,output_tokens,total_tokens,accounting_status,metadata_json,completed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'pending',?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE source_type=VALUES(source_type),provider_id=VALUES(provider_id),model_id=VALUES(model_id),input_tokens=VALUES(input_tokens),output_tokens=VALUES(output_tokens),total_tokens=VALUES(total_tokens),metadata_json=VALUES(metadata_json),completed_at=VALUES(completed_at),updated_at=NOW()");
    $stmt->execute([
        mg_ai_credit_uuid(), $reference, $userId, $providerKey,
        $model['provider_id'] ?? null, $model['model_id'] ?? null, $sourceType,
        $inputTokens, $outputTokens, $inputTokens + $outputTokens,
        json_encode($storedMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    $stmt = $pdo->prepare('SELECT id,public_id,response_reference,provider_id,model_id,input_tokens,output_tokens,total_tokens,accounting_status FROM ai_credit_provider_responses WHERE user_id=? AND provider_key=? AND response_reference=? LIMIT 1');
    $stmt->execute([$userId, $providerKey, $reference]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $row['captured'] = true;
    return $row;
}

function mg_ai_reconciliation_mark_provider_accounting(PDO $pdo, int $userId, string $providerKey, string $responseReference, bool $accounted, array $metadata = []): void
{
    if (!mg_ai_reconciliation_schema_ready($pdo)) return;
    $providerKey = mg_ai_credit_provider_key($providerKey);
    $ledgerId = null;
    if ($accounted) {
        $stmt = $pdo->prepare("SELECT id FROM ai_credit_ledger WHERE user_id=? AND provider_key=? AND entry_type='usage_debit' AND source_reference=? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $providerKey, $responseReference]);
        $ledgerId = $stmt->fetchColumn() ?: null;
    }
    $status = $accounted ? 'accounted' : 'failed';
    $stmt = $pdo->prepare('UPDATE ai_credit_provider_responses SET accounting_status=?,ledger_entry_id=?,accounted_at=IF(?="accounted",NOW(),accounted_at),metadata_json=JSON_MERGE_PATCH(COALESCE(metadata_json,JSON_OBJECT()),?),updated_at=NOW() WHERE user_id=? AND provider_key=? AND response_reference=?');
    $stmt->execute([
        $status,
        $ledgerId !== null ? (int)$ledgerId : null,
        $status,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $userId,
        $providerKey,
        $responseReference,
    ]);
}

function mg_ai_reconciliation_upsert_incident(PDO $pdo, int $runId, array $incident): array
{
    $type = mg_ai_reconciliation_text($incident['incident_type'] ?? '', 80);
    $userId = (int)($incident['user_id'] ?? 0);
    $reference = mg_ai_reconciliation_text($incident['key_reference'] ?? $incident['source_reference'] ?? $type, 500);
    $key = mg_ai_reconciliation_incident_key($type, $userId, $reference);
    $stmt = $pdo->prepare('SELECT id,public_id,status FROM ai_credit_reconciliation_incidents WHERE incident_key=? LIMIT 1');
    $stmt->execute([$key]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    $evidence = is_array($incident['evidence'] ?? null) ? $incident['evidence'] : [];
    $severity = $incident['severity'] ?? mg_ai_reconciliation_severity($type, (int)($incident['token_difference'] ?? 0));
    if (!$existing) {
        $publicId = mg_ai_credit_uuid();
        $stmt = $pdo->prepare("INSERT INTO ai_credit_reconciliation_incidents (public_id,incident_key,user_id,provider_key,incident_type,severity,status,source_type,source_reference,provider_response_id,provider_usage_event_id,ledger_entry_id,model_id,provider_tokens,debited_tokens,token_difference,evidence_json,first_detected_at,last_detected_at,occurrence_count,last_run_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'open',?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),1,?,NOW(),NOW())");
        $stmt->execute([
            $publicId, $key, $userId, mg_ai_credit_provider_key($incident['provider_key'] ?? 'anthropic'), $type, $severity,
            ($source = mg_ai_reconciliation_text($incident['source_type'] ?? '', 80)) !== '' ? $source : null,
            ($sourceRef = mg_ai_reconciliation_text($incident['source_reference'] ?? '', 190)) !== '' ? $sourceRef : null,
            !empty($incident['provider_response_id']) ? (int)$incident['provider_response_id'] : null,
            !empty($incident['provider_usage_event_id']) ? (int)$incident['provider_usage_event_id'] : null,
            !empty($incident['ledger_entry_id']) ? (int)$incident['ledger_entry_id'] : null,
            !empty($incident['model_id']) ? (int)$incident['model_id'] : null,
            max(0, (int)($incident['provider_tokens'] ?? 0)),
            max(0, (int)($incident['debited_tokens'] ?? 0)),
            (int)($incident['token_difference'] ?? 0),
            json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $runId,
        ]);
        $id = (int)$pdo->lastInsertId();
        mg_ai_reconciliation_action($pdo, $id, 'detected', null, 'Automated reconciliation detected this accounting incident.', ['run_id'=>$runId,'incident_type'=>$type]);
        return ['id'=>$id,'public_id'=>$publicId,'incident_key'=>$key,'created'=>true,'reopened'=>false];
    }
    $reopen = in_array((string)$existing['status'], ['resolved','dismissed'], true);
    $stmt = $pdo->prepare("UPDATE ai_credit_reconciliation_incidents SET severity=?,status=IF(status IN ('resolved','dismissed'),'open',status),source_type=?,source_reference=?,provider_response_id=?,provider_usage_event_id=?,ledger_entry_id=?,model_id=?,provider_tokens=?,debited_tokens=?,token_difference=?,evidence_json=?,last_detected_at=NOW(),occurrence_count=occurrence_count+1,resolution_note=IF(status IN ('resolved','dismissed'),NULL,resolution_note),resolved_by_user_id=IF(status IN ('resolved','dismissed'),NULL,resolved_by_user_id),resolved_at=IF(status IN ('resolved','dismissed'),NULL,resolved_at),dismissed_at=IF(status IN ('resolved','dismissed'),NULL,dismissed_at),last_run_id=?,updated_at=NOW() WHERE id=?");
    $stmt->execute([
        $severity,
        ($source = mg_ai_reconciliation_text($incident['source_type'] ?? '', 80)) !== '' ? $source : null,
        ($sourceRef = mg_ai_reconciliation_text($incident['source_reference'] ?? '', 190)) !== '' ? $sourceRef : null,
        !empty($incident['provider_response_id']) ? (int)$incident['provider_response_id'] : null,
        !empty($incident['provider_usage_event_id']) ? (int)$incident['provider_usage_event_id'] : null,
        !empty($incident['ledger_entry_id']) ? (int)$incident['ledger_entry_id'] : null,
        !empty($incident['model_id']) ? (int)$incident['model_id'] : null,
        max(0, (int)($incident['provider_tokens'] ?? 0)),
        max(0, (int)($incident['debited_tokens'] ?? 0)),
        (int)($incident['token_difference'] ?? 0),
        json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $runId,
        (int)$existing['id'],
    ]);
    mg_ai_reconciliation_action($pdo, (int)$existing['id'], $reopen ? 'reopened_by_detection' : 'refreshed', null, $reopen ? 'The accounting issue was detected again and reopened.' : 'Automated reconciliation refreshed the incident evidence.', ['run_id'=>$runId]);
    return ['id'=>(int)$existing['id'],'public_id'=>(string)$existing['public_id'],'incident_key'=>$key,'created'=>false,'reopened'=>$reopen];
}

function mg_ai_reconciliation_scan_user(PDO $pdo, int $runId, int $userId, string $providerKey, int $days): array
{
    $windowSql = 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . max(1, min(365, $days)) . ' DAY)';
    $stats = ['provider_events'=>0,'ledger_entries'=>0,'created'=>0,'updated'=>0,'auto_resolved'=>0,'token_difference'=>0];
    $detected = [];

    $stmt = $pdo->prepare("SELECT r.*,m.model_key,m.display_name model_name FROM ai_credit_provider_responses r LEFT JOIN ai_models m ON m.id=r.model_id WHERE r.user_id=? AND r.provider_key=? AND r.completed_at>={$windowSql} ORDER BY r.id");
    $stmt->execute([$userId, $providerKey]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $stats['provider_events'] += count($responses);

    $stmt = $pdo->prepare("SELECT l.*,ABS(l.token_delta) debited_tokens,m.model_key,m.display_name model_name FROM ai_credit_ledger l LEFT JOIN ai_models m ON m.id=l.model_id WHERE l.user_id=? AND l.provider_key=? AND l.entry_type='usage_debit' AND LEFT(l.source_type,15)='merchant_agent_' AND l.created_at>={$windowSql} ORDER BY l.id");
    $stmt->execute([$userId, $providerKey]);
    $ledgerRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $stats['ledger_entries'] += count($ledgerRows);
    $ledgerByReference = [];
    foreach ($ledgerRows as $ledger) {
        $ref = trim((string)($ledger['source_reference'] ?? ''));
        if ($ref !== '') $ledgerByReference[$ref] = $ledger;
        if ($ref === '') {
            $result = mg_ai_reconciliation_upsert_incident($pdo, $runId, [
                'user_id'=>$userId,'provider_key'=>$providerKey,'incident_type'=>'missing_response_reference',
                'key_reference'=>(string)$ledger['public_id'],'source_type'=>(string)$ledger['source_type'],
                'ledger_entry_id'=>(int)$ledger['id'],'model_id'=>$ledger['model_id'],
                'debited_tokens'=>(int)$ledger['debited_tokens'],'token_difference'=>(int)$ledger['debited_tokens'],
                'evidence'=>['ledger_public_id'=>(string)$ledger['public_id'],'created_at'=>(string)$ledger['created_at'],'input_tokens'=>(int)$ledger['input_tokens'],'output_tokens'=>(int)$ledger['output_tokens']],
            ]);
            $detected[$result['incident_key']] = true;
            $result['created'] ? $stats['created']++ : $stats['updated']++;
        }
    }

    $responseReferences = [];
    foreach ($responses as $response) {
        $reference = (string)$response['response_reference'];
        $responseReferences[$reference] = true;
        $providerTokens = (int)$response['total_tokens'];
        $ledger = $ledgerByReference[$reference] ?? null;
        if (!$ledger) {
            $result = mg_ai_reconciliation_upsert_incident($pdo, $runId, [
                'user_id'=>$userId,'provider_key'=>$providerKey,'incident_type'=>'provider_without_ledger',
                'key_reference'=>$reference,'source_type'=>(string)$response['source_type'],'source_reference'=>$reference,
                'provider_response_id'=>(int)$response['id'],'model_id'=>$response['model_id'],
                'provider_tokens'=>$providerTokens,'token_difference'=>$providerTokens,
                'evidence'=>['provider_response_public_id'=>(string)$response['public_id'],'accounting_status'=>(string)$response['accounting_status'],'completed_at'=>(string)$response['completed_at'],'model_key'=>(string)($response['model_key'] ?? ''),'input_tokens'=>(int)$response['input_tokens'],'output_tokens'=>(int)$response['output_tokens']],
            ]);
            $detected[$result['incident_key']] = true;
            $result['created'] ? $stats['created']++ : $stats['updated']++;
            continue;
        }
        $debited = (int)$ledger['debited_tokens'];
        if ($providerTokens !== $debited) {
            $difference = $providerTokens - $debited;
            $result = mg_ai_reconciliation_upsert_incident($pdo, $runId, [
                'user_id'=>$userId,'provider_key'=>$providerKey,'incident_type'=>'token_mismatch',
                'key_reference'=>$reference,'source_type'=>(string)$response['source_type'],'source_reference'=>$reference,
                'provider_response_id'=>(int)$response['id'],'ledger_entry_id'=>(int)$ledger['id'],'model_id'=>$response['model_id'],
                'provider_tokens'=>$providerTokens,'debited_tokens'=>$debited,'token_difference'=>$difference,
                'evidence'=>['provider_response_public_id'=>(string)$response['public_id'],'ledger_public_id'=>(string)$ledger['public_id'],'completed_at'=>(string)$response['completed_at'],'ledger_created_at'=>(string)$ledger['created_at']],
            ]);
            $detected[$result['incident_key']] = true;
            $result['created'] ? $stats['created']++ : $stats['updated']++;
        }
    }

    if ($responses !== []) {
        $firstResponseAt = min(array_map(static fn(array $row): string => (string)$row['created_at'], $responses));
        foreach ($ledgerRows as $ledger) {
            $reference = trim((string)($ledger['source_reference'] ?? ''));
            if ($reference === '' || isset($responseReferences[$reference]) || (string)$ledger['created_at'] < $firstResponseAt) continue;
            $result = mg_ai_reconciliation_upsert_incident($pdo, $runId, [
                'user_id'=>$userId,'provider_key'=>$providerKey,'incident_type'=>'ledger_without_provider',
                'key_reference'=>(string)$ledger['public_id'],'source_type'=>(string)$ledger['source_type'],'source_reference'=>$reference,
                'ledger_entry_id'=>(int)$ledger['id'],'model_id'=>$ledger['model_id'],'debited_tokens'=>(int)$ledger['debited_tokens'],'token_difference'=>(int)$ledger['debited_tokens'] * -1,
                'evidence'=>['ledger_public_id'=>(string)$ledger['public_id'],'created_at'=>(string)$ledger['created_at'],'response_reference'=>$reference],
            ]);
            $detected[$result['incident_key']] = true;
            $result['created'] ? $stats['created']++ : $stats['updated']++;
        }
    }

    $providerTotals = ['tokens'=>0,'events'=>0];
    $stmt = $pdo->prepare("SELECT COUNT(*) events,COALESCE(SUM(input_tokens+output_tokens),0) tokens FROM ai_usage_events e INNER JOIN ai_providers p ON p.id=e.provider_id WHERE e.user_id=? AND p.provider_key=? AND e.request_status='completed' AND e.created_at>={$windowSql} AND e.metadata_json REGEXP '\"source\"[[:space:]]*:[[:space:]]*\"merchant_agent_'");
    $stmt->execute([$userId, $providerKey]);
    $providerTotals = $stmt->fetch(PDO::FETCH_ASSOC) ?: $providerTotals;
    $ledgerTotal = array_sum(array_map(static fn(array $row): int => (int)$row['debited_tokens'], $ledgerRows));
    $aggregateDifference = (int)$providerTotals['tokens'] - $ledgerTotal;
    $stats['provider_events'] += max(0, (int)$providerTotals['events'] - count($responses));
    $stats['token_difference'] = $aggregateDifference;
    if ($aggregateDifference !== 0) {
        $result = mg_ai_reconciliation_upsert_incident($pdo, $runId, [
            'user_id'=>$userId,'provider_key'=>$providerKey,'incident_type'=>'token_mismatch',
            'key_reference'=>'aggregate','provider_tokens'=>(int)$providerTotals['tokens'],'debited_tokens'=>$ledgerTotal,'token_difference'=>$aggregateDifference,
            'evidence'=>['window_days'=>$days,'provider_completed_events'=>(int)$providerTotals['events'],'durable_provider_responses'=>count($responses),'ledger_debits'=>count($ledgerRows),'aggregate'=>true],
        ]);
        $detected[$result['incident_key']] = true;
        $result['created'] ? $stats['created']++ : $stats['updated']++;
    }

    if (mg_ai_credit_table_exists($pdo, 'security_logs')) {
        $stmt = $pdo->prepare("SELECT id,severity,event_type,message,context_json,created_at FROM security_logs WHERE user_id=? AND created_at>={$windowSql} AND event_type IN ('merchant_agent.ai_credit_debit_failed','merchant_agent.ai_preflight_state_missing','merchant_agent.ai_call_context_missing') ORDER BY id");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $log) {
            $type = match ((string)$log['event_type']) {
                'merchant_agent.ai_credit_debit_failed' => 'credit_debit_failed',
                'merchant_agent.ai_preflight_state_missing' => 'preflight_state_missing',
                default => 'call_context_missing',
            };
            $context = mg_ai_reconciliation_json($log['context_json'] ?? null);
            $reference = mg_ai_reconciliation_response_reference($context);
            $result = mg_ai_reconciliation_upsert_incident($pdo, $runId, [
                'user_id'=>$userId,'provider_key'=>$providerKey,'incident_type'=>$type,
                'key_reference'=>'security:' . (int)$log['id'],'source_type'=>mg_ai_reconciliation_source($context),
                'source_reference'=>$reference,'token_difference'=>0,
                'evidence'=>['security_log_id'=>(int)$log['id'],'severity'=>(string)$log['severity'],'event_type'=>(string)$log['event_type'],'message'=>(string)$log['message'],'context'=>$context,'created_at'=>(string)$log['created_at']],
            ]);
            $detected[$result['incident_key']] = true;
            $result['created'] ? $stats['created']++ : $stats['updated']++;
        }
    }

    $autoTypes = ['provider_without_ledger','ledger_without_provider','token_mismatch','missing_response_reference'];
    $stmt = $pdo->prepare("SELECT id,incident_key FROM ai_credit_reconciliation_incidents WHERE user_id=? AND provider_key=? AND status IN ('open','under_review') AND incident_type IN ('provider_without_ledger','ledger_without_provider','token_mismatch','missing_response_reference')");
    $stmt->execute([$userId, $providerKey]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if (isset($detected[(string)$row['incident_key']])) continue;
        $pdo->prepare("UPDATE ai_credit_reconciliation_incidents SET status='resolved',resolution_note='Automatically resolved after reconciliation no longer detected the mismatch.',resolved_at=NOW(),dismissed_at=NULL,last_run_id=?,updated_at=NOW() WHERE id=?")->execute([$runId, (int)$row['id']]);
        mg_ai_reconciliation_action($pdo, (int)$row['id'], 'auto_resolved', null, 'Automated reconciliation confirmed the mismatch is no longer present.', ['run_id'=>$runId]);
        $stats['auto_resolved']++;
    }

    return $stats;
}

function mg_ai_reconciliation_run(PDO $pdo, array $options = []): array
{
    if (!mg_ai_reconciliation_schema_ready($pdo)) {
        throw new RuntimeException('Import database/20260718_ai_credit_reconciliation_incidents.sql before running AI credit reconciliation.');
    }
    $providerKey = mg_ai_credit_provider_key($options['provider_key'] ?? 'anthropic');
    $days = max(1, min(365, (int)($options['days'] ?? 30)));
    $trigger = mg_ai_reconciliation_text($options['trigger_source'] ?? 'scheduled', 40) ?: 'scheduled';
    $actorId = isset($options['initiated_by_user_id']) && (int)$options['initiated_by_user_id'] > 0 ? (int)$options['initiated_by_user_id'] : null;
    $specificUserId = isset($options['user_id']) && (int)$options['user_id'] > 0 ? (int)$options['user_id'] : null;
    $publicId = mg_ai_credit_uuid();
    $stmt = $pdo->prepare("INSERT INTO ai_credit_reconciliation_runs (public_id,trigger_source,provider_key,status,window_days,initiated_by_user_id,metadata_json,started_at,created_at) VALUES (?,?,?,'running',?,?,?,NOW(),NOW())");
    $stmt->execute([$publicId, $trigger, $providerKey, $days, $actorId, json_encode(['specific_user_id'=>$specificUserId], JSON_UNESCAPED_SLASHES)]);
    $runId = (int)$pdo->lastInsertId();
    $summary = ['id'=>$publicId,'status'=>'running','provider_key'=>$providerKey,'window_days'=>$days,'merchants_scanned'=>0,'provider_events_scanned'=>0,'ledger_entries_scanned'=>0,'incidents_created'=>0,'incidents_updated'=>0,'incidents_auto_resolved'=>0,'token_difference_total'=>0];
    try {
        if ($specificUserId !== null) {
            $userIds = [$specificUserId];
        } else {
            $windowSql = 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $days . ' DAY)';
            $sql = "SELECT DISTINCT user_id FROM (SELECT user_id FROM ai_credit_provider_responses WHERE provider_key=? AND completed_at>={$windowSql} UNION SELECT l.user_id FROM ai_credit_ledger l WHERE l.provider_key=? AND l.entry_type='usage_debit' AND LEFT(l.source_type,15)='merchant_agent_' AND l.created_at>={$windowSql} UNION SELECT e.user_id FROM ai_usage_events e INNER JOIN ai_providers p ON p.id=e.provider_id WHERE p.provider_key=? AND e.user_id IS NOT NULL AND e.created_at>={$windowSql} AND e.metadata_json REGEXP '\"source\"[[:space:]]*:[[:space:]]*\"merchant_agent_' UNION SELECT user_id FROM security_logs WHERE user_id IS NOT NULL AND created_at>={$windowSql} AND event_type IN ('merchant_agent.ai_credit_debit_failed','merchant_agent.ai_preflight_state_missing','merchant_agent.ai_call_context_missing')) candidates WHERE user_id IS NOT NULL ORDER BY user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$providerKey, $providerKey, $providerKey]);
            $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        foreach ($userIds as $userId) {
            if ($userId < 1) continue;
            $userStats = mg_ai_reconciliation_scan_user($pdo, $runId, $userId, $providerKey, $days);
            $summary['merchants_scanned']++;
            $summary['provider_events_scanned'] += $userStats['provider_events'];
            $summary['ledger_entries_scanned'] += $userStats['ledger_entries'];
            $summary['incidents_created'] += $userStats['created'];
            $summary['incidents_updated'] += $userStats['updated'];
            $summary['incidents_auto_resolved'] += $userStats['auto_resolved'];
            $summary['token_difference_total'] += $userStats['token_difference'];
        }
        $summary['status'] = 'completed';
        $stmt = $pdo->prepare("UPDATE ai_credit_reconciliation_runs SET status='completed',merchants_scanned=?,provider_events_scanned=?,ledger_entries_scanned=?,incidents_created=?,incidents_updated=?,incidents_auto_resolved=?,token_difference_total=?,completed_at=NOW() WHERE id=?");
        $stmt->execute([$summary['merchants_scanned'],$summary['provider_events_scanned'],$summary['ledger_entries_scanned'],$summary['incidents_created'],$summary['incidents_updated'],$summary['incidents_auto_resolved'],$summary['token_difference_total'],$runId]);
        if (function_exists('mg_security_log')) mg_security_log('info', 'ai_credit.reconciliation.completed', 'AI credit reconciliation completed.', $summary, $actorId);
    } catch (Throwable $error) {
        $summary['status'] = 'failed';
        $pdo->prepare("UPDATE ai_credit_reconciliation_runs SET status='failed',failure_message=?,completed_at=NOW() WHERE id=?")->execute([mb_substr($error->getMessage(), 0, 1000), $runId]);
        if (function_exists('mg_security_log')) mg_security_log('error', 'ai_credit.reconciliation.failed', 'AI credit reconciliation failed.', ['exception_class'=>$error::class,'run_id'=>$publicId], $actorId);
        throw $error;
    }
    return $summary;
}

function mg_ai_reconciliation_incident_row(PDO $pdo, string $publicId, bool $lock = false): array
{
    $publicId = mg_ai_reconciliation_text($publicId, 80);
    $sql = 'SELECT * FROM ai_credit_reconciliation_incidents WHERE public_id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('AI credit accounting incident not found.');
    return $row;
}

function mg_ai_reconciliation_user_incidents(PDO $pdo, int $userId, int $days = 30, int $limit = 8): array
{
    if (!mg_ai_reconciliation_schema_ready($pdo)) return [];
    $days = max(1, min(365, $days));
    $limit = max(1, min(25, $limit));
    $stmt = $pdo->prepare("SELECT public_id,incident_type,severity,status,source_type,source_reference,provider_tokens,debited_tokens,token_difference,evidence_json,first_detected_at,last_detected_at,occurrence_count,retry_count,resolution_note,resolved_at FROM ai_credit_reconciliation_incidents WHERE user_id=? AND last_detected_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$days} DAY) ORDER BY FIELD(status,'open','under_review','resolved','dismissed'),FIELD(severity,'critical','high','medium','low'),last_detected_at DESC LIMIT {$limit}");
    $stmt->execute([$userId]);
    return array_map(static function (array $row): array {
        $row['provider_tokens'] = (int)$row['provider_tokens'];
        $row['debited_tokens'] = (int)$row['debited_tokens'];
        $row['token_difference'] = (int)$row['token_difference'];
        $row['occurrence_count'] = (int)$row['occurrence_count'];
        $row['retry_count'] = (int)$row['retry_count'];
        $row['evidence'] = mg_ai_reconciliation_json($row['evidence_json'] ?? null);
        unset($row['evidence_json']);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_ai_reconciliation_queue(PDO $pdo, array $filters = []): array
{
    if (!mg_ai_reconciliation_schema_ready($pdo)) return ['schema_ready'=>false,'summary'=>[],'incidents'=>[],'last_run'=>null];
    $status = strtolower(trim((string)($filters['status'] ?? 'active')));
    $type = mg_ai_reconciliation_text($filters['incident_type'] ?? '', 80);
    $limit = max(1, min(200, (int)($filters['limit'] ?? 100)));
    $where = [];
    $params = [];
    if ($status === 'active') $where[] = "i.status IN ('open','under_review')";
    elseif (in_array($status, ['open','under_review','resolved','dismissed'], true)) { $where[] = 'i.status=?'; $params[] = $status; }
    if ($type !== '') { $where[] = 'i.incident_type=?'; $params[] = $type; }
    $sql = "SELECT i.*,u.email,u.display_name,u.full_name,m.model_key,m.display_name model_name,a.email assignee_email,a.display_name assignee_name,r.email resolver_email,r.display_name resolver_name FROM ai_credit_reconciliation_incidents i INNER JOIN users u ON u.id=i.user_id LEFT JOIN ai_models m ON m.id=i.model_id LEFT JOIN users a ON a.id=i.assigned_admin_user_id LEFT JOIN users r ON r.id=i.resolved_by_user_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY FIELD(i.status,'open','under_review','resolved','dismissed'),FIELD(i.severity,'critical','high','medium','low'),i.last_detected_at DESC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $incidents = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $actions = $pdo->prepare('SELECT public_id,action_type,admin_user_id,note,metadata_json,created_at FROM ai_credit_reconciliation_actions WHERE incident_id=? ORDER BY id DESC LIMIT 8');
        $actions->execute([(int)$row['id']]);
        $incidents[] = [
            'id'=>(string)$row['public_id'],'user_id'=>(int)$row['user_id'],'merchant'=>['email'=>(string)$row['email'],'name'=>(string)($row['display_name'] ?: $row['full_name'] ?: $row['email'])],
            'provider_key'=>(string)$row['provider_key'],'incident_type'=>(string)$row['incident_type'],'severity'=>(string)$row['severity'],'status'=>(string)$row['status'],
            'source_type'=>(string)($row['source_type'] ?? ''),'source_reference'=>(string)($row['source_reference'] ?? ''),'provider_tokens'=>(int)$row['provider_tokens'],'debited_tokens'=>(int)$row['debited_tokens'],'token_difference'=>(int)$row['token_difference'],
            'model'=>['key'=>(string)($row['model_key'] ?? ''),'name'=>(string)($row['model_name'] ?? '')],
            'evidence'=>mg_ai_reconciliation_json($row['evidence_json'] ?? null),'first_detected_at'=>(string)$row['first_detected_at'],'last_detected_at'=>(string)$row['last_detected_at'],'occurrence_count'=>(int)$row['occurrence_count'],
            'assigned_admin'=>$row['assigned_admin_user_id'] !== null ? ['id'=>(int)$row['assigned_admin_user_id'],'email'=>(string)$row['assignee_email'],'name'=>(string)($row['assignee_name'] ?: $row['assignee_email'])] : null,
            'resolution_note'=>(string)($row['resolution_note'] ?? ''),'retry_count'=>(int)$row['retry_count'],'last_retry_at'=>$row['last_retry_at'] !== null ? (string)$row['last_retry_at'] : null,'resolved_at'=>$row['resolved_at'] !== null ? (string)$row['resolved_at'] : null,
            'resolved_by'=>$row['resolved_by_user_id'] !== null ? ['id'=>(int)$row['resolved_by_user_id'],'email'=>(string)$row['resolver_email'],'name'=>(string)($row['resolver_name'] ?: $row['resolver_email'])] : null,
            'can_retry'=>in_array((string)$row['incident_type'], ['provider_without_ledger','credit_debit_failed'], true) && trim((string)($row['source_reference'] ?? '')) !== '',
            'actions'=>array_map(static function (array $action): array { $action['admin_user_id']=$action['admin_user_id']!==null?(int)$action['admin_user_id']:null;$action['metadata']=mg_ai_reconciliation_json($action['metadata_json']??null);unset($action['metadata_json']);return $action; }, $actions->fetchAll(PDO::FETCH_ASSOC) ?: []),
        ];
    }
    $summary = $pdo->query("SELECT COUNT(*) total,SUM(status='open') open_total,SUM(status='under_review') review_total,SUM(status='resolved') resolved_total,SUM(status='dismissed') dismissed_total,SUM(status IN ('open','under_review') AND severity='critical') critical_total,SUM(status IN ('open','under_review') AND severity='high') high_total,COALESCE(SUM(CASE WHEN status IN ('open','under_review') THEN ABS(token_difference) ELSE 0 END),0) active_token_difference FROM ai_credit_reconciliation_incidents")->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($summary as $key=>$value) $summary[$key]=(int)$value;
    $lastRun = $pdo->query('SELECT public_id,trigger_source,provider_key,status,window_days,merchants_scanned,provider_events_scanned,ledger_entries_scanned,incidents_created,incidents_updated,incidents_auto_resolved,token_difference_total,failure_message,started_at,completed_at FROM ai_credit_reconciliation_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
    return ['schema_ready'=>true,'summary'=>$summary,'incidents'=>$incidents,'last_run'=>$lastRun];
}

function mg_ai_reconciliation_apply_action(PDO $pdo, int $adminUserId, array $input): array
{
    $action = strtolower(trim((string)($input['action'] ?? 'under_review')));
    $allowed = ['assign','under_review','resolve','dismiss','reopen'];
    if (!in_array($action, $allowed, true)) throw new InvalidArgumentException('Invalid AI credit incident action.');
    $note = mg_ai_reconciliation_text($input['note'] ?? '', 1000);
    $pdo->beginTransaction();
    try {
        $incident = mg_ai_reconciliation_incident_row($pdo, (string)($input['incident_id'] ?? ''), true);
        $incidentId = (int)$incident['id'];
        if ($action === 'assign') {
            $assignee = max(1, (int)($input['assigned_admin_user_id'] ?? $adminUserId));
            $pdo->prepare("UPDATE ai_credit_reconciliation_incidents SET assigned_admin_user_id=?,status=IF(status='open','under_review',status),updated_at=NOW() WHERE id=?")->execute([$assignee,$incidentId]);
            mg_ai_reconciliation_action($pdo,$incidentId,'assigned',$adminUserId,$note ?: 'Incident assigned for accounting review.',['assigned_admin_user_id'=>$assignee]);
        } elseif ($action === 'under_review') {
            $pdo->prepare("UPDATE ai_credit_reconciliation_incidents SET status='under_review',assigned_admin_user_id=COALESCE(assigned_admin_user_id,?),resolution_note=NULL,resolved_by_user_id=NULL,resolved_at=NULL,dismissed_at=NULL,updated_at=NOW() WHERE id=?")->execute([$adminUserId,$incidentId]);
            mg_ai_reconciliation_action($pdo,$incidentId,'under_review',$adminUserId,$note ?: 'Incident moved under review.');
        } elseif ($action === 'resolve') {
            if ($note === '') throw new InvalidArgumentException('Resolution note is required.');
            $pdo->prepare("UPDATE ai_credit_reconciliation_incidents SET status='resolved',resolution_note=?,resolved_by_user_id=?,resolved_at=NOW(),dismissed_at=NULL,updated_at=NOW() WHERE id=?")->execute([$note,$adminUserId,$incidentId]);
            mg_ai_reconciliation_action($pdo,$incidentId,'resolved',$adminUserId,$note);
        } elseif ($action === 'dismiss') {
            if ($note === '') throw new InvalidArgumentException('Dismissal note is required.');
            $pdo->prepare("UPDATE ai_credit_reconciliation_incidents SET status='dismissed',resolution_note=?,resolved_by_user_id=?,dismissed_at=NOW(),resolved_at=NULL,updated_at=NOW() WHERE id=?")->execute([$note,$adminUserId,$incidentId]);
            mg_ai_reconciliation_action($pdo,$incidentId,'dismissed',$adminUserId,$note);
        } else {
            $pdo->prepare("UPDATE ai_credit_reconciliation_incidents SET status='open',resolution_note=NULL,resolved_by_user_id=NULL,resolved_at=NULL,dismissed_at=NULL,updated_at=NOW() WHERE id=?")->execute([$incidentId]);
            mg_ai_reconciliation_action($pdo,$incidentId,'reopened',$adminUserId,$note ?: 'Incident reopened for accounting review.');
        }
        $pdo->commit();
        if (function_exists('mg_audit')) mg_audit('admin_ai_credit_incident_' . $action, 'user', ['incident_id'=>(string)$incident['public_id'],'note'=>$note], $adminUserId);
        if (function_exists('mg_security_log')) mg_security_log('info', 'admin.ai_credit_incident.updated', 'AI credit accounting incident updated.', ['incident_id'=>(string)$incident['public_id'],'action'=>$action], $adminUserId);
        return ['incident_id'=>(string)$incident['public_id'],'action'=>$action];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_ai_reconciliation_retry_debit(PDO $pdo, int $adminUserId, string $incidentPublicId): array
{
    $incident = mg_ai_reconciliation_incident_row($pdo, $incidentPublicId);
    if (!in_array((string)$incident['incident_type'], ['provider_without_ledger','credit_debit_failed'], true)) throw new RuntimeException('This incident type does not support debit retry.');
    $reference = trim((string)($incident['source_reference'] ?? ''));
    if ($reference === '') throw new RuntimeException('The original provider response reference is required for a safe retry.');
    $stmt = $pdo->prepare('SELECT * FROM ai_credit_provider_responses WHERE user_id=? AND provider_key=? AND response_reference=? LIMIT 1');
    $stmt->execute([(int)$incident['user_id'],(string)$incident['provider_key'],$reference]);
    $response = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($response)) throw new RuntimeException('Durable provider response evidence was not found for this incident.');
    $sourceType = (string)$response['source_type'];
    if (!str_starts_with($sourceType, 'merchant_agent_')) throw new RuntimeException('The provider response source is not eligible for Merchant Agent credit recovery.');
    $metadata = mg_ai_reconciliation_json($response['metadata_json'] ?? null) + ['reconciliation_retry'=>true,'reconciliation_incident_id'=>(string)$incident['public_id'],'retry_admin_user_id'=>$adminUserId];
    try {
        mg_ai_credit_consume($pdo,(int)$incident['user_id'],(int)$response['provider_id'],$response['model_id']!==null?(int)$response['model_id']:null,(string)$response['provider_key'],(int)$response['input_tokens'],(int)$response['output_tokens'],$sourceType,$reference,$metadata);
        mg_ai_reconciliation_mark_provider_accounting($pdo,(int)$incident['user_id'],(string)$response['provider_key'],$reference,true,['reconciliation_retry'=>true,'retry_admin_user_id'=>$adminUserId]);
        $pdo->prepare("UPDATE ai_credit_reconciliation_incidents SET status='under_review',assigned_admin_user_id=COALESCE(assigned_admin_user_id,?),retry_count=retry_count+1,last_retry_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$adminUserId,(int)$incident['id']]);
        mg_ai_reconciliation_action($pdo,(int)$incident['id'],'retry_debit',$adminUserId,'Controlled idempotent debit retry completed using the original provider response reference.',['response_reference'=>$reference]);
        if (function_exists('mg_audit')) mg_audit('admin_ai_credit_incident_retry_debit','user',['incident_id'=>(string)$incident['public_id'],'response_reference'=>$reference],$adminUserId);
        if (function_exists('mg_security_log')) mg_security_log('warning','admin.ai_credit_incident.retry_debit','Admin completed a controlled AI credit debit retry.',['incident_id'=>(string)$incident['public_id'],'response_reference'=>$reference],$adminUserId);
        $reconciliation = mg_ai_reconciliation_run($pdo,['trigger_source'=>'admin_retry','initiated_by_user_id'=>$adminUserId,'user_id'=>(int)$incident['user_id'],'provider_key'=>(string)$incident['provider_key'],'days'=>30]);
        return ['incident_id'=>(string)$incident['public_id'],'response_reference'=>$reference,'reconciliation'=>$reconciliation];
    } catch (Throwable $error) {
        $pdo->prepare('UPDATE ai_credit_reconciliation_incidents SET retry_count=retry_count+1,last_retry_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$incident['id']]);
        mg_ai_reconciliation_action($pdo,(int)$incident['id'],'retry_failed',$adminUserId,'Controlled debit retry failed.',['exception_class'=>$error::class]);
        if (function_exists('mg_security_log')) mg_security_log('error','admin.ai_credit_incident.retry_failed','Controlled AI credit debit retry failed.',['incident_id'=>(string)$incident['public_id'],'exception_class'=>$error::class],$adminUserId);
        throw $error;
    }
}
