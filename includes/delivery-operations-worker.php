<?php
declare(strict_types=1);

function mg_delivery_acquire_job(PDO $pdo, int $jobId, string $leaseToken, array $config): ?array
{
    $stmt = $pdo->prepare(
        "UPDATE notification_delivery_jobs
            SET status='processing',lease_token=?,lease_expires_at=DATE_ADD(NOW(),INTERVAL ? SECOND),updated_at=NOW()
          WHERE id=?
            AND status IN ('queued','retry_scheduled')
            AND (next_attempt_at IS NULL OR next_attempt_at<=NOW())
            AND (lease_expires_at IS NULL OR lease_expires_at<=NOW())"
    );
    $stmt->execute([$leaseToken, (int)$config['lease_seconds'], $jobId]);
    if ($stmt->rowCount() !== 1) return null;
    $read = $pdo->prepare(
        "SELECT j.*,n.public_id notification_public_id,n.type notification_type,n.title,n.body,n.action_url,n.context_json,n.created_at notification_created_at,
                u.status user_status,u.email user_email
           FROM notification_delivery_jobs j
           JOIN notifications n ON n.id=j.notification_id
           JOIN users u ON u.id=j.user_id
          WHERE j.id=? AND j.lease_token=? LIMIT 1"
    );
    $read->execute([$jobId, $leaseToken]);
    $row = $read->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_delivery_candidate_ids(PDO $pdo, array $config, int $limit): array
{
    $scan = max($limit, min(1000, $limit * 8));
    $stmt = $pdo->query(
        "SELECT j.id,j.user_id,COALESCE(j.merchant_user_id,CAST(JSON_UNQUOTE(JSON_EXTRACT(n.context_json,'$.merchant_user_id')) AS UNSIGNED),0) merchant_user_id
           FROM notification_delivery_jobs j
           JOIN notifications n ON n.id=j.notification_id
          WHERE j.status IN ('queued','retry_scheduled')
            AND (j.next_attempt_at IS NULL OR j.next_attempt_at<=NOW())
            AND (j.lease_expires_at IS NULL OR j.lease_expires_at<=NOW())
          ORDER BY j.priority DESC,COALESCE(j.next_attempt_at,j.created_at),j.id
          LIMIT " . $scan
    );
    $userCounts = [];
    $merchantCounts = [];
    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $userId = (int)$row['user_id'];
        $merchantId = (int)($row['merchant_user_id'] ?? 0);
        $userCounts[$userId] = ($userCounts[$userId] ?? 0) + 1;
        if ($userCounts[$userId] > (int)$config['max_per_user_per_run']) continue;
        if ($merchantId > 0) {
            $merchantCounts[$merchantId] = ($merchantCounts[$merchantId] ?? 0) + 1;
            if ($merchantCounts[$merchantId] > (int)$config['max_per_merchant_per_run']) continue;
        }
        $ids[] = (int)$row['id'];
        if (count($ids) >= $limit) break;
    }
    return $ids;
}

function mg_delivery_finalize_job(PDO $pdo, array $job, array $result, string $leaseToken, array $config): array
{
    $jobId = (int)$job['id'];
    $attempt = (int)$job['attempt_count'] + 1;
    $maxAttempts = max(1, (int)($job['max_attempts'] ?? $config['max_attempts']));
    $outcome = (string)$result['outcome'];
    $status = 'retry_scheduled';
    $event = 'retry_scheduled';
    $nextAttemptAt = null;

    if ($outcome === 'delivered') { $status = 'delivered'; $event = 'delivered'; }
    elseif ($outcome === 'accepted') { $status = 'provider_accepted'; $event = 'accepted'; }
    elseif ($outcome === 'suppressed') { $status = 'suppressed'; $event = 'suppressed'; }
    elseif ($outcome === 'dead_letter' || $attempt >= $maxAttempts) { $status = 'dead_letter'; $event = 'dead_lettered'; }
    else {
        $seconds = mg_delivery_backoff_seconds($attempt, $config);
        $nextAttemptAt = gmdate('Y-m-d H:i:s', time() + $seconds);
    }

    $sql = "UPDATE notification_delivery_jobs SET
              status=?,provider=COALESCE(?,provider),provider_reference=COALESCE(?,provider_reference),
              attempt_count=?,last_attempt_at=NOW(),next_attempt_at=?,lease_token=NULL,lease_expires_at=NULL,
              failure_code=?,failure_message=?,
              accepted_at=IF(?='provider_accepted',COALESCE(accepted_at,NOW()),accepted_at),
              sent_at=IF(? IN ('provider_accepted','delivered'),COALESCE(sent_at,NOW()),sent_at),
              delivered_at=IF(?='delivered',COALESCE(delivered_at,NOW()),delivered_at),
              failed_at=IF(? IN ('retry_scheduled','dead_letter'),NOW(),failed_at),
              suppressed_at=IF(?='suppressed',COALESCE(suppressed_at,NOW()),suppressed_at),
              dead_lettered_at=IF(?='dead_letter',COALESCE(dead_lettered_at,NOW()),dead_lettered_at),
              updated_at=NOW()
            WHERE id=? AND lease_token=?";
    $stmt = $pdo->prepare($sql);
    $failureCode = in_array($status, ['retry_scheduled','dead_letter'], true) ? ($result['code'] ?: $status) : null;
    $failureMessage = in_array($status, ['retry_scheduled','dead_letter'], true) ? ($result['message'] ?: 'Delivery attempt did not complete.') : null;
    $stmt->execute([
        $status, $result['provider'] ?: null, $result['provider_reference'] ?: null,
        $attempt, $nextAttemptAt, $failureCode, $failureMessage,
        $status, $status, $status, $status, $status, $status,
        $jobId, $leaseToken,
    ]);
    if ($stmt->rowCount() !== 1) {
        return ['status'=>'ownership_lost','event'=>'failed','attempt'=>$attempt,'job_id'=>$jobId];
    }
    mg_delivery_provider_event($pdo, $jobId, $event, $result, $attempt);
    return ['status'=>$status,'event'=>$event,'attempt'=>$attempt,'job_id'=>$jobId,'next_attempt_at'=>$nextAttemptAt];
}

function mg_delivery_create_run(PDO $pdo, string $mode, int $limit): array
{
    $publicId = mg_delivery_uuid();
    $worker = gethostname() . ':' . (function_exists('getmypid') ? getmypid() : 'web');
    $stmt = $pdo->prepare("INSERT INTO mg_delivery_worker_runs (public_id,mode,status,worker_name,batch_limit,started_at) VALUES (?,?,'running',?,?,NOW())");
    $stmt->execute([$publicId, $mode, mb_substr($worker, 0, 120), $limit]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId,'started'=>microtime(true)];
}

function mg_delivery_complete_run(PDO $pdo, array $run, string $status, array $counts, array $metadata = []): void
{
    $duration = max(0, (int)round((microtime(true) - (float)$run['started']) * 1000));
    $pdo->prepare(
        'UPDATE mg_delivery_worker_runs SET status=?,selected_count=?,processed_count=?,delivered_count=?,accepted_count=?,retry_count=?,suppressed_count=?,dead_letter_count=?,failed_count=?,duration_ms=?,metadata_json=?,completed_at=NOW() WHERE id=?'
    )->execute([
        $status,
        (int)($counts['selected'] ?? 0), (int)($counts['processed'] ?? 0),
        (int)($counts['delivered'] ?? 0), (int)($counts['provider_accepted'] ?? 0),
        (int)($counts['retry_scheduled'] ?? 0), (int)($counts['suppressed'] ?? 0),
        (int)($counts['dead_letter'] ?? 0), (int)($counts['failed'] ?? 0),
        $duration, mg_delivery_safe_json($metadata), (int)$run['id'],
    ]);
}

function mg_delivery_maybe_pause(PDO $pdo, array $counts, array $config): bool
{
    $attempted = (int)($counts['processed'] ?? 0);
    $bad = (int)($counts['retry_scheduled'] ?? 0) + (int)($counts['dead_letter'] ?? 0) + (int)($counts['failed'] ?? 0);
    if ($attempted < (int)$config['failure_pause_min_attempts']) return false;
    $rate = ($bad / max(1, $attempted)) * 100;
    if ($rate < (int)$config['failure_pause_percent']) return false;
    mg_delivery_pause($pdo, sprintf('Automatic pause: %.1f%% of %d attempted jobs did not complete.', $rate, $attempted));
    return true;
}

function mg_delivery_run(PDO $pdo, string $mode = 'observe', ?int $requestedLimit = null): array
{
    $config = mg_delivery_config();
    $mode = $mode === 'process' ? 'process' : 'observe';
    $limit = max(1, min((int)$config['batch_size'], $requestedLimit ?? (int)$config['batch_size']));
    if (!mg_delivery_schema_ready($pdo)) return ['status'=>'skipped','reason'=>'delivery_schema_not_ready','exit_code'=>2];

    $run = mg_delivery_create_run($pdo, $mode, $limit);
    $counts = ['selected'=>0,'processed'=>0,'delivered'=>0,'provider_accepted'=>0,'retry_scheduled'=>0,'suppressed'=>0,'dead_letter'=>0,'failed'=>0];
    $lockAcquired = false;
    try {
        $lockStmt = $pdo->prepare('SELECT GET_LOCK(?,0)');
        $lockStmt->execute([(string)$config['lock_name']]);
        $lockAcquired = (int)$lockStmt->fetchColumn() === 1;
        if (!$lockAcquired) {
            mg_delivery_complete_run($pdo, $run, 'skipped', $counts, ['reason'=>'worker_overlap_detected']);
            return ['status'=>'skipped','reason'=>'worker_overlap_detected','run_id'=>$run['public_id'],'exit_code'=>2];
        }

        $leaseRecovery = mg_delivery_recover_expired_leases($pdo);
        $recovered = (int)$leaseRecovery['recovered'];
        $state = mg_delivery_worker_state($pdo);
        $summary = mg_delivery_summary($pdo);
        if ($mode === 'observe') {
            mg_delivery_complete_run($pdo, $run, 'completed', $counts, ['recovered_leases'=>$recovered,'lease_dead_letters'=>(int)$leaseRecovery['dead_lettered'],'summary'=>$summary]);
            return ['status'=>'completed','mode'=>'observe','run_id'=>$run['public_id'],'summary'=>$summary,'recovered_leases'=>$recovered,'lease_dead_letters'=>(int)$leaseRecovery['dead_lettered'],'exit_code'=>0];
        }
        if (empty($config['worker_enabled'])) {
            mg_delivery_complete_run($pdo, $run, 'skipped', $counts, ['reason'=>'worker_disabled']);
            return ['status'=>'skipped','reason'=>'worker_disabled','run_id'=>$run['public_id'],'exit_code'=>2];
        }
        if (!empty($state['paused'])) {
            mg_delivery_complete_run($pdo, $run, 'paused', $counts, ['reason'=>$state['pause_reason']]);
            return ['status'=>'paused','reason'=>$state['pause_reason'],'run_id'=>$run['public_id'],'exit_code'=>2];
        }

        $ids = mg_delivery_candidate_ids($pdo, $config, $limit);
        $counts['selected'] = count($ids);
        $deadline = microtime(true) + (int)$config['max_runtime_seconds'];
        foreach ($ids as $id) {
            if (microtime(true) >= $deadline) break;
            $leaseToken = bin2hex(random_bytes(32));
            $job = mg_delivery_acquire_job($pdo, $id, $leaseToken, $config);
            if (!$job) continue;
            $counts['processed']++;
            if ((string)($job['user_status'] ?? '') !== 'active') {
                $result = mg_delivery_normalize_result(['outcome'=>'suppressed','provider'=>'microgifter','code'=>'recipient_inactive','message'=>'Recipient account is not active.']);
            } else {
                mg_delivery_provider_event($pdo, (int)$job['id'], 'attempted', ['provider'=>$job['provider'] ?? $job['channel'],'code'=>'attempted','message'=>'Delivery attempt started.','metadata'=>[]], (int)$job['attempt_count'] + 1);
                $result = mg_delivery_process_adapter($pdo, $job, $config);
            }
            $final = mg_delivery_finalize_job($pdo, $job, $result, $leaseToken, $config);
            $finalStatus = (string)($final['status'] ?? 'failed');
            if (isset($counts[$finalStatus])) $counts[$finalStatus]++;
            else $counts['failed']++;
        }

        $paused = mg_delivery_maybe_pause($pdo, $counts, $config);
        $status = $paused ? 'paused' : (($counts['processed'] < $counts['selected']) ? 'partial' : 'completed');
        mg_delivery_complete_run($pdo, $run, $status, $counts, ['recovered_leases'=>$recovered,'lease_dead_letters'=>(int)$leaseRecovery['dead_lettered']]);
        return ['status'=>$status,'mode'=>'process','run_id'=>$run['public_id'],'counts'=>$counts,'recovered_leases'=>$recovered,'lease_dead_letters'=>(int)$leaseRecovery['dead_lettered'],'exit_code'=>$status === 'completed' ? 0 : 2];
    } catch (Throwable $error) {
        $counts['failed']++;
        mg_delivery_complete_run($pdo, $run, 'failed', $counts, ['error'=>mb_substr($error->getMessage(),0,500)]);
        if (function_exists('mg_security_log')) mg_security_log('error','delivery.worker.failed','Delivery worker failed.',['exception_class'=>$error::class],null);
        return ['status'=>'failed','run_id'=>$run['public_id'],'error'=>mb_substr($error->getMessage(),0,500),'exit_code'=>1];
    } finally {
        if ($lockAcquired) {
            try { $release = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([(string)$config['lock_name']]); } catch (Throwable) {}
        }
    }
}
