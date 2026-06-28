<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';

mg_require_method('GET');
$user = mg_require_permission('gift.message.send');
$userId = (int)$user['id'];
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

$stmt = mg_db()->prepare(
    'SELECT mt.id, mt.public_id, mt.subject, mt.gift_id, mt.conversation_key, mt.created_by_user_id, mt.updated_at,
            g.public_id AS gift_public_id, g.title AS gift_title,
            latest.body AS latest_body, latest.created_at AS latest_created_at,
            sender.display_name AS latest_sender_name, sender.full_name AS latest_sender_full_name,
            mtp.last_read_at,
            COALESCE(mcs.status,\'open\') crm_status,
            mcs.label crm_label,
            mcs.assigned_user_id,
            CASE WHEN mcs.assigned_user_id = ? THEN 1 ELSE 0 END assigned_to_me,
            CASE WHEN latest.created_at IS NOT NULL AND (mtp.last_read_at IS NULL OR latest.created_at > mtp.last_read_at)
                 AND latest.sender_user_id <> ? THEN 1 ELSE 0 END AS unread
     FROM message_thread_participants mtp
     INNER JOIN message_threads mt ON mt.id = mtp.thread_id
     LEFT JOIN message_thread_settings mts ON mts.thread_id=mt.id AND mts.user_id=mtp.user_id
     LEFT JOIN message_thread_crm_state mcs ON mcs.thread_id=mt.id
     LEFT JOIN gifts g ON g.id = mt.gift_id
     LEFT JOIN messages latest ON latest.id = (
       SELECT m2.id FROM messages m2 WHERE m2.thread_id = mt.id ORDER BY m2.created_at DESC, m2.id DESC LIMIT 1
     )
     LEFT JOIN users sender ON sender.id = latest.sender_user_id
     WHERE mtp.user_id = ? AND mts.archived_at IS NULL
     ORDER BY COALESCE(latest.created_at, mt.updated_at) DESC, mt.id DESC
     LIMIT ' . $limit
);
$stmt->execute([$userId, $userId, $userId]);

$threads = array_map(static function(array $row) use ($userId): array {
    $isCrm = str_starts_with((string)($row['conversation_key'] ?? ''), 'crm:');
    $isMerchantCrmOwner = $isCrm && (int)($row['created_by_user_id'] ?? 0) === $userId;
    $actionUrl = $isMerchantCrmOwner ? '/merchant-crm.php?thread=' . rawurlencode((string)$row['public_id']) : '/messages.php?thread=' . rawurlencode((string)$row['public_id']);
    $label = (string)($row['crm_label'] ?? '');
    return [
        'id' => (string) $row['public_id'],
        'subject' => (string) ($row['subject'] ?? $row['gift_title'] ?? ($isCrm ? 'CRM conversation' : 'Gift conversation')),
        'gift_id' => $row['gift_public_id'] !== null ? (string) $row['gift_public_id'] : null,
        'latest_message' => (string) ($row['latest_body'] ?? ''),
        'latest_sender' => trim((string) ($row['latest_sender_name'] ?? $row['latest_sender_full_name'] ?? '')),
        'latest_at' => $row['latest_created_at'] ?? $row['updated_at'] ?? null,
        'unread' => (bool) $row['unread'],
        'is_crm' => $isCrm,
        'merchant_crm_owner' => $isMerchantCrmOwner,
        'action_url' => $actionUrl,
        'crm_status' => (string)($row['crm_status'] ?? 'open'),
        'crm_label' => $label,
        'assigned_to_me' => !empty($row['assigned_to_me']),
        'high_value' => strtolower($label) === 'high value',
    ];
}, $stmt->fetchAll());

$countStmt = mg_db()->prepare(
    "SELECT COUNT(*) FROM message_thread_participants mtp
     INNER JOIN message_threads mt ON mt.id=mtp.thread_id
     LEFT JOIN message_thread_settings mts ON mts.thread_id=mt.id AND mts.user_id=mtp.user_id
     LEFT JOIN messages latest ON latest.id=(SELECT m2.id FROM messages m2 WHERE m2.thread_id=mt.id ORDER BY m2.created_at DESC,m2.id DESC LIMIT 1)
     WHERE mtp.user_id=? AND mts.archived_at IS NULL AND latest.created_at IS NOT NULL AND (mtp.last_read_at IS NULL OR latest.created_at>mtp.last_read_at) AND latest.sender_user_id<>?"
);
$countStmt->execute([$userId,$userId]);
mg_ok(['threads' => $threads, 'unread_count' => (int)$countStmt->fetchColumn()]);