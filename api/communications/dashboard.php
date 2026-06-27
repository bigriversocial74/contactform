<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_communications_source_type(?string $sourceType, ?string $conversationKey = null, ?string $pppmId = null): string
{
    $sourceType = trim((string)$sourceType);
    $conversationKey = trim((string)$conversationKey);
    if ($sourceType !== '' && $sourceType !== 'messaging') {
        return $sourceType;
    }
    if ($conversationKey !== '' && str_starts_with($conversationKey, 'store_canvas:')) {
        return 'store_canvas_direct';
    }
    if ($conversationKey !== '' && str_starts_with($conversationKey, 'crm:')) {
        return 'merchant_crm_message';
    }
    if (trim((string)$pppmId) !== '') {
        return 'pppm_message';
    }
    return $sourceType !== '' ? $sourceType : 'messaging';
}

function mg_communications_source_label(string $sourceType): string
{
    return match ($sourceType) {
        'store_canvas_direct' => 'Store Canvas',
        'store_canvas_reply' => 'Store Canvas Reply',
        'merchant_crm_message', 'merchant_crm' => 'Merchant CRM',
        'action_center_follow_up' => 'Action Center Follow Up',
        'action_center' => 'Action Center',
        'pppm_message' => 'PPPM / IN-OUT Box',
        'messaging' => 'Messages',
        default => ucwords(str_replace(['_', '-'], ' ', $sourceType)),
    };
}

function mg_communications_source_system(string $sourceType): string
{
    if (str_starts_with($sourceType, 'store_canvas')) {
        return 'store_canvas';
    }
    if ($sourceType === 'merchant_crm_message' || $sourceType === 'merchant_crm') {
        return 'merchant_crm';
    }
    if (str_starts_with($sourceType, 'action_center') || $sourceType === 'pppm_message') {
        return 'in_out_box';
    }
    return 'messages';
}

function mg_communications_project_thread(array $row): array
{
    $sourceType = mg_communications_source_type(
        isset($row['source_type']) ? (string)$row['source_type'] : null,
        isset($row['conversation_key']) ? (string)$row['conversation_key'] : null,
        isset($row['pppm_id']) ? (string)$row['pppm_id'] : null
    );
    $row['source_type'] = $sourceType;
    $row['source_label'] = mg_communications_source_label($sourceType);
    $row['source_system'] = mg_communications_source_system($sourceType);
    $row['source_reference'] = isset($row['source_reference']) ? (string)$row['source_reference'] : '';
    return $row;
}

mg_require_method('GET');
$user = mg_require_permission('notification.view');
$pdo = mg_db();
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

$notifications = $pdo->prepare(
    "SELECT n.public_id,n.type,n.title,n.body,n.action_url,n.read_at,n.created_at,n.context_json,
            CASE
              WHEN n.type IN ('claim_locked','claim_expired','delivery_failed','distribution_failed','security','system_alert') THEN 'operational'
              WHEN n.type IN ('message','merchant_crm_message') THEN 'message'
              ELSE 'activity'
            END category
     FROM notifications n
     WHERE n.user_id=?
     ORDER BY n.created_at DESC,n.id DESC
     LIMIT {$limit}"
);
$notifications->execute([(int)$user['id']]);

$threads = $pdo->prepare(
    "SELECT mt.public_id,mt.subject,mt.updated_at,mt.conversation_key,
            g.public_id gift_id,
            pi.public_id pppm_id,
            latest.body latest_message,
            latest.created_at latest_at,
            latest.source_type,
            latest.source_reference,
            COALESCE(mts.archived_at IS NOT NULL,0) archived,
            COALESCE(mts.pinned_at IS NOT NULL,0) pinned,
            CASE
              WHEN latest.created_at IS NOT NULL
               AND (mtp.last_read_at IS NULL OR latest.created_at>mtp.last_read_at)
               AND latest.sender_user_id<>?
              THEN 1 ELSE 0
            END unread
     FROM message_thread_participants mtp
     INNER JOIN message_threads mt ON mt.id=mtp.thread_id
     LEFT JOIN message_thread_settings mts ON mts.thread_id=mt.id AND mts.user_id=mtp.user_id
     LEFT JOIN gifts g ON g.id=mt.gift_id
     LEFT JOIN pppm_items pi ON pi.id=mt.pppm_item_id
     LEFT JOIN messages latest ON latest.id=(
        SELECT m2.id FROM messages m2 WHERE m2.thread_id=mt.id ORDER BY m2.created_at DESC,m2.id DESC LIMIT 1
     )
     WHERE mtp.user_id=? AND mts.archived_at IS NULL
     ORDER BY pinned DESC,COALESCE(latest.created_at,mt.updated_at) DESC
     LIMIT {$limit}"
);
$threads->execute([(int)$user['id'], (int)$user['id']]);

$alerts = $pdo->prepare(
    "SELECT public_id,alert_type,severity,status,title,body,action_url,created_at,acknowledged_at,resolved_at
     FROM operational_alerts
     WHERE user_id=? AND status IN ('open','acknowledged')
     ORDER BY FIELD(severity,'critical','high','warning','info'),created_at DESC
     LIMIT {$limit}"
);
$alerts->execute([(int)$user['id']]);

$counts = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL) notification_unread,
        (SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND status='open') open_alerts,
        (SELECT COUNT(*)
         FROM message_thread_participants mtp
         INNER JOIN message_threads mt ON mt.id=mtp.thread_id
         LEFT JOIN messages latest ON latest.id=(
            SELECT m2.id FROM messages m2 WHERE m2.thread_id=mt.id ORDER BY m2.created_at DESC,m2.id DESC LIMIT 1
         )
         WHERE mtp.user_id=?
           AND latest.created_at IS NOT NULL
           AND (mtp.last_read_at IS NULL OR latest.created_at>mtp.last_read_at)
           AND latest.sender_user_id<>?) message_unread"
);
$counts->execute([(int)$user['id'], (int)$user['id'], (int)$user['id'], (int)$user['id']]);

mg_ok([
    'notifications' => $notifications->fetchAll(),
    'threads' => array_map('mg_communications_project_thread', $threads->fetchAll()),
    'alerts' => $alerts->fetchAll(),
    'counts' => $counts->fetch() ?: [],
]);
