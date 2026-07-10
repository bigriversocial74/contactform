<?php
declare(strict_types=1);

function mg_delivery_bool(mixed $value, bool $fallback = false): bool
{
    if ($value === null || $value === '') return $fallback;
    if (is_bool($value)) return $value;
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
}

function mg_delivery_env(string $name, mixed $fallback = null): mixed
{
    $value = getenv($name);
    return $value === false || $value === '' ? $fallback : $value;
}

function mg_delivery_config(): array
{
    $root = function_exists('mg_config_value') ? [
        'enabled' => mg_config_value('delivery', 'worker_enabled', false),
        'batch_size' => mg_config_value('delivery', 'batch_size', 50),
        'runtime' => mg_config_value('delivery', 'max_runtime_seconds', 50),
        'lease' => mg_config_value('delivery', 'lease_seconds', 120),
        'max_attempts' => mg_config_value('delivery', 'max_attempts', 8),
        'retry_base' => mg_config_value('delivery', 'retry_base_seconds', 60),
        'retry_max' => mg_config_value('delivery', 'retry_max_seconds', 21600),
        'per_user' => mg_config_value('delivery', 'max_per_user_per_run', 10),
        'per_merchant' => mg_config_value('delivery', 'max_per_merchant_per_run', 100),
        'pause_percent' => mg_config_value('delivery', 'failure_pause_percent', 20),
        'pause_min' => mg_config_value('delivery', 'failure_pause_min_attempts', 10),
        'email' => mg_config_value('delivery', 'email_enabled', false),
        'sms' => mg_config_value('delivery', 'sms_enabled', false),
        'push' => mg_config_value('delivery', 'push_enabled', false),
    ] : [];

    return [
        'worker_enabled' => mg_delivery_bool(mg_delivery_env('MG_DELIVERY_WORKER_ENABLED', $root['enabled'] ?? false), false),
        'batch_size' => max(1, min(250, (int)mg_delivery_env('MG_DELIVERY_BATCH_SIZE', $root['batch_size'] ?? 50))),
        'max_runtime_seconds' => max(5, min(300, (int)mg_delivery_env('MG_DELIVERY_MAX_RUNTIME_SECONDS', $root['runtime'] ?? 50))),
        'lease_seconds' => max(30, min(900, (int)mg_delivery_env('MG_DELIVERY_LEASE_SECONDS', $root['lease'] ?? 120))),
        'max_attempts' => max(1, min(25, (int)mg_delivery_env('MG_DELIVERY_MAX_ATTEMPTS', $root['max_attempts'] ?? 8))),
        'retry_base_seconds' => max(10, min(3600, (int)mg_delivery_env('MG_DELIVERY_RETRY_BASE_SECONDS', $root['retry_base'] ?? 60))),
        'retry_max_seconds' => max(60, min(86400, (int)mg_delivery_env('MG_DELIVERY_RETRY_MAX_SECONDS', $root['retry_max'] ?? 21600))),
        'max_per_user_per_run' => max(1, min(50, (int)mg_delivery_env('MG_DELIVERY_MAX_PER_USER_PER_RUN', $root['per_user'] ?? 10))),
        'max_per_merchant_per_run' => max(1, min(250, (int)mg_delivery_env('MG_DELIVERY_MAX_PER_MERCHANT_PER_RUN', $root['per_merchant'] ?? 100))),
        'failure_pause_percent' => max(1, min(100, (int)mg_delivery_env('MG_DELIVERY_FAILURE_PAUSE_PERCENT', $root['pause_percent'] ?? 20))),
        'failure_pause_min_attempts' => max(1, min(100, (int)mg_delivery_env('MG_DELIVERY_FAILURE_PAUSE_MIN_ATTEMPTS', $root['pause_min'] ?? 10))),
        'channels' => [
            'in_app' => true,
            'email' => mg_delivery_bool(mg_delivery_env('MG_DELIVERY_EMAIL_ENABLED', $root['email'] ?? false), false),
            'sms' => mg_delivery_bool(mg_delivery_env('MG_DELIVERY_SMS_ENABLED', $root['sms'] ?? false), false),
            'push' => mg_delivery_bool(mg_delivery_env('MG_DELIVERY_PUSH_ENABLED', $root['push'] ?? false), false),
        ],
        'lock_name' => 'microgifter:delivery-worker:v1',
        'pause_acknowledgement' => 'ACKNOWLEDGE DELIVERY WORKER PAUSE',
    ];
}

function mg_delivery_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) return false;
    $key = spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function mg_delivery_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1');
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_delivery_schema_ready(PDO $pdo): bool
{
    foreach (['notifications','notification_delivery_jobs','mg_delivery_worker_state','mg_delivery_worker_runs','mg_delivery_provider_events'] as $table) {
        if (!mg_delivery_table_exists($pdo, $table)) return false;
    }
    foreach (['job_key','merchant_user_id','lease_token','lease_expires_at','max_attempts','metadata_json'] as $column) {
        if (!mg_delivery_column_exists($pdo, 'notification_delivery_jobs', $column)) return false;
    }
    return true;
}

function mg_delivery_channel_enabled(string $channel, ?array $config = null): bool
{
    $config ??= mg_delivery_config();
    return !empty(($config['channels'] ?? [])[$channel]);
}

function mg_delivery_job_key(int $notificationId, string $channel, ?string $destinationHash = null): string
{
    return hash('sha256', implode(':', ['notification', $notificationId, strtolower($channel), $destinationHash ?: 'primary']));
}

function mg_delivery_uuid(): string
{
    if (function_exists('mg_public_uuid')) return mg_public_uuid();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex,0,8) . '-' . substr($hex,8,4) . '-' . substr($hex,12,4) . '-' . substr($hex,16,4) . '-' . substr($hex,20,12);
}

function mg_delivery_safe_json(mixed $value): ?string
{
    if (!is_array($value) || $value === []) return null;
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_delivery_worker_state(PDO $pdo): array
{
    if (!mg_delivery_table_exists($pdo, 'mg_delivery_worker_state')) {
        return ['ready'=>false,'paused'=>true,'pause_reason'=>'Delivery operations migration is not installed.'];
    }
    $row = $pdo->query('SELECT * FROM mg_delivery_worker_state WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->exec('INSERT IGNORE INTO mg_delivery_worker_state (id,paused) VALUES (1,0)');
        $row = $pdo->query('SELECT * FROM mg_delivery_worker_state WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return [
        'ready' => true,
        'paused' => !empty($row['paused']),
        'pause_reason' => (string)($row['pause_reason'] ?? ''),
        'paused_at' => $row['paused_at'] ?? null,
        'cleared_at' => $row['cleared_at'] ?? null,
    ];
}

function mg_delivery_pause(PDO $pdo, string $reason, ?int $actorUserId = null): void
{
    $reason = mb_substr(trim($reason), 0, 500);
    $pdo->prepare('UPDATE mg_delivery_worker_state SET paused=1,pause_reason=?,paused_at=NOW(),paused_by_user_id=?,cleared_at=NULL,cleared_by_user_id=NULL WHERE id=1')
        ->execute([$reason !== '' ? $reason : 'Delivery worker safety threshold reached.', $actorUserId]);
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'delivery.worker.paused', 'Delivery worker paused by a safety control.', ['reason'=>$reason], $actorUserId);
    }
}

function mg_delivery_clear_pause(PDO $pdo, string $acknowledgement, int $actorUserId): array
{
    $config = mg_delivery_config();
    if (!hash_equals((string)$config['pause_acknowledgement'], trim($acknowledgement))) {
        throw new InvalidArgumentException('The exact delivery pause acknowledgement is required.');
    }
    $deadLetters = (int)$pdo->query("SELECT COUNT(*) FROM notification_delivery_jobs WHERE status='dead_letter'")->fetchColumn();
    if ($deadLetters > 0) throw new RuntimeException('Resolve or requeue all dead-letter jobs before clearing the worker pause.');
    $pdo->prepare('UPDATE mg_delivery_worker_state SET paused=0,pause_reason=NULL,cleared_at=NOW(),cleared_by_user_id=? WHERE id=1')->execute([$actorUserId]);
    if (function_exists('mg_audit')) mg_audit('delivery_worker_pause_cleared', 'system', ['actor_user_id'=>$actorUserId], $actorUserId);
    return mg_delivery_worker_state($pdo);
}

function mg_delivery_recover_expired_leases(PDO $pdo): array
{
    if (!mg_delivery_schema_ready($pdo)) return ['recovered'=>0,'dead_lettered'=>0];
    $rows = $pdo->query(
        "SELECT id,attempt_count,max_attempts
           FROM notification_delivery_jobs
          WHERE status='processing' AND lease_expires_at IS NOT NULL AND lease_expires_at<NOW()
          ORDER BY lease_expires_at,id LIMIT 500"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $counts = ['recovered'=>0,'dead_lettered'=>0];
    foreach ($rows as $row) {
        $attempt = (int)$row['attempt_count'] + 1;
        $maxAttempts = max(1, (int)$row['max_attempts']);
        $dead = $attempt >= $maxAttempts;
        $status = $dead ? 'dead_letter' : 'retry_scheduled';
        $stmt = $pdo->prepare(
            "UPDATE notification_delivery_jobs
                SET status=?,attempt_count=?,lease_token=NULL,lease_expires_at=NULL,
                    next_attempt_at=IF(?='retry_scheduled',NOW(),NULL),last_attempt_at=NOW(),
                    failure_code='lease_expired',failure_message='A prior worker lease expired before completion.',
                    failed_at=NOW(),dead_lettered_at=IF(?='dead_letter',COALESCE(dead_lettered_at,NOW()),dead_lettered_at),updated_at=NOW()
              WHERE id=? AND status='processing' AND lease_expires_at<NOW()"
        );
        $stmt->execute([$status,$attempt,$status,$status,(int)$row['id']]);
        if ($stmt->rowCount() !== 1) continue;
        $event = $dead ? 'dead_lettered' : 'retry_scheduled';
        mg_delivery_provider_event($pdo,(int)$row['id'],$event,[
            'provider'=>'worker','code'=>'lease_expired','message'=>'A prior worker lease expired before completion.','metadata'=>[]
        ],$attempt);
        $counts[$dead ? 'dead_lettered' : 'recovered']++;
    }
    return $counts;
}

function mg_delivery_backoff_seconds(int $attempt, array $config): int
{
    $attempt = max(1, $attempt);
    $base = (int)$config['retry_base_seconds'];
    $max = (int)$config['retry_max_seconds'];
    $raw = min($max, $base * (2 ** min(12, $attempt - 1)));
    $jitter = random_int(0, max(1, (int)floor($raw * 0.20)));
    return min($max, $raw + $jitter);
}
