<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/pwa-push.php';

function mg_stamp_receipt_notification_table_exists(PDO $pdo, string $table): bool
{
    if (function_exists('mg_pwa_push_table_exists')) return mg_pwa_push_table_exists($pdo, $table);
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_stamp_receipt_notification_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function mg_stamp_receipt_notification_uuid(): string
{
    return function_exists('mg_pwa_push_uuid') ? mg_pwa_push_uuid() : mg_public_uuid();
}

function mg_stamp_receipt_notification_url(array $purchase): string
{
    return '/stamp-receipt.php?purchase=' . rawurlencode((string)$purchase['public_id']);
}

function mg_stamp_receipt_notification_title(string $event): string
{
    return match ($event) {
        'created' => 'Stamp purchase started',
        'credited' => 'Stamp purchase credited',
        'failed' => 'Stamp purchase failed',
        'cancelled' => 'Stamp purchase cancelled',
        'receipt_sent' => 'Stamp receipt ready',
        default => 'Stamp purchase update',
    };
}

function mg_stamp_receipt_notification_body(array $purchase, string $event): string
{
    $label = trim((string)($purchase['label_snapshot'] ?? $purchase['bundle_key'] ?? 'Stamp bundle')) ?: 'Stamp bundle';
    $stamps = number_format((int)($purchase['stamps_snapshot'] ?? 0));
    $amount = strtoupper((string)($purchase['currency_snapshot'] ?? 'USD')) . ' ' . number_format(((int)($purchase['price_cents_snapshot'] ?? 0)) / 100, 2);
    return match ($event) {
        'created' => $label . ' checkout was created for ' . $stamps . ' Stamps at ' . $amount . '. Complete payment to receive ledger credit.',
        'credited' => $label . ' was paid and credited to your Stamp ledger. Receipt is ready.',
        'failed' => $label . ' could not be completed. Review checkout status or contact support.',
        'cancelled' => $label . ' checkout was cancelled. No Stamp credit was posted.',
        'receipt_sent' => 'Your receipt for ' . $label . ' is ready to view or print.',
        default => $label . ' Stamp purchase status changed.',
    };
}

function mg_stamp_receipt_notification_type(string $event): string
{
    return match ($event) {
        'created' => 'stamp_purchase_created',
        'credited' => 'stamp_purchase_credited',
        'failed' => 'stamp_purchase_failed',
        'cancelled' => 'stamp_purchase_cancelled',
        'receipt_sent' => 'stamp_purchase_receipt_sent',
        default => 'stamp_purchase_update',
    };
}

function mg_stamp_receipt_notify_merchant(PDO $pdo, array $purchase, string $event, int $actorUserId = 0, array $extra = []): array
{
    $merchantUserId = (int)($purchase['account_user_id'] ?? 0);
    if ($merchantUserId < 1) return ['created'=>false,'reason'=>'missing_merchant'];
    if (!mg_stamp_receipt_notification_table_exists($pdo, 'notifications')) return ['created'=>false,'reason'=>'notifications_table_missing'];

    $type = mg_stamp_receipt_notification_type($event);
    $title = mg_stamp_receipt_notification_title($event);
    $body = mg_stamp_receipt_notification_body($purchase, $event);
    $actionUrl = mg_stamp_receipt_notification_url($purchase);
    $eventKey = 'stamp_purchase:' . (string)$purchase['public_id'] . ':' . $event;
    $context = [
        'stamp_purchase_id' => (string)$purchase['public_id'],
        'bundle_key' => (string)($purchase['bundle_key'] ?? ''),
        'stamps' => (int)($purchase['stamps_snapshot'] ?? 0),
        'price_cents' => (int)($purchase['price_cents_snapshot'] ?? 0),
        'currency' => (string)($purchase['currency_snapshot'] ?? 'USD'),
        'receipt_url' => $actionUrl,
        'notification_surface' => 'merchant_notifications',
        'inbox_receipt' => false,
    ] + $extra;

    try {
        $hasEventKey = mg_stamp_receipt_notification_column_exists($pdo, 'notifications', 'event_key');
        $hasContext = mg_stamp_receipt_notification_column_exists($pdo, 'notifications', 'context_json');
        $hasUpdated = mg_stamp_receipt_notification_column_exists($pdo, 'notifications', 'updated_at');
        $hasActor = mg_stamp_receipt_notification_column_exists($pdo, 'notifications', 'actor_user_id');
        $publicId = mg_stamp_receipt_notification_uuid();
        if ($hasEventKey && $hasContext && $hasUpdated) {
            $columns = ['public_id','user_id'];
            $values = [$publicId, $merchantUserId];
            $placeholders = ['?','?'];
            if ($hasActor) { $columns[] = 'actor_user_id'; $values[] = $actorUserId > 0 ? $actorUserId : null; $placeholders[] = '?'; }
            array_push($columns, 'type','event_key','title','body','action_url','context_json','created_at','updated_at');
            array_push($values, $type, $eventKey, $title, $body, $actionUrl, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
            array_push($placeholders, '?','?','?','?','?','?','NOW()','NOW()');
            $sql = 'INSERT INTO notifications (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ') ON DUPLICATE KEY UPDATE title=VALUES(title),body=VALUES(body),action_url=VALUES(action_url),context_json=VALUES(context_json),occurrence_count=occurrence_count+1,read_at=NULL,updated_at=NOW()';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $notificationId = (int)$pdo->lastInsertId();
            if ($notificationId < 1) {
                $lookup = $pdo->prepare('SELECT id,public_id FROM notifications WHERE user_id=? AND event_key=? LIMIT 1');
                $lookup->execute([$merchantUserId, $eventKey]);
                $row = $lookup->fetch(PDO::FETCH_ASSOC) ?: [];
                $notificationId = (int)($row['id'] ?? 0);
                $publicId = (string)($row['public_id'] ?? $publicId);
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())');
            $stmt->execute([$publicId, $merchantUserId, $type, $title, $body, $actionUrl]);
            $notificationId = (int)$pdo->lastInsertId();
        }
        $push = ['queued'=>0,'reason'=>'not_available'];
        if ($notificationId > 0 && function_exists('mg_pwa_push_queue_for_notification')) $push = mg_pwa_push_queue_for_notification($pdo, $notificationId);
        return ['created'=>true,'notification_id'=>$publicId,'type'=>$type,'event'=>$event,'action_url'=>$actionUrl,'pwa_push'=>$push];
    } catch (Throwable $error) {
        mg_security_log('warning','stamps.receipt_notification_failed','Unable to create Stamp receipt notification.', ['purchase_id'=>(string)($purchase['public_id'] ?? ''),'event'=>$event,'exception_class'=>$error::class], $merchantUserId);
        return ['created'=>false,'reason'=>'notification_failed'];
    }
}
