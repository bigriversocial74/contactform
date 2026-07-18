<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent.php';

function mg_admin_agent_monitor_migrations_runtime(PDO $pdo): array
{
    if (!mg_admin_schema_has_table($pdo, 'schema_migrations')) {
        return [
            'events'=>[],
            'findings'=>[ [
                'monitor_key'=>'migration_readiness','domain'=>'database','finding_type'=>'schema_migrations_missing','severity'=>'critical',
                'title'=>'Migration registry is unavailable','summary'=>'The schema_migrations table could not be found.',
                'source_reference'=>'schema_migrations','evidence'=>['table'=>'schema_migrations'],
            ] ],
            'metrics'=>['available'=>false],
        ];
    }
    $manifestPath = dirname(__DIR__) . '/config/migrations.php';
    $manifest = is_file($manifestPath) ? require $manifestPath : [];
    $files = is_array($manifest['ordered_files'] ?? null) ? $manifest['ordered_files'] : [];
    $rows = mg_admin_agent_safe_rows($pdo, 'SELECT migration_key FROM schema_migrations');
    $applied = [];
    foreach ($rows as $row) $applied[(string)$row['migration_key']] = true;
    $missing = [];
    foreach ($files as $file) {
        $key = preg_replace('/\.sql$/', '', (string)$file);
        if ($key !== '' && empty($applied[$key])) $missing[] = (string)$file;
    }
    $findings = [];
    if ($missing !== []) {
        $findings[] = [
            'monitor_key'=>'migration_readiness','domain'=>'database','finding_type'=>'pending_canonical_migrations','severity'=>'critical',
            'title'=>'Canonical database migrations are pending','summary'=>count($missing) . ' migration file(s) in the canonical manifest are not recorded as applied.',
            'source_reference'=>'canonical_manifest','evidence'=>['missing'=>array_slice($missing, 0, 25),'total_missing'=>count($missing)],
        ];
    }
    return ['events'=>[],'findings'=>$findings,'metrics'=>['manifest_total'=>count($files),'applied_total'=>count($applied),'missing_total'=>count($missing)]];
}

function mg_admin_agent_run_monitor_runtime(PDO $pdo, string $monitorKey): array
{
    if ($monitorKey === 'migration_readiness') return mg_admin_agent_monitor_migrations_runtime($pdo);
    return mg_admin_agent_run_monitor($pdo, $monitorKey);
}

function mg_admin_agent_scan_runtime(PDO $pdo, array $options = []): array
{
    if (!mg_admin_agent_schema_ready($pdo)) throw new RuntimeException('Main Admin Agent SQL migration is required.');
    $trigger = strtolower(trim((string)($options['trigger_source'] ?? 'scheduled')));
    if (!in_array($trigger, ['scheduled','manual','workspace_load','api'], true)) $trigger = 'scheduled';
    $actorId = isset($options['initiated_by_user_id']) && (int)$options['initiated_by_user_id'] > 0 ? (int)$options['initiated_by_user_id'] : null;
    $scanPublic = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_scans (public_id,trigger_source,status,initiated_by_user_id,started_at,created_at) VALUES (?, ?, "running", ?, NOW(), NOW())')->execute([$scanPublic,$trigger,$actorId]);
    $scanId = (int)$pdo->lastInsertId();
    $created = $updated = $eventsIngested = 0;
    $monitorKeys = [];
    $detectedKeys = [];
    $monitorMetrics = [];
    try {
        $monitors = mg_admin_agent_safe_rows($pdo, 'SELECT id,monitor_key,label,domain FROM admin_agent_monitors WHERE enabled=1 ORDER BY id');
        foreach ($monitors as $monitor) {
            $monitorId = (int)$monitor['id'];
            $monitorKey = (string)$monitor['monitor_key'];
            $monitorKeys[] = $monitorKey;
            $pdo->prepare('UPDATE admin_agent_monitors SET last_status="running",last_started_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$monitorId]);
            try {
                $result = mg_admin_agent_run_monitor_runtime($pdo, $monitorKey);
                foreach ($result['events'] ?? [] as $event) {
                    if (mg_admin_agent_ingest_event($pdo, $event)) $eventsIngested++;
                }
                foreach ($result['findings'] ?? [] as $finding) {
                    $finding['monitor_key'] = $monitorKey;
                    $upsert = mg_admin_agent_upsert_finding($pdo, $scanId, $finding);
                    $detectedKeys[] = $upsert['key'];
                    if (!empty($upsert['reopened']) && !empty($upsert['id'])) {
                        $pdo->prepare('UPDATE admin_agent_findings SET resolved_by_user_id=NULL,resolved_at=NULL,resolution_note=NULL WHERE id=?')->execute([(int)$upsert['id']]);
                    }
                    $upsert['created'] ? $created++ : $updated++;
                }
                $monitorMetrics[$monitorKey] = $result['metrics'] ?? [];
                $status = ($result['findings'] ?? []) === [] ? 'healthy' : 'warning';
                foreach ($result['findings'] ?? [] as $finding) {
                    if (($finding['severity'] ?? '') === 'critical') $status = 'critical';
                }
                $pdo->prepare('UPDATE admin_agent_monitors SET last_status=?,last_completed_at=NOW(),last_success_at=NOW(),consecutive_failures=0,last_error=NULL,updated_at=NOW() WHERE id=?')->execute([$status,$monitorId]);
            } catch (Throwable $error) {
                $monitorMetrics[$monitorKey] = ['failed'=>true,'exception_class'=>$error::class];
                $pdo->prepare('UPDATE admin_agent_monitors SET last_status="failed",last_completed_at=NOW(),consecutive_failures=consecutive_failures+1,last_error=?,updated_at=NOW() WHERE id=?')->execute([mb_substr($error->getMessage(),0,1000),$monitorId]);
                $failure = mg_admin_agent_upsert_finding($pdo, $scanId, [
                    'monitor_key'=>$monitorKey,'domain'=>(string)$monitor['domain'],'finding_type'=>'monitor_execution_failed','severity'=>'high',
                    'title'=>(string)$monitor['label'] . ' monitor failed','summary'=>'The monitor could not complete. Review the security log for the exception class.',
                    'source_reference'=>$monitorKey,'evidence'=>['exception_class'=>$error::class],
                ]);
                $detectedKeys[] = $failure['key'];
                $failure['created'] ? $created++ : $updated++;
                mg_security_log('error', 'admin_agent.monitor_failed', 'Main Admin Agent monitor failed.', ['monitor_key'=>$monitorKey,'exception_class'=>$error::class], $actorId);
            }
        }
        $resolved = mg_admin_agent_auto_resolve($pdo, $scanId, $monitorKeys, array_values(array_unique($detectedKeys)));
        $health = mg_admin_agent_health($pdo);
        $pdo->prepare('UPDATE admin_agent_scans SET status="completed",monitors_run=?,events_ingested=?,findings_created=?,findings_updated=?,findings_resolved=?,health_score=?,metrics_json=?,completed_at=NOW() WHERE id=?')->execute([
            count($monitorKeys),$eventsIngested,$created,$updated,$resolved,$health['score'],
            json_encode($monitorMetrics, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),$scanId,
        ]);
        if (mg_admin_agent_ingest_event($pdo, [
            'monitor_key'=>'admin_agent','domain'=>'system','severity'=>$health['score'] < 40 ? 'critical' : ($health['score'] < 70 ? 'warning' : 'info'),
            'event_type'=>'admin_agent.scan.completed','title'=>'Main Admin Agent scan completed',
            'message'=>count($monitorKeys) . ' monitors ran; ' . $created . ' findings created; ' . $resolved . ' resolved.',
            'source_table'=>'admin_agent_scans','source_id'=>$scanPublic,'entity_type'=>'admin_agent_scan','entity_id'=>$scanPublic,
            'evidence'=>['health'=>$health,'events_ingested'=>$eventsIngested],'occurred_at'=>gmdate('Y-m-d H:i:s'),
        ])) $eventsIngested++;
        mg_audit('admin_agent_scan_completed', 'system', ['scan_id'=>$scanPublic,'health_score'=>$health['score'],'events_ingested'=>$eventsIngested,'findings_created'=>$created,'findings_resolved'=>$resolved], $actorId);
        return ['id'=>$scanPublic,'status'=>'completed','health'=>$health,'monitors_run'=>count($monitorKeys),'events_ingested'=>$eventsIngested,'findings_created'=>$created,'findings_updated'=>$updated,'findings_resolved'=>$resolved];
    } catch (Throwable $error) {
        $pdo->prepare('UPDATE admin_agent_scans SET status="failed",failure_message=?,completed_at=NOW() WHERE id=?')->execute([mb_substr($error->getMessage(),0,1000),$scanId]);
        mg_security_log('error', 'admin_agent.scan_failed', 'Main Admin Agent scan failed.', ['scan_id'=>$scanPublic,'exception_class'=>$error::class], $actorId);
        throw $error;
    }
}

function mg_admin_agent_events_runtime(PDO $pdo, int $afterId = 0, int $limit = 100, string $domain = ''): array
{
    $afterId = max(0, $afterId);
    $limit = max(10, min(200, $limit));
    $domain = preg_replace('/[^a-z0-9_]/', '', strtolower($domain));
    $where = [];
    $params = [];
    if ($afterId > 0) { $where[] = 'id>?'; $params[] = $afterId; }
    if ($domain !== '') { $where[] = 'domain=?'; $params[] = $domain; }
    $sql = 'SELECT id,public_id,monitor_key,domain,severity,event_type,title,message,source_table,source_id,actor_user_id,entity_type,entity_id,evidence_json,occurred_at,ingested_at FROM admin_agent_events';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= $afterId > 0 ? ' ORDER BY id ASC' : ' ORDER BY id DESC';
    $sql .= ' LIMIT ' . $limit;
    $rows = mg_admin_agent_safe_rows($pdo, $sql, $params);
    if ($afterId === 0) $rows = array_reverse($rows);
    return array_map(static fn(array $row): array => [
        'cursor'=>(int)$row['id'],'id'=>(string)$row['public_id'],'monitor_key'=>(string)$row['monitor_key'],'domain'=>(string)$row['domain'],
        'severity'=>(string)$row['severity'],'type'=>(string)$row['event_type'],'title'=>(string)$row['title'],'message'=>(string)($row['message'] ?? ''),
        'source'=>['table'=>$row['source_table'],'id'=>$row['source_id']],'actor_user_id'=>$row['actor_user_id'] !== null ? (int)$row['actor_user_id'] : null,
        'entity'=>['type'=>$row['entity_type'],'id'=>$row['entity_id']],'evidence'=>mg_admin_agent_json($row['evidence_json'] ?? null),
        'occurred_at'=>(string)$row['occurred_at'],'ingested_at'=>(string)$row['ingested_at'],
    ], $rows);
}

function mg_admin_agent_chat_response_runtime(PDO $pdo, string $mode): array
{
    $health = mg_admin_agent_health($pdo);
    $domains = mg_admin_agent_domain_health($pdo);
    $findings = mg_admin_agent_findings($pdo, ['status'=>'active','limit'=>25]);
    $lastScan = mg_admin_agent_last_scan($pdo);
    $events = mg_admin_agent_events_runtime($pdo, 0, 40);
    $filteredFindings = $findings;
    $filteredEvents = $events;
    $title = 'Main Admin Agent system overview';
    $intro = 'The platform health score is ' . $health['score'] . '/100 (' . $health['status'] . ').';
    if ($mode === 'security') {
        $title = 'Security monitoring report';
        $filteredFindings = array_values(array_filter($findings, static fn(array $f): bool => $f['domain'] === 'security'));
        $filteredEvents = array_values(array_filter($events, static fn(array $e): bool => $e['domain'] === 'security'));
        $intro = count($filteredFindings) . ' active security finding(s) are currently tracked.';
    } elseif ($mode === 'migrations') {
        $title = 'Database and migration report';
        $filteredFindings = array_values(array_filter($findings, static fn(array $f): bool => $f['domain'] === 'database'));
        $filteredEvents = array_values(array_filter($events, static fn(array $e): bool => $e['domain'] === 'database'));
        $intro = count($filteredFindings) . ' active database finding(s) are currently tracked.';
    } elseif ($mode === 'ai_accounting') {
        $title = 'AI credit accounting report';
        $filteredFindings = array_values(array_filter($findings, static fn(array $f): bool => $f['domain'] === 'ai_accounting'));
        $filteredEvents = array_values(array_filter($events, static fn(array $e): bool => $e['domain'] === 'ai_accounting'));
        $intro = count($filteredFindings) . ' active AI accounting finding(s) are currently tracked.';
    } elseif ($mode === 'operations') {
        $title = 'Operations and queue report';
        $filteredFindings = array_values(array_filter($findings, static fn(array $f): bool => in_array($f['domain'], ['operations','support','automation','notifications'], true)));
        $filteredEvents = array_values(array_filter($events, static fn(array $e): bool => in_array($e['domain'], ['operations','support','automation','notifications'], true)));
        $intro = count($filteredFindings) . ' active operations-related finding(s) are currently tracked.';
    } elseif ($mode === 'findings') {
        $title = 'Active system findings';
        $intro = count($findings) . ' active finding(s): ' . $health['critical_total'] . ' critical and ' . $health['high_total'] . ' high severity.';
    } elseif ($mode === 'recent' || $mode === 'changes') {
        $title = $mode === 'changes' ? 'What changed since recent monitoring' : 'Recent normalized system activity';
        $intro = count($events) . ' recent normalized event(s) are available in this report window.';
    } elseif ($mode === 'help') {
        return [
            'title'=>'Main Admin Agent commands',
            'content'=>'Ask for: Overview, What changed, Active findings, Security, Operations, AI credit accounting, Migrations, or Recent activity. Monitoring and these reports are database-only and use no AI credits. Remediation requests are review-gated and never execute automatically.',
            'blocks'=>[['type'=>'commands','items'=>['Overview','What changed','Active findings','Security report','Operations report','AI credit accounting','Migration report','Recent activity']]],
            'metadata'=>['mode'=>'help','database_only'=>true,'used_ai'=>false],
        ];
    }
    $lines = [$intro];
    if ($filteredFindings !== []) {
        $lines[] = 'Highest-priority findings:';
        foreach (array_slice($filteredFindings, 0, 8) as $finding) {
            $lines[] = strtoupper($finding['severity']) . ' — ' . $finding['title'] . ': ' . $finding['summary'];
        }
    } else {
        $lines[] = 'No active findings match this report.';
    }
    if (in_array($mode, ['recent','changes'], true) && $filteredEvents !== []) {
        $lines[] = 'Recent changes:';
        foreach (array_slice(array_reverse($filteredEvents), 0, 10) as $event) {
            $lines[] = strtoupper($event['severity']) . ' — ' . $event['title'] . ' (' . $event['occurred_at'] . ')';
        }
    }
    return [
        'title'=>$title,
        'content'=>implode("\n", $lines),
        'blocks'=>[
            ['type'=>'health','health'=>$health,'last_scan'=>$lastScan],
            ['type'=>'domains','items'=>$domains],
            ['type'=>'findings','items'=>array_slice($filteredFindings,0,12)],
            ['type'=>'events','items'=>array_slice(array_reverse($filteredEvents),0,12)],
        ],
        'metadata'=>['mode'=>$mode,'database_only'=>true,'used_ai'=>false,'generated_at'=>gmdate('Y-m-d H:i:s')],
    ];
}

function mg_admin_agent_send_runtime(PDO $pdo, int $adminId, array $input): array
{
    $message = mb_substr(trim((string)($input['message'] ?? '')), 0, 4000);
    if ($message === '') throw new InvalidArgumentException('Enter a message for the Main Admin Agent.');
    $thread = mg_admin_agent_thread($pdo, $adminId, isset($input['thread_id']) ? (string)$input['thread_id'] : null);
    $userMessage = mg_admin_agent_record_message($pdo, (int)$thread['id'], $adminId, 'user', $message, 'chat', [], ['database_only'=>true]);
    $mode = mg_admin_agent_chat_mode($message);
    $report = mg_admin_agent_chat_response_runtime($pdo, $mode);
    $assistant = mg_admin_agent_record_message($pdo, (int)$thread['id'], $adminId, 'assistant', $report['content'], 'system_report', $report['blocks'], $report['metadata'] + ['title'=>$report['title']]);
    mg_audit('admin_agent_chat_report', 'system', ['thread_id'=>$thread['public_id'],'mode'=>$mode,'database_only'=>true], $adminId);
    return ['thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title']],'user_message'=>$userMessage,'assistant_message'=>$assistant,'report'=>$report];
}

function mg_admin_agent_apply_finding_action_runtime(PDO $pdo, int $adminId, array $input): array
{
    $publicId = trim((string)($input['finding_id'] ?? ''));
    $action = strtolower(trim((string)($input['action_key'] ?? $input['finding_action'] ?? 'acknowledge')));
    $note = mb_substr(trim((string)($input['note'] ?? '')), 0, 1000);
    if (!in_array($action, ['acknowledge','assign_self','under_review','resolve','dismiss','reopen'], true)) throw new InvalidArgumentException('Unknown finding action.');
    if (in_array($action, ['resolve','dismiss'], true) && $note === '') throw new InvalidArgumentException('A resolution or dismissal note is required.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id,status FROM admin_agent_findings WHERE public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$publicId]);
        $finding = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$finding) throw new InvalidArgumentException('System finding not found.');
        if ($action === 'acknowledge') $pdo->prepare('UPDATE admin_agent_findings SET status="acknowledged",acknowledged_by_user_id=?,acknowledged_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$adminId,(int)$finding['id']]);
        elseif ($action === 'assign_self') $pdo->prepare('UPDATE admin_agent_findings SET assigned_admin_user_id=?,updated_at=NOW() WHERE id=?')->execute([$adminId,(int)$finding['id']]);
        elseif ($action === 'under_review') $pdo->prepare('UPDATE admin_agent_findings SET status="under_review",assigned_admin_user_id=COALESCE(assigned_admin_user_id,?),updated_at=NOW() WHERE id=?')->execute([$adminId,(int)$finding['id']]);
        elseif ($action === 'resolve') $pdo->prepare('UPDATE admin_agent_findings SET status="resolved",resolved_by_user_id=?,resolved_at=NOW(),resolution_note=?,updated_at=NOW() WHERE id=?')->execute([$adminId,$note,(int)$finding['id']]);
        elseif ($action === 'dismiss') $pdo->prepare('UPDATE admin_agent_findings SET status="dismissed",resolved_by_user_id=?,resolved_at=NOW(),resolution_note=?,updated_at=NOW() WHERE id=?')->execute([$adminId,$note,(int)$finding['id']]);
        else $pdo->prepare('UPDATE admin_agent_findings SET status="open",resolved_by_user_id=NULL,resolved_at=NULL,resolution_note=NULL,updated_at=NOW() WHERE id=?')->execute([(int)$finding['id']]);
        mg_admin_agent_finding_action($pdo, (int)$finding['id'], $adminId, $action, $note ?: null);
        $pdo->commit();
        mg_audit('admin_agent_finding_' . $action, 'system', ['finding_id'=>$publicId,'note_recorded'=>$note !== ''], $adminId);
        mg_security_log('info', 'admin_agent.finding_action', 'Main Admin Agent finding action completed.', ['finding_id'=>$publicId,'action'=>$action], $adminId);
        return ['finding_id'=>$publicId,'action'=>$action,'updated'=>true];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_admin_agent_state_runtime(PDO $pdo, int $adminId, array $options = []): array
{
    $schema = mg_admin_agent_schema_state($pdo);
    if (!$schema['ready']) return ['schema'=>$schema,'schema_ready'=>false];
    $after = max(0, (int)($options['after'] ?? 0));
    $thread = mg_admin_agent_thread($pdo, $adminId, isset($options['thread_id']) ? (string)$options['thread_id'] : null);
    $events = mg_admin_agent_events_runtime($pdo, $after, (int)($options['event_limit'] ?? 100), (string)($options['domain'] ?? ''));
    return [
        'schema'=>$schema,'schema_ready'=>true,'health'=>mg_admin_agent_health($pdo),'domains'=>mg_admin_agent_domain_health($pdo),
        'monitors'=>mg_admin_agent_monitors($pdo),'findings'=>mg_admin_agent_findings($pdo, ['status'=>$options['finding_status'] ?? 'active','domain'=>$options['domain'] ?? '','limit'=>100]),
        'events'=>$events,'event_cursor'=>$events !== [] ? (int)end($events)['cursor'] : $after,'last_scan'=>mg_admin_agent_last_scan($pdo),
        'threads'=>mg_admin_agent_threads($pdo, $adminId),'active_thread'=>['id'=>(string)$thread['public_id'],'title'=>(string)$thread['title'],'status'=>(string)$thread['status']],
        'messages'=>mg_admin_agent_messages($pdo, (int)$thread['id'], 150),'action_reviews'=>mg_admin_agent_action_reviews($pdo, 50),
        'systematic'=>['database_only'=>true,'used_ai'=>false,'credits_used'=>0,'realtime'=>'sse_with_polling_fallback'],'generated_at'=>gmdate('Y-m-d H:i:s'),
    ];
}
