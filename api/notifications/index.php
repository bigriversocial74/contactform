<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-push.php';

mg_require_method('GET');
$user = mg_require_permission('notification.view');
$unreadOnly = (string)($_GET['unread'] ?? '') === '1';
$limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));

// User-scope compatibility contract: WHERE user_id = ?
$where = "n.user_id=? AND COALESCE(np.in_app_enabled,1)=1 AND COALESCE(np.digest_mode,'immediate')<>'off'";
if ($unreadOnly) $where .= ' AND n.read_at IS NULL';

$pdo = mg_db();
try {
    mg_pwa_push_queue_recent_for_user($pdo, (int)$user['id'], 20);
} catch (Throwable $error) {
    mg_security_log('warning', 'pwa.push.queue_recent_failed', 'Unable to bridge recent notifications into PWA push queue.', ['exception_class' => $error::class], (int)$user['id']);
}
$stmt = $pdo->prepare(
    'SELECT n.public_id,n.type,n.title,n.body,n.action_url,n.occurrence_count,n.context_json,
            n.read_at,n.created_at,n.updated_at,n.actor_user_id,
            COALESCE(NULLIF(actor.display_name,\'\'),NULLIF(actor.full_name,\'\'),actor.email) actor_name,
            actor_profile.slug actor_slug,actor_profile.avatar_url actor_avatar_url
     FROM notifications n
     LEFT JOIN notification_preferences np
       ON np.user_id=n.user_id AND np.notification_type=n.type
     LEFT JOIN users actor ON actor.id=n.actor_user_id AND actor.status=\'active\'
     LEFT JOIN public_profiles actor_profile ON actor_profile.user_id=actor.id AND actor_profile.status=\'active\'
     WHERE ' . $where . '
     ORDER BY n.created_at DESC,n.id DESC
     LIMIT ' . $limit
);
$stmt->execute([(int)$user['id']]);
$notifications = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $context = [];
    if (!empty($row['context_json'])) {
        $decoded = json_decode((string)$row['context_json'], true);
        if (is_array($decoded)) $context = $decoded;
    }
    $notifications[] = [
        'id'=>(string)$row['public_id'],
        'public_id'=>(string)$row['public_id'],
        'type'=>(string)$row['type'],
        'title'=>(string)$row['title'],
        'body'=>(string)($row['body'] ?? ''),
        'action_url'=>$row['action_url'] !== null ? (string)$row['action_url'] : null,
        'occurrence_count'=>max(1,(int)($row['occurrence_count'] ?? 1)),
        'actor'=>$row['actor_user_id'] !== null ? [
            'id'=>(int)$row['actor_user_id'],
            'name'=>(string)($row['actor_name'] ?? 'Microgifter member'),
            'slug'=>$row['actor_slug'] !== null ? (string)$row['actor_slug'] : null,
            'avatar_url'=>$row['actor_avatar_url'] !== null ? (string)$row['actor_avatar_url'] : null,
        ] : null,
        'context'=>$context,
        'read'=>$row['read_at'] !== null,
        'read_at'=>$row['read_at'] ?? null,
        'created_at'=>$row['created_at'] ?? null,
        'updated_at'=>$row['updated_at'] ?? null,
    ];
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM notifications n
     LEFT JOIN notification_preferences np
       ON np.user_id=n.user_id AND np.notification_type=n.type
     WHERE n.user_id=? AND n.read_at IS NULL
       AND COALESCE(np.in_app_enabled,1)=1
       AND COALESCE(np.digest_mode,'immediate')<>'off'"
);
$countStmt->execute([(int)$user['id']]);

header('Cache-Control: private, no-store, max-age=0');
mg_ok(['notifications'=>$notifications,'unread_count'=>(int)$countStmt->fetchColumn()]);
