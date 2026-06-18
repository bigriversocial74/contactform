<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

function mg_notification_preference(PDO $pdo, int $userId, string $type): array
{
    $stmt = $pdo->prepare('SELECT * FROM notification_preferences WHERE user_id=? AND notification_type=? LIMIT 1');
    $stmt->execute([$userId, $type]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'in_app_enabled' => 1,
        'email_enabled' => 1,
        'sms_enabled' => 0,
        'push_enabled' => 1,
        'digest_mode' => 'immediate',
        'quiet_hours_start' => null,
        'quiet_hours_end' => null,
        'timezone' => 'UTC',
    ];
}

function mg_notification_user_label(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT display_name,full_name,email FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $label = trim((string)($row['display_name'] ?? $row['full_name'] ?? $row['email'] ?? 'Microgifter member'));
    return $label !== '' ? mb_substr($label, 0, 120) : 'Microgifter member';
}

function mg_notification_recipient_is_active(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id=? AND status='active' LIMIT 1");
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

function mg_notification_safe_action_url(?string $value): ?string
{
    $url = trim((string)$value);
    if ($url === '') return null;
    if (mb_strlen($url) > 255 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
        throw new InvalidArgumentException('Invalid notification action URL.');
    }
    if ($url[0] !== '/' || str_starts_with($url, '//')) {
        throw new InvalidArgumentException('Notification actions must use an internal URL.');
    }
    return $url;
}

function mg_notification_event_key(mixed $value): ?string
{
    $key = strtolower(trim((string)$value));
    if ($key === '') return null;
    if (strlen($key) > 190 || preg_match('/^[a-z0-9][a-z0-9:._-]*$/', $key) !== 1) {
        throw new InvalidArgumentException('Invalid notification event key.');
    }
    return $key;
}

function mg_notification_enabled_channels(array $preference): array
{
    if (($preference['digest_mode'] ?? 'immediate') === 'off') return [];
    $channels = [];
    foreach ([
        'in_app' => 'in_app_enabled',
        'email' => 'email_enabled',
        'sms' => 'sms_enabled',
        'push' => 'push_enabled',
    ] as $channel => $field) {
        if (!empty($preference[$field])) $channels[] = $channel;
    }
    return $channels;
}

function mg_notification_timezone(array $preference): DateTimeZone
{
    try {
        return new DateTimeZone(trim((string)($preference['timezone'] ?? 'UTC')) ?: 'UTC');
    } catch (Throwable) {
        return new DateTimeZone('UTC');
    }
}

function mg_notification_apply_quiet_hours(DateTimeImmutable $candidate, array $preference): DateTimeImmutable
{
    $start = trim((string)($preference['quiet_hours_start'] ?? ''));
    $end = trim((string)($preference['quiet_hours_end'] ?? ''));
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $start, $startMatch) !== 1) return $candidate;
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $end, $endMatch) !== 1) return $candidate;

    $startToday = $candidate->setTime((int)$startMatch[1], (int)$startMatch[2], 0);
    $endToday = $candidate->setTime((int)$endMatch[1], (int)$endMatch[2], 0);
    if ($startToday == $endToday) return $candidate;

    if ($startToday < $endToday) {
        if ($candidate >= $startToday && $candidate < $endToday) return $endToday;
        return $candidate;
    }

    if ($candidate >= $startToday) return $endToday->modify('+1 day');
    if ($candidate < $endToday) return $endToday;
    return $candidate;
}

function mg_notification_delivery_time(array $preference, string $channel): string
{
    $timezone = mg_notification_timezone($preference);
    $now = new DateTimeImmutable('now', $timezone);
    if ($channel === 'in_app') return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $mode = (string)($preference['digest_mode'] ?? 'immediate');
    $candidate = $now;
    if ($mode === 'hourly') {
        $candidate = $now->modify('+1 hour')->setTime((int)$now->modify('+1 hour')->format('H'), 0, 0);
    } elseif ($mode === 'daily') {
        $today = $now->setTime(8, 0, 0);
        $candidate = $now < $today ? $today : $today->modify('+1 day');
    } elseif ($mode === 'weekly') {
        $today = $now->setTime(8, 0, 0);
        if ((int)$now->format('N') === 1 && $now < $today) $candidate = $today;
        else $candidate = $now->modify('next monday')->setTime(8, 0, 0);
    }
    $candidate = mg_notification_apply_quiet_hours($candidate, $preference);
    return $candidate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function mg_queue_notification_deliveries(PDO $pdo, int $notificationId, int $userId, string $type, ?array $preference = null): void
{
    $preference ??= mg_notification_preference($pdo, $userId, $type);
    $channels = mg_notification_enabled_channels($preference);
    if ($channels === []) return;

    $insert = $pdo->prepare(
        "INSERT INTO notification_delivery_jobs
         (public_id,notification_id,user_id,channel,status,next_attempt_at,created_at,updated_at)
         VALUES (?,?,?,?,'queued',?,NOW(),NOW())"
    );
    foreach ($channels as $channel) {
        $insert->execute([
            mg_public_uuid(),
            $notificationId,
            $userId,
            $channel,
            mg_notification_delivery_time($preference, $channel),
        ]);
    }
}

function mg_create_notification(
    PDO $pdo,
    int $userId,
    string $type,
    string $title,
    ?string $body = null,
    ?string $actionUrl = null,
    array $context = []
): string {
    if ($userId < 1) throw new InvalidArgumentException('Notification recipient is required.');
    $type = strtolower(trim($type));
    if ($type === '' || mb_strlen($type) > 80) throw new InvalidArgumentException('Invalid notification type.');

    $actorUserId = isset($context['actor_user_id']) ? (int)$context['actor_user_id'] : null;
    if ($actorUserId !== null && $actorUserId < 1) $actorUserId = null;
    if ($actorUserId === $userId && empty($context['allow_self'])) return '';
    if (!mg_notification_recipient_is_active($pdo, $userId)) return '';

    $preference = mg_notification_preference($pdo, $userId, $type);
    if (mg_notification_enabled_channels($preference) === []) return '';

    $title = mb_substr(trim($title), 0, 160);
    if ($title === '') throw new InvalidArgumentException('Notification title is required.');
    $body = $body !== null ? mb_substr(trim($body), 0, 500) : null;
    $actionUrl = mg_notification_safe_action_url($actionUrl);
    $eventKey = mg_notification_event_key($context['event_key'] ?? null);
    $aggregate = !empty($context['aggregate']);

    $storedContext = $context;
    unset($storedContext['event_key'], $storedContext['aggregate'], $storedContext['allow_self']);
    $contextJson = $storedContext !== []
        ? json_encode($storedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        : null;

    $publicId = mg_public_uuid();
    if ($eventKey !== null) {
        $sql = $aggregate
            ? "INSERT INTO notifications
               (public_id,user_id,actor_user_id,type,event_key,occurrence_count,title,body,action_url,gift_id,pppm_item_id,thread_id,context_json,created_at,updated_at)
               VALUES (?,?,?,?,?,1,?,?,?,?,?,?,?,NOW(),NOW())
               ON DUPLICATE KEY UPDATE
                 id=LAST_INSERT_ID(id),actor_user_id=VALUES(actor_user_id),title=VALUES(title),body=VALUES(body),
                 action_url=VALUES(action_url),gift_id=VALUES(gift_id),pppm_item_id=VALUES(pppm_item_id),
                 thread_id=VALUES(thread_id),context_json=VALUES(context_json),occurrence_count=occurrence_count+1,
                 read_at=NULL,created_at=NOW(),updated_at=NOW()"
            : "INSERT INTO notifications
               (public_id,user_id,actor_user_id,type,event_key,occurrence_count,title,body,action_url,gift_id,pppm_item_id,thread_id,context_json,created_at,updated_at)
               VALUES (?,?,?,?,?,1,?,?,?,?,?,?,?,NOW(),NOW())
               ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $publicId,
            $userId,
            $actorUserId,
            $type,
            $eventKey,
            $title,
            $body,
            $actionUrl,
            $context['gift_id'] ?? null,
            $context['pppm_item_id'] ?? null,
            $context['thread_id'] ?? null,
            $contextJson,
        ]);
        $notificationId = (int)$pdo->lastInsertId();
        $changed = $stmt->rowCount() > 0;
        $lookup = $pdo->prepare('SELECT public_id FROM notifications WHERE id=? LIMIT 1');
        $lookup->execute([$notificationId]);
        $publicId = (string)($lookup->fetchColumn() ?: $publicId);
        if ($changed) mg_queue_notification_deliveries($pdo, $notificationId, $userId, $type, $preference);
        return $publicId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO notifications
         (public_id,user_id,actor_user_id,type,event_key,occurrence_count,title,body,action_url,gift_id,pppm_item_id,thread_id,context_json,created_at,updated_at)
         VALUES (?,?,?, ?,NULL,1,?,?,?,?,?,?,?,NOW(),NOW())'
    );
    $stmt->execute([
        $publicId,
        $userId,
        $actorUserId,
        $type,
        $title,
        $body,
        $actionUrl,
        $context['gift_id'] ?? null,
        $context['pppm_item_id'] ?? null,
        $context['thread_id'] ?? null,
        $contextJson,
    ]);
    mg_queue_notification_deliveries($pdo, (int)$pdo->lastInsertId(), $userId, $type, $preference);
    return $publicId;
}

function mg_create_operational_alert(PDO $pdo, int $userId, string $type, string $severity, string $title, ?string $body = null, ?string $actionUrl = null, array $context = []): string
{
    if (!in_array($severity, ['info','warning','high','critical'], true)) $severity = 'info';
    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO operational_alerts (public_id,merchant_user_id,user_id,alert_type,severity,status,title,body,action_url,gift_id,pppm_item_id,distribution_program_id,claim_id,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,'open',?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([
            $publicId,
            $context['merchant_user_id'] ?? null,
            $userId,
            $type,
            $severity,
            mb_substr($title, 0, 180),
            $body !== null ? mb_substr($body, 0, 1000) : null,
            $actionUrl,
            $context['gift_id'] ?? null,
            $context['pppm_item_id'] ?? null,
            $context['distribution_program_id'] ?? null,
            $context['claim_id'] ?? null,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : null,
        ]);
    return $publicId;
}
