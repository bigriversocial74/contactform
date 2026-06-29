<?php
declare(strict_types=1);

/**
 * Microgifter PWA push bridge.
 * Uses existing notifications + notification_delivery_jobs(channel='push') as the
 * source event/delivery layer and only adds browser subscription tracking.
 */

function mg_pwa_push_uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function mg_pwa_push_table_exists(PDO $pdo, string $table): bool
{
    if (function_exists('mg_admin_system_health_table_exists')) return mg_admin_system_health_table_exists($pdo, $table);
    if (preg_match('/^[a-z0-9_]{1,64}$/', $table) !== 1) throw new InvalidArgumentException('Invalid table name.');
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_pwa_push_config(): array
{
    $public = trim((string)(function_exists('mg_config_value') ? mg_config_value('pwa_push', 'vapid_public_key', '') : getenv('MG_PWA_VAPID_PUBLIC_KEY')));
    $private = trim((string)(function_exists('mg_config_value') ? mg_config_value('pwa_push', 'vapid_private_key', '') : getenv('MG_PWA_VAPID_PRIVATE_KEY')));
    $subject = trim((string)(function_exists('mg_config_value') ? mg_config_value('pwa_push', 'vapid_subject', '') : getenv('MG_PWA_VAPID_SUBJECT')));
    return [
        'enabled' => (bool)(function_exists('mg_config_value') ? mg_config_value('features', 'pwa_push', true) : true),
        'public_key' => $public,
        'private_key' => $private,
        'subject' => $subject !== '' ? $subject : 'mailto:admin@microgifter.com',
        'public_key_configured' => $public !== '',
        'private_key_configured' => $private !== '',
        'provider_available' => class_exists('\\Minishlink\\WebPush\\WebPush') && class_exists('\\Minishlink\\WebPush\\Subscription'),
        'service_worker_url' => '/sw.js',
        'service_worker_scope' => '/',
        'manifest_url' => '/manifest.webmanifest',
        'icon' => '/images/logo_main_drk.png',
        'badge' => '/images/logo_main_drk.png',
    ];
}

function mg_pwa_push_safe_internal_url(mixed $value, string $fallback = '/notifications.php'): string
{
    $url = trim((string)$value);
    if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//') || preg_match('/[\x00-\x1F\x7F]/', $url)) return $fallback;
    return mb_substr($url, 0, 500);
}

function mg_pwa_push_safe_push_text(mixed $value, string $fallback, int $limit): string
{
    $text = trim((string)$value);
    if ($text === '') return $fallback;
    $sensitive = preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text)
        || preg_match('/\b(?:\+?1[-.\s]?)?(?:\(?\d{3}\)?[-.\s]?)\d{3}[-.\s]?\d{4}\b/', $text)
        || preg_match('/\b(?:claim|code|token|secret|password|api key|gift id)\b/i', $text);
    if ($sensitive) return $fallback;
    return mb_substr($text, 0, $limit);
}

function mg_pwa_push_public_metadata(mixed $value, int $depth = 0): array
{
    if (!is_array($value) || $depth > 2) return [];
    $safe = [];
    foreach ($value as $key => $item) {
        $key = (string)$key;
        if ($key === '' || preg_match('/(token|secret|password|auth|cookie|session|csrf|claim|code|key|email|phone|address|private)/i', $key)) continue;
        if (is_scalar($item) || $item === null) $safe[$key] = is_string($item) ? mb_substr($item, 0, 160) : $item;
        elseif (is_array($item)) {
            $nested = mg_pwa_push_public_metadata($item, $depth + 1);
            if ($nested !== []) $safe[$key] = $nested;
        }
        if (count($safe) >= 8) break;
    }
    return $safe;
}

function mg_pwa_push_active_subscription_count(PDO $pdo, ?int $userId = null): int
{
    if (!mg_pwa_push_table_exists($pdo, 'pwa_push_subscriptions')) return 0;
    if ($userId !== null && $userId > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pwa_push_subscriptions WHERE user_id=? AND status='active'");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    return (int)$pdo->query("SELECT COUNT(*) FROM pwa_push_subscriptions WHERE status='active'")->fetchColumn();
}

function mg_pwa_push_user_status(PDO $pdo, int $userId): array
{
    $cfg = mg_pwa_push_config();
    $tablesReady = mg_pwa_push_table_exists($pdo, 'pwa_push_subscriptions') && mg_pwa_push_table_exists($pdo, 'pwa_notification_deliveries');
    return [
        'enabled' => (bool)$cfg['enabled'],
        'public_key' => (string)$cfg['public_key'],
        'public_key_configured' => (bool)$cfg['public_key_configured'],
        'provider_available' => (bool)$cfg['provider_available'],
        'service_worker_url' => (string)$cfg['service_worker_url'],
        'service_worker_scope' => (string)$cfg['service_worker_scope'],
        'manifest_url' => (string)$cfg['manifest_url'],
        'subscription_tables_ready' => $tablesReady,
        'active_subscriptions' => mg_pwa_push_active_subscription_count($pdo, $userId),
        'can_subscribe' => $tablesReady && (bool)$cfg['enabled'] && (bool)$cfg['public_key_configured'] && $userId > 0,
    ];
}

function mg_pwa_push_sanitize_subscription(array $subscription): array
{
    $endpoint = trim((string)($subscription['endpoint'] ?? ''));
    $host = parse_url($endpoint, PHP_URL_HOST);
    $scheme = strtolower((string)parse_url($endpoint, PHP_URL_SCHEME));
    $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    if ($endpoint === '' || strlen($endpoint) > 2000 || ($scheme !== 'https' && !($local && $scheme === 'http'))) throw new InvalidArgumentException('A valid HTTPS push endpoint is required.');
    $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
    $p256dh = trim((string)($keys['p256dh'] ?? ''));
    $auth = trim((string)($keys['auth'] ?? ''));
    if ($p256dh === '' || $auth === '' || strlen($p256dh) > 500 || strlen($auth) > 255) throw new InvalidArgumentException('Push subscription keys are required.');
    return ['endpoint' => $endpoint, 'expirationTime' => $subscription['expirationTime'] ?? null, 'keys' => ['p256dh' => $p256dh, 'auth' => $auth]];
}

function mg_pwa_push_register_subscription(PDO $pdo, int $userId, array $subscription, string $userAgent = ''): array
{
    if ($userId < 1) throw new InvalidArgumentException('Authenticated user is required.');
    if (!mg_pwa_push_table_exists($pdo, 'pwa_push_subscriptions')) throw new RuntimeException('PWA push subscriptions table is not installed.');
    $safe = mg_pwa_push_sanitize_subscription($subscription);
    $hash = hash('sha256', $safe['endpoint']);
    $json = json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("INSERT INTO pwa_push_subscriptions (public_id,user_id,endpoint_hash,endpoint_url,subscription_json,user_agent_hash,status,subscribed_at,last_seen_at,created_at,updated_at) VALUES (?,?,?,?,?,?,'active',NOW(),NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE endpoint_url=VALUES(endpoint_url),subscription_json=VALUES(subscription_json),user_agent_hash=VALUES(user_agent_hash),status='active',revoked_at=NULL,failed_at=NULL,last_error_code=NULL,last_error_message=NULL,last_seen_at=NOW(),updated_at=NOW()");
    $stmt->execute([mg_pwa_push_uuid(), $userId, $hash, $safe['endpoint'], $json, $userAgent !== '' ? hash('sha256', $userAgent) : null]);
    return ['registered' => true, 'active_subscriptions' => mg_pwa_push_active_subscription_count($pdo, $userId)];
}

function mg_pwa_push_deactivate_subscription(PDO $pdo, int $userId, ?array $subscription = null): array
{
    if (!mg_pwa_push_table_exists($pdo, 'pwa_push_subscriptions')) return ['unsubscribed' => false, 'active_subscriptions' => 0];
    $params = [$userId];
    $where = 'user_id=?';
    if (is_array($subscription) && isset($subscription['endpoint'])) { $where .= ' AND endpoint_hash=?'; $params[] = hash('sha256', trim((string)$subscription['endpoint'])); }
    $stmt = $pdo->prepare("UPDATE pwa_push_subscriptions SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE {$where} AND status='active'");
    $stmt->execute($params);
    return ['unsubscribed' => $stmt->rowCount() > 0, 'active_subscriptions' => mg_pwa_push_active_subscription_count($pdo, $userId)];
}

function mg_pwa_push_notification_payload(array $n): array
{
    $cfg = mg_pwa_push_config();
    $context = [];
    if (!empty($n['context_json'])) {
        $decoded = json_decode((string)$n['context_json'], true);
        $context = mg_pwa_push_public_metadata($decoded);
    }
    return [
        'title' => mg_pwa_push_safe_push_text($n['title'] ?? 'Microgifter update', 'Microgifter update', 90),
        'body' => mg_pwa_push_safe_push_text($n['body'] ?? 'Open Microgifter for details.', 'Open Microgifter for details.', 180),
        'notification_type' => mb_substr(trim((string)($n['type'] ?? 'system')), 0, 80),
        'notification_id' => (string)($n['public_id'] ?? ''),
        'action_url' => mg_pwa_push_safe_internal_url($n['action_url'] ?? '/notifications.php'),
        'icon' => (string)$cfg['icon'],
        'badge' => (string)$cfg['badge'],
        'metadata' => $context,
        'created_at' => $n['created_at'] ?? gmdate('c'),
    ];
}

function mg_pwa_push_queue_for_notification(PDO $pdo, int $notificationId): array
{
    if ($notificationId < 1) return ['queued' => 0, 'suppressed' => 0, 'reason' => 'invalid_notification'];
    if (!mg_pwa_push_config()['enabled']) return ['queued' => 0, 'suppressed' => 1, 'reason' => 'pwa_push_disabled'];
    foreach (['notifications','notification_delivery_jobs','pwa_push_subscriptions','pwa_notification_deliveries'] as $table) if (!mg_pwa_push_table_exists($pdo, $table)) return ['queued' => 0, 'suppressed' => 0, 'reason' => 'missing_' . $table];
    $stmt = $pdo->prepare('SELECT id,public_id,user_id,type,title,body,action_url,context_json,created_at FROM notifications WHERE id=? LIMIT 1');
    $stmt->execute([$notificationId]);
    $n = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$n) return ['queued' => 0, 'suppressed' => 0, 'reason' => 'notification_not_found'];
    $userId = (int)$n['user_id'];
    $type = (string)$n['type'];
    if (mg_pwa_push_table_exists($pdo, 'notification_preferences')) {
        $pref = $pdo->prepare('SELECT push_enabled,digest_mode FROM notification_preferences WHERE user_id=? AND notification_type=? LIMIT 1');
        $pref->execute([$userId, $type]);
        $row = $pref->fetch(PDO::FETCH_ASSOC) ?: [];
        if (array_key_exists('push_enabled', $row) && (int)$row['push_enabled'] !== 1) return ['queued' => 0, 'suppressed' => 1, 'reason' => 'push_disabled'];
        if (($row['digest_mode'] ?? 'immediate') === 'off') return ['queued' => 0, 'suppressed' => 1, 'reason' => 'digest_off'];
    }
    $subs = $pdo->prepare("SELECT id,endpoint_hash FROM pwa_push_subscriptions WHERE user_id=? AND status='active' ORDER BY last_seen_at DESC,id DESC");
    $subs->execute([$userId]);
    $subscriptions = $subs->fetchAll(PDO::FETCH_ASSOC);
    if (!$subscriptions) return ['queued' => 0, 'suppressed' => 0, 'reason' => 'no_active_subscription'];
    $payloadJson = json_encode(mg_pwa_push_notification_payload($n), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    $exists = $pdo->prepare('SELECT id FROM pwa_notification_deliveries WHERE notification_id=? AND subscription_id=? LIMIT 1');
    $job = $pdo->prepare("INSERT INTO notification_delivery_jobs (public_id,notification_id,user_id,channel,destination_hash,status,provider,next_attempt_at,created_at,updated_at) VALUES (?,?,?,?,?,'queued','pwa_push',NOW(),NOW(),NOW())");
    $delivery = $pdo->prepare("INSERT INTO pwa_notification_deliveries (public_id,notification_id,delivery_job_id,subscription_id,user_id,endpoint_hash,payload_json,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'queued',NOW(),NOW())");
    $queued = 0;
    foreach ($subscriptions as $sub) {
        $exists->execute([(int)$n['id'], (int)$sub['id']]);
        if ($exists->fetchColumn()) continue;
        $job->execute([mg_pwa_push_uuid(), (int)$n['id'], $userId, 'push', (string)$sub['endpoint_hash']]);
        $delivery->execute([mg_pwa_push_uuid(), (int)$n['id'], (int)$pdo->lastInsertId(), (int)$sub['id'], $userId, (string)$sub['endpoint_hash'], $payloadJson]);
        $queued++;
    }
    return ['queued' => $queued, 'suppressed' => 0, 'reason' => $queued > 0 ? 'queued' : 'already_queued'];
}

function mg_pwa_push_queue_recent_for_user(PDO $pdo, int $userId, int $limit = 10): array
{
    if ($userId < 1 || !mg_pwa_push_table_exists($pdo, 'notifications')) return ['queued' => 0, 'checked' => 0];
    $stmt = $pdo->prepare('SELECT id FROM notifications WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT ' . max(1, min(50, $limit)));
    $stmt->execute([$userId]);
    $queued = 0; $checked = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) { $checked++; $queued += (int)(mg_pwa_push_queue_for_notification($pdo, (int)$id)['queued'] ?? 0); }
    return ['queued' => $queued, 'checked' => $checked];
}

function mg_pwa_push_create_user_notification(PDO $pdo, int $userId, string $type, string $title, string $body, string $actionUrl = '/notifications.php'): array
{
    if ($userId < 1 || !mg_pwa_push_table_exists($pdo, 'notifications')) return ['created' => false, 'reason' => 'missing_user_or_table'];
    $publicId = mg_pwa_push_uuid();
    $stmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->execute([$publicId, $userId, mb_substr($type, 0, 80), mb_substr($title, 0, 180), mb_substr($body, 0, 1000), mg_pwa_push_safe_internal_url($actionUrl)]);
    $queued = mg_pwa_push_queue_for_notification($pdo, (int)$pdo->lastInsertId());
    return ['created' => true, 'notification_id' => $publicId, 'queued' => $queued];
}

function mg_pwa_push_attempt_delivery(PDO $pdo, int $deliveryId): array
{
    $stmt = $pdo->prepare("SELECT d.*,s.subscription_json,s.status subscription_status FROM pwa_notification_deliveries d INNER JOIN pwa_push_subscriptions s ON s.id=d.subscription_id WHERE d.id=? LIMIT 1");
    $stmt->execute([$deliveryId]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$d) return ['sent' => false, 'reason' => 'delivery_not_found'];
    if (($d['subscription_status'] ?? '') !== 'active') {
        $pdo->prepare("UPDATE pwa_notification_deliveries SET status='suppressed',last_attempt_at=NOW(),failure_code='subscription_inactive',failure_message='Subscription is not active.',updated_at=NOW() WHERE id=?")->execute([$deliveryId]);
        $pdo->prepare("UPDATE notification_delivery_jobs SET status='suppressed',attempt_count=attempt_count+1,failure_code='subscription_inactive',failure_message='Subscription is not active.',updated_at=NOW() WHERE id=?")->execute([(int)$d['delivery_job_id']]);
        return ['sent' => false, 'reason' => 'subscription_inactive'];
    }
    $cfg = mg_pwa_push_config();
    if (!$cfg['enabled'] || !$cfg['public_key_configured'] || !$cfg['private_key_configured'] || !$cfg['provider_available']) {
        $code = !$cfg['provider_available'] ? 'provider_missing' : 'config_missing';
        $msg = !$cfg['provider_available'] ? 'PHP WebPush provider library is not installed.' : 'PWA VAPID keys are not configured.';
        $pdo->prepare("UPDATE pwa_notification_deliveries SET status='failed',last_attempt_at=NOW(),failed_at=NOW(),failure_code=?,failure_message=?,updated_at=NOW() WHERE id=?")->execute([$code, $msg, $deliveryId]);
        $pdo->prepare("UPDATE notification_delivery_jobs SET status='failed',attempt_count=attempt_count+1,failed_at=NOW(),failure_code=?,failure_message=?,updated_at=NOW() WHERE id=?")->execute([$code, $msg, (int)$d['delivery_job_id']]);
        return ['sent' => false, 'reason' => $code];
    }
    try {
        $subData = json_decode((string)$d['subscription_json'], true);
        if (!is_array($subData)) throw new RuntimeException('Invalid subscription payload.');
        $sub = \Minishlink\WebPush\Subscription::create($subData);
        $webPush = new \Minishlink\WebPush\WebPush(['VAPID' => ['subject' => (string)$cfg['subject'], 'publicKey' => (string)$cfg['public_key'], 'privateKey' => (string)$cfg['private_key']]]);
        $report = $webPush->sendOneNotification($sub, (string)$d['payload_json']);
        if ($report->isSuccess()) {
            $pdo->prepare("UPDATE pwa_notification_deliveries SET status='sent',attempt_count=attempt_count+1,sent_at=COALESCE(sent_at,NOW()),last_attempt_at=NOW(),failure_code=NULL,failure_message=NULL,updated_at=NOW() WHERE id=?")->execute([$deliveryId]);
            $pdo->prepare("UPDATE notification_delivery_jobs SET status='sent',attempt_count=attempt_count+1,sent_at=COALESCE(sent_at,NOW()),failure_code=NULL,failure_message=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$d['delivery_job_id']]);
            return ['sent' => true, 'reason' => 'sent'];
        }
        $reason = method_exists($report, 'getReason') ? (string)$report->getReason() : 'Push service rejected notification.';
        $gone = method_exists($report, 'isSubscriptionExpired') && $report->isSubscriptionExpired();
        $code = $gone ? 'subscription_expired' : 'provider_rejected';
        $pdo->prepare("UPDATE pwa_notification_deliveries SET status='failed',attempt_count=attempt_count+1,failed_at=NOW(),last_attempt_at=NOW(),failure_code=?,failure_message=?,updated_at=NOW() WHERE id=?")->execute([$code, mb_substr($reason, 0, 500), $deliveryId]);
        $pdo->prepare("UPDATE notification_delivery_jobs SET status='failed',attempt_count=attempt_count+1,failed_at=NOW(),failure_code=?,failure_message=?,updated_at=NOW() WHERE id=?")->execute([$code, mb_substr($reason, 0, 500), (int)$d['delivery_job_id']]);
        if ($gone) $pdo->prepare("UPDATE pwa_push_subscriptions SET status='expired',failed_at=NOW(),last_error_code='subscription_expired',last_error_message=?,updated_at=NOW() WHERE id=?")->execute([mb_substr($reason, 0, 500), (int)$d['subscription_id']]);
        return ['sent' => false, 'reason' => $code];
    } catch (Throwable $e) {
        $msg = mb_substr($e->getMessage(), 0, 500);
        $pdo->prepare("UPDATE pwa_notification_deliveries SET status='failed',attempt_count=attempt_count+1,failed_at=NOW(),last_attempt_at=NOW(),failure_code='exception',failure_message=?,updated_at=NOW() WHERE id=?")->execute([$msg, $deliveryId]);
        $pdo->prepare("UPDATE notification_delivery_jobs SET status='failed',attempt_count=attempt_count+1,failed_at=NOW(),failure_code='exception',failure_message=?,updated_at=NOW() WHERE id=?")->execute([$msg, (int)$d['delivery_job_id']]);
        return ['sent' => false, 'reason' => 'exception'];
    }
}

function mg_pwa_push_send_pending(PDO $pdo, int $limit = 25): array
{
    if (!mg_pwa_push_table_exists($pdo, 'pwa_notification_deliveries')) return ['checked' => 0, 'sent' => 0, 'failed' => 0];
    $ids = $pdo->query('SELECT id FROM pwa_notification_deliveries WHERE status=\'queued\' ORDER BY created_at,id LIMIT ' . max(1, min(250, $limit)))->fetchAll(PDO::FETCH_COLUMN);
    $sent = 0; $failed = 0;
    foreach ($ids as $id) { $result = mg_pwa_push_attempt_delivery($pdo, (int)$id); if (!empty($result['sent'])) $sent++; else $failed++; }
    return ['checked' => count($ids), 'sent' => $sent, 'failed' => $failed];
}

function mg_pwa_push_mark_opened(PDO $pdo, int $userId, string $publicId): array
{
    if ($userId < 1 || preg_match('/^[a-f0-9-]{20,60}$/i', $publicId) !== 1) return ['opened' => false, 'reason' => 'invalid_request'];
    $stmt = $pdo->prepare('SELECT id FROM notifications WHERE public_id=? AND user_id=? LIMIT 1');
    $stmt->execute([$publicId, $userId]);
    $notificationId = (int)($stmt->fetchColumn() ?: 0);
    if ($notificationId < 1) return ['opened' => false, 'reason' => 'not_found'];
    $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND user_id=?')->execute([$notificationId, $userId]);
    if (mg_pwa_push_table_exists($pdo, 'pwa_notification_deliveries')) $pdo->prepare("UPDATE pwa_notification_deliveries SET status='opened',opened_at=COALESCE(opened_at,NOW()),updated_at=NOW() WHERE notification_id=? AND user_id=?")->execute([$notificationId, $userId]);
    return ['opened' => true];
}

function mg_pwa_push_health(PDO $pdo): array
{
    $cfg = mg_pwa_push_config();
    $hasSubs = mg_pwa_push_table_exists($pdo, 'pwa_push_subscriptions');
    $hasDeliveries = mg_pwa_push_table_exists($pdo, 'pwa_notification_deliveries');
    $last = null;
    if ($hasDeliveries) {
        $row = $pdo->query("SELECT d.status,d.failure_code,d.failure_message,d.sent_at,d.failed_at,d.opened_at,d.created_at,n.title,n.type FROM pwa_notification_deliveries d INNER JOIN notifications n ON n.id=d.notification_id WHERE n.type='admin_pwa_test' ORDER BY d.created_at DESC,d.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) $last = ['status' => (string)$row['status'], 'type' => (string)$row['type'], 'title' => (string)$row['title'], 'failure_code' => $row['failure_code'] ?? null, 'failure_message' => isset($row['failure_message']) ? mb_substr((string)$row['failure_message'], 0, 180) : null, 'sent_at' => $row['sent_at'] ?? null, 'failed_at' => $row['failed_at'] ?? null, 'opened_at' => $row['opened_at'] ?? null, 'created_at' => $row['created_at'] ?? null];
    }
    return [
        'enabled' => (bool)$cfg['enabled'],
        'service_worker_url' => (string)$cfg['service_worker_url'],
        'manifest_url' => (string)$cfg['manifest_url'],
        'service_worker_file_present' => is_file(dirname(__DIR__) . '/sw.js'),
        'subscription_tables_ready' => $hasSubs && $hasDeliveries,
        'vapid_public_key_configured' => (bool)$cfg['public_key_configured'],
        'vapid_private_key_configured' => (bool)$cfg['private_key_configured'],
        'provider_available' => (bool)$cfg['provider_available'],
        'active_subscriptions_count' => $hasSubs ? mg_pwa_push_active_subscription_count($pdo) : 0,
        'failed_delivery_count' => $hasDeliveries ? (int)$pdo->query("SELECT COUNT(*) FROM pwa_notification_deliveries WHERE status='failed'")->fetchColumn() : 0,
        'last_test_notification_result' => $last,
    ];
}

function mg_pwa_push_send_test_to_user(PDO $pdo, int $userId): array
{
    $active = mg_pwa_push_active_subscription_count($pdo, $userId);
    if ($active < 1) return ['created' => false, 'queued' => 0, 'active_subscriptions' => 0, 'reason' => 'no_active_subscription'];
    $created = mg_pwa_push_create_user_notification($pdo, $userId, 'admin_pwa_test', 'Microgifter PWA test', 'Your browser push notification channel is connected.', '/notifications.php');
    $attempts = !empty($created['created']) ? mg_pwa_push_send_pending($pdo, min(10, max(1, $active))) : ['checked' => 0, 'sent' => 0, 'failed' => 0];
    return ['created' => !empty($created['created']), 'notification_id' => $created['notification_id'] ?? null, 'queued' => (int)($created['queued']['queued'] ?? 0), 'active_subscriptions' => $active, 'attempts' => $attempts, 'provider_available' => (bool)mg_pwa_push_config()['provider_available']];
}
