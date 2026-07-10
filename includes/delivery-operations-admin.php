<?php
declare(strict_types=1);

function mg_delivery_summary(PDO $pdo): array
{
    $config = mg_delivery_config();
    $ready = mg_delivery_schema_ready($pdo);
    if (!$ready) return ['schema_ready'=>false,'score'=>0,'status'=>'critical','configuration'=>$config];

    $counts = [];
    $stmt = $pdo->query('SELECT channel,status,COUNT(*) total FROM notification_delivery_jobs GROUP BY channel,status');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $counts[(string)$row['channel']][(string)$row['status']] = (int)$row['total'];
    }
    $due = (int)$pdo->query("SELECT COUNT(*) FROM notification_delivery_jobs WHERE status IN ('queued','retry_scheduled') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW())")->fetchColumn();
    $oldest = $pdo->query("SELECT TIMESTAMPDIFF(SECOND,created_at,NOW()) FROM notification_delivery_jobs WHERE status IN ('queued','retry_scheduled') ORDER BY created_at LIMIT 1")->fetchColumn();
    $dead = (int)$pdo->query("SELECT COUNT(*) FROM notification_delivery_jobs WHERE status='dead_letter'")->fetchColumn();
    $processing = (int)$pdo->query("SELECT COUNT(*) FROM notification_delivery_jobs WHERE status='processing'")->fetchColumn();
    $state = mg_delivery_worker_state($pdo);
    $lastRun = $pdo->query('SELECT public_id,mode,status,batch_limit,selected_count,processed_count,delivered_count,accepted_count,retry_count,suppressed_count,dead_letter_count,failed_count,duration_ms,started_at,completed_at FROM mg_delivery_worker_runs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
    $recent = $pdo->query('SELECT public_id,mode,status,processed_count,delivered_count,accepted_count,retry_count,dead_letter_count,failed_count,duration_ms,started_at,completed_at FROM mg_delivery_worker_runs ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $channelReadiness = mg_delivery_channel_readiness($config);
    $checks = [
        'schema' => $ready,
        'in_app_durable' => true,
        'worker_configured' => is_bool($config['worker_enabled']),
        'capacity_positive' => $config['batch_size'] >= 1 && $config['max_runtime_seconds'] >= 5,
        'retry_policy' => $config['max_attempts'] >= 1 && $config['retry_base_seconds'] >= 10,
        'fairness' => $config['max_per_user_per_run'] >= 1 && $config['max_per_merchant_per_run'] >= 1,
        'not_paused' => empty($state['paused']),
        'no_dead_letters' => $dead === 0,
        'no_expired_processing' => (int)$pdo->query("SELECT COUNT(*) FROM notification_delivery_jobs WHERE status='processing' AND lease_expires_at<NOW()")->fetchColumn() === 0,
        'external_channels_ready' => !in_array(false, array_map(static fn(array $row): bool => !empty($row['ready']), $channelReadiness), true),
    ];
    $score = (int)round((count(array_filter($checks)) / count($checks)) * 100);
    $status = $score === 100 ? 'healthy' : ($score >= 80 ? 'warning' : 'critical');
    return [
        'schema_ready'=>true,
        'score'=>$score,
        'status'=>$status,
        'checks'=>$checks,
        'state'=>$state,
        'channel_readiness'=>$channelReadiness,
        'configuration'=>[
            'worker_enabled'=>$config['worker_enabled'],
            'batch_size'=>$config['batch_size'],
            'max_runtime_seconds'=>$config['max_runtime_seconds'],
            'lease_seconds'=>$config['lease_seconds'],
            'max_attempts'=>$config['max_attempts'],
            'max_per_user_per_run'=>$config['max_per_user_per_run'],
            'max_per_merchant_per_run'=>$config['max_per_merchant_per_run'],
            'failure_pause_percent'=>$config['failure_pause_percent'],
            'channels'=>$config['channels'],
        ],
        'queue'=>[
            'due'=>$due,
            'processing'=>$processing,
            'dead_letter'=>$dead,
            'oldest_pending_age_seconds'=>$oldest === false || $oldest === null ? null : (int)$oldest,
            'by_channel_status'=>$counts,
        ],
        'last_run'=>$lastRun,
        'recent_runs'=>$recent,
    ];
}

function mg_delivery_list_jobs(PDO $pdo, array $filters = []): array
{
    $where = ['1=1'];
    $params = [];
    $status = strtolower(trim((string)($filters['status'] ?? '')));
    $channel = strtolower(trim((string)($filters['channel'] ?? '')));
    if (in_array($status, ['queued','processing','retry_scheduled','provider_accepted','sent','delivered','failed','dead_letter','cancelled','suppressed'], true)) { $where[]='j.status=?'; $params[]=$status; }
    if (in_array($channel, ['in_app','email','sms','push','webhook'], true)) { $where[]='j.channel=?'; $params[]=$channel; }
    $limit = max(10, min(200, (int)($filters['limit'] ?? 100)));
    $stmt = $pdo->prepare(
        "SELECT j.public_id,j.channel,j.status,j.provider,j.attempt_count,j.max_attempts,j.next_attempt_at,j.sent_at,j.accepted_at,j.delivered_at,j.failed_at,j.failure_code,j.failure_message,j.created_at,j.updated_at,
                n.public_id notification_id,n.type notification_type,n.title,n.action_url,
                u.id user_id,u.display_name,u.full_name
           FROM notification_delivery_jobs j
           JOIN notifications n ON n.id=j.notification_id
           JOIN users u ON u.id=j.user_id
          WHERE " . implode(' AND ', $where) . "
          ORDER BY FIELD(j.status,'dead_letter','failed','retry_scheduled','queued','processing','provider_accepted','sent','delivered','suppressed','cancelled'),j.created_at DESC
          LIMIT {$limit}"
    );
    $stmt->execute($params);
    return array_map(static function(array $row): array {
        $name = trim((string)($row['display_name'] ?: $row['full_name'] ?: 'Member'));
        return [
            'id'=>(string)$row['public_id'],
            'channel'=>(string)$row['channel'],
            'status'=>(string)$row['status'],
            'provider'=>$row['provider'] !== null ? (string)$row['provider'] : null,
            'attempt_count'=>(int)$row['attempt_count'],
            'max_attempts'=>(int)$row['max_attempts'],
            'next_attempt_at'=>$row['next_attempt_at'],
            'sent_at'=>$row['sent_at'],
            'accepted_at'=>$row['accepted_at'],
            'delivered_at'=>$row['delivered_at'],
            'failed_at'=>$row['failed_at'],
            'failure_code'=>$row['failure_code'],
            'failure_message'=>$row['failure_message'] !== null ? mb_substr((string)$row['failure_message'],0,240) : null,
            'created_at'=>(string)$row['created_at'],
            'updated_at'=>(string)$row['updated_at'],
            'notification'=>[
                'id'=>(string)$row['notification_id'],
                'type'=>(string)$row['notification_type'],
                'title'=>(string)$row['title'],
                'action_url'=>$row['action_url'],
            ],
            'recipient'=>['id'=>(int)$row['user_id'],'label'=>mb_substr($name !== '' ? $name : 'Member',0,120)],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function mg_delivery_job_by_public_id(PDO $pdo, string $publicId, bool $forUpdate = false): array
{
    if (preg_match('/^[a-f0-9-]{20,64}$/i', $publicId) !== 1) throw new InvalidArgumentException('Invalid delivery job identifier.');
    $stmt = $pdo->prepare('SELECT * FROM notification_delivery_jobs WHERE public_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Delivery job not found.');
    return $row;
}

function mg_delivery_operator_action(PDO $pdo, string $action, string $publicId, int $actorUserId): array
{
    $allowed = ['retry','cancel','requeue_dead_letter'];
    if (!in_array($action, $allowed, true)) throw new InvalidArgumentException('Unsupported delivery action.');
    $pdo->beginTransaction();
    try {
        $job = mg_delivery_job_by_public_id($pdo, $publicId, true);
        if ($action === 'cancel') {
            if (!in_array((string)$job['status'], ['queued','retry_scheduled','failed','dead_letter','suppressed'], true)) throw new RuntimeException('Only inactive pending or failed jobs can be cancelled safely.');
            $pdo->prepare("UPDATE notification_delivery_jobs SET status='cancelled',cancelled_at=NOW(),lease_token=NULL,lease_expires_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$job['id']]);
            mg_delivery_provider_event($pdo,(int)$job['id'],'cancelled',['provider'=>'operator','code'=>'operator_cancelled','message'=>'Cancelled by an authorized operator.','metadata'=>['actor_user_id'=>$actorUserId]],(int)$job['attempt_count']);
        } else {
            if ($action === 'requeue_dead_letter' && (string)$job['status'] !== 'dead_letter') throw new RuntimeException('Only dead-letter jobs can use this recovery action.');
            $resetAttempts = $action === 'requeue_dead_letter';
            $pdo->prepare("UPDATE notification_delivery_jobs SET status='queued',attempt_count=IF(?,0,attempt_count),next_attempt_at=NOW(),lease_token=NULL,lease_expires_at=NULL,failure_code=NULL,failure_message=NULL,failed_at=NULL,dead_lettered_at=NULL,updated_at=NOW() WHERE id=?")->execute([$resetAttempts ? 1 : 0,(int)$job['id']]);
            mg_delivery_provider_event($pdo,(int)$job['id'],'recovered',['provider'=>'operator','code'=>$action,'message'=>'Delivery job returned to the queue.','metadata'=>['actor_user_id'=>$actorUserId]],(int)$job['attempt_count']);
        }
        if (function_exists('mg_audit')) mg_audit('delivery_job_' . $action, 'notification', ['delivery_job_id'=>$publicId], $actorUserId);
        $pdo->commit();
        return ['updated'=>true,'job_id'=>$publicId,'action'=>$action];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
