<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

function mg_notification_preference(PDO $pdo, int $userId, string $type): array
{
    $stmt = $pdo->prepare('SELECT * FROM notification_preferences WHERE user_id=? AND notification_type=? LIMIT 1');
    $stmt->execute([$userId, $type]);
    return $stmt->fetch() ?: [
        'in_app_enabled' => 1,
        'email_enabled' => 1,
        'sms_enabled' => 0,
        'push_enabled' => 1,
        'digest_mode' => 'immediate',
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

function mg_queue_notification_deliveries(PDO $pdo, int $notificationId, int $userId, string $type): void
{
    $preference = mg_notification_preference($pdo, $userId, $type);
    $channels = [
        'in_app' => !empty($preference['in_app_enabled']),
        'email' => !empty($preference['email_enabled']),
        'sms' => !empty($preference['sms_enabled']),
        'push' => !empty($preference['push_enabled']),
    ];
    $insert = $pdo->prepare("INSERT INTO notification_delivery_jobs (public_id,notification_id,user_id,channel,status,next_attempt_at,created_at,updated_at) VALUES (?,?,?,?,'queued',NOW(),NOW(),NOW())");
    foreach ($channels as $channel => $enabled) {
        if (!$enabled || ($preference['digest_mode'] ?? 'immediate') === 'off') continue;
        $insert->execute([mg_public_uuid(), $notificationId, $userId, $channel]);
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
    $publicId = mg_public_uuid();
    $stmt = $pdo->prepare(
        'INSERT INTO notifications
         (public_id,user_id,type,title,body,action_url,gift_id,pppm_item_id,thread_id,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW())'
    );
    $stmt->execute([
        $publicId,
        $userId,
        $type,
        mb_substr(trim($title), 0, 160),
        $body !== null ? mb_substr(trim($body), 0, 500) : null,
        $actionUrl !== null ? mb_substr(trim($actionUrl), 0, 255) : null,
        $context['gift_id'] ?? null,
        $context['pppm_item_id'] ?? null,
        $context['thread_id'] ?? null,
    ]);
    mg_queue_notification_deliveries($pdo, (int)$pdo->lastInsertId(), $userId, $type);
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
