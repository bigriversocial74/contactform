<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_merchant_notification_filter(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['all','unread','tips','messages','rewards','redemptions'], true) ? $value : 'all';
}

function mg_merchant_notification_limit(mixed $value): int
{
    return max(1, min(100, (int)($value ?: 50)));
}

function mg_merchant_notification_decode(?string $json): array
{
    $data = json_decode((string)$json, true);
    return is_array($data) ? $data : [];
}

function mg_merchant_notification_kind(string $type, array $context = []): string
{
    $type = strtolower(trim($type));
    if (str_contains($type, 'tip')) return 'tips';
    if (str_contains($type, 'message')) return 'messages';
    if (str_contains($type, 'redeem') || str_contains($type, 'claim')) return 'redemptions';
    if (str_contains($type, 'wallet') || str_contains($type, 'reward') || str_contains($type, 'campaign')) return 'rewards';
    if (!empty($context['wallet_item_id']) || !empty($context['campaign_id'])) return 'rewards';
    return 'all';
}

function mg_merchant_notification_action_url(string $type, ?string $storedUrl, array $context = []): string
{
    $type = strtolower(trim($type));
    $walletItemId = trim((string)($context['wallet_item_id'] ?? ''));
    $campaignId = trim((string)($context['campaign_id'] ?? ''));
    $threadId = trim((string)($context['thread_id'] ?? $context['conversation_key'] ?? ''));
    $tipId = trim((string)($context['tip_id'] ?? ''));
    if (str_contains($type, 'message')) {
        if ($threadId !== '') return '/messages.php?thread=' . rawurlencode($threadId);
        if ($walletItemId !== '') return '/merchant-notifications.php?filter=messages&item=' . rawurlencode($walletItemId);
    }
    if (str_contains($type, 'tip')) {
        return '/merchant-notifications.php?filter=tips' . ($tipId !== '' ? '&tip=' . rawurlencode($tipId) : '');
    }
    if (str_contains($type, 'redeem') || str_contains($type, 'claim')) return '/merchant-claims.php';
    if ($campaignId !== '') return '/merchant-campaigns.php?campaign=' . rawurlencode($campaignId);
    if ($walletItemId !== '') return '/merchant-notifications.php?filter=rewards&item=' . rawurlencode($walletItemId);
    $storedUrl = trim((string)$storedUrl);
    return ($storedUrl !== '' && str_starts_with($storedUrl, '/') && !str_starts_with($storedUrl, '//')) ? $storedUrl : '/merchant-notifications.php';
}

function mg_merchant_notification_alert_row(array $row): array
{
    $context = mg_merchant_notification_decode($row['metadata_json'] ?? null);
    $type = (string)($row['alert_type'] ?? 'operational_alert');
    $status = (string)($row['status'] ?? 'open');
    return [
        'source' => 'alert',
        'id' => (string)$row['public_id'],
        'type' => $type,
        'kind' => mg_merchant_notification_kind($type, $context),
        'status' => $status,
        'is_unread' => in_array($status, ['open'], true),
        'severity' => (string)($row['severity'] ?? 'info'),
        'title' => (string)($row['title'] ?? 'Merchant alert'),
        'body' => (string)($row['body'] ?? ''),
        'action_url' => mg_merchant_notification_action_url($type, $row['action_url'] ?? null, $context),
        'context' => $context,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
    ];
}

function mg_merchant_notification_notification_row(array $row): array
{
    $context = mg_merchant_notification_decode($row['context_json'] ?? null);
    $type = (string)($row['type'] ?? 'notification');
    return [
        'source' => 'notification',
        'id' => (string)$row['public_id'],
        'type' => $type,
        'kind' => mg_merchant_notification_kind($type, $context),
        'status' => empty($row['read_at']) ? 'unread' : 'read',
        'is_unread' => empty($row['read_at']),
        'severity' => 'info',
        'title' => (string)($row['title'] ?? 'Merchant notification'),
        'body' => (string)($row['body'] ?? ''),
        'action_url' => mg_merchant_notification_action_url($type, $row['action_url'] ?? null, $context),
        'context' => $context,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
    ];
}

function mg_merchant_notification_type_filter(string $filter, string $column): string
{
    return match($filter) {
        'tips' => " AND {$column} IN ('tip_received','tip_refunded','tip_disputed','tip_dispute_won','tip_dispute_lost','tip_chargeback','tip_recovery')",
        'messages' => " AND {$column} IN ('wallet_reward_message','microgift_message','gift_message')",
        'redemptions' => " AND ({$column} LIKE '%redeem%' OR {$column} LIKE '%claim%')",
        'rewards' => " AND ({$column} LIKE '%wallet%' OR {$column} LIKE '%reward%' OR {$column} LIKE '%campaign%')",
        default => '',
    };
}

function mg_merchant_notifications_fetch(PDO $pdo, int $merchantUserId, string $filter, int $limit): array
{
    $alerts = [];
    $notifications = [];
    try {
        $alertWhere = '(merchant_user_id=? OR user_id=?)' . mg_merchant_notification_type_filter($filter, 'alert_type');
        if ($filter === 'unread') $alertWhere .= " AND status='open'";
        $stmt = $pdo->prepare("SELECT public_id,alert_type,severity,status,title,body,action_url,metadata_json,created_at,updated_at FROM operational_alerts WHERE {$alertWhere} ORDER BY created_at DESC,id DESC LIMIT {$limit}");
        $stmt->execute([$merchantUserId, $merchantUserId]);
        $alerts = array_map('mg_merchant_notification_alert_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $error) {
        $alerts = [];
    }
    try {
        $notificationWhere = 'user_id=?' . mg_merchant_notification_type_filter($filter, 'type');
        if ($filter === 'unread') $notificationWhere .= ' AND read_at IS NULL';
        $stmt = $pdo->prepare("SELECT public_id,type,title,body,action_url,context_json,read_at,created_at,updated_at FROM notifications WHERE {$notificationWhere} ORDER BY created_at DESC,id DESC LIMIT {$limit}");
        $stmt->execute([$merchantUserId]);
        $notifications = array_map('mg_merchant_notification_notification_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $error) {
        $notifications = [];
    }
    $items = array_merge($alerts, $notifications);
    usort($items, function(array $a, array $b): int {
        $at = strtotime((string)($a['created_at'] ?? '')) ?: 0;
        $bt = strtotime((string)($b['created_at'] ?? '')) ?: 0;
        return $bt <=> $at;
    });
    return array_slice($items, 0, $limit);
}

function mg_merchant_notifications_counts(PDO $pdo, int $merchantUserId): array
{
    $counts = ['all'=>0,'unread'=>0,'tips'=>0,'messages'=>0,'rewards'=>0,'redemptions'=>0];
    foreach (['all','unread','tips','messages','rewards','redemptions'] as $filter) {
        $counts[$filter] = count(mg_merchant_notifications_fetch($pdo, $merchantUserId, $filter, 100));
    }
    return $counts;
}

function mg_merchant_notifications_mark(PDO $pdo, int $merchantUserId, string $source, string $publicId, string $action): void
{
    $source = strtolower(trim($source));
    $action = strtolower(trim($action));
    if ($publicId === '' || !in_array($source, ['alert','notification'], true)) mg_fail('Notification item is required.', 422);
    if ($source === 'notification') {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()),updated_at=NOW() WHERE public_id=? AND user_id=?');
        $stmt->execute([$publicId, $merchantUserId]);
        if ($stmt->rowCount() < 1) mg_fail('Notification not found.', 404);
        return;
    }
    $status = match($action) {
        'resolve' => 'resolved',
        'dismiss' => 'dismissed',
        default => 'acknowledged',
    };
    $stmt = $pdo->prepare("UPDATE operational_alerts SET status=?,acknowledged_by_user_id=IF(acknowledged_at IS NULL,?,acknowledged_by_user_id),acknowledged_at=COALESCE(acknowledged_at,NOW()),resolved_at=IF(?='resolved',NOW(),resolved_at),updated_at=NOW() WHERE public_id=? AND (merchant_user_id=? OR user_id=?)");
    $stmt->execute([$status, $merchantUserId, $status, $publicId, $merchantUserId, $merchantUserId]);
    if ($stmt->rowCount() < 1) mg_fail('Alert not found.', 404);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = mg_require_api_user();
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int)$workspace['merchant_user_id'];

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_merchant_notifications_mark($pdo, $merchantUserId, (string)($input['source'] ?? ''), (string)($input['id'] ?? ''), (string)($input['action'] ?? 'acknowledge'));
    mg_ok(['status'=>'updated'], 'Merchant notification updated.');
}

mg_require_method('GET');
$filter = mg_merchant_notification_filter((string)($_GET['filter'] ?? 'all'));
$limit = mg_merchant_notification_limit($_GET['limit'] ?? 50);
$items = mg_merchant_notifications_fetch($pdo, $merchantUserId, $filter, $limit);
mg_ok([
    'workspace_id' => (string)$workspace['public_id'],
    'filter' => $filter,
    'counts' => mg_merchant_notifications_counts($pdo, $merchantUserId),
    'items' => $items,
]);
