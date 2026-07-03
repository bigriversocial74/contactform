<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';
require_once __DIR__ . '/_account_restrictions.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';
require_once dirname(__DIR__) . '/messages/_delivery_validation.php';

function mg_feed_chat_key(int $a, int $b): string
{
    return 'social_direct:' . min($a, $b) . ':' . max($a, $b);
}

function mg_feed_chat_avatar(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 500 || strpbrk($url, "\r\n\t") !== false) return null;
    if ($url[0] === '/' && !str_starts_with($url, '//')) return $url;
    return preg_match('#^https://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}

function mg_feed_chat_profile_for_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT pp.public_id,pp.user_id,pp.slug,pp.display_name,pp.avatar_url,pp.profile_type,MAX(us.last_seen_at) last_seen_at,CASE WHEN MAX(us.last_seen_at)>=DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END is_online FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id LEFT JOIN user_sessions us ON us.user_id=pp.user_id AND us.revoked_at IS NULL AND us.expires_at>NOW() WHERE pp.user_id=? AND pp.status='active' AND pp.visibility IN ('public','unlisted') AND u.status='active' GROUP BY pp.id LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_feed_chat_existing_thread_exists(PDO $pdo, int $viewerId, int $peerId): bool
{
    $key = mg_feed_chat_key($viewerId, $peerId);
    $stmt = $pdo->prepare('SELECT 1 FROM message_threads mt INNER JOIN message_thread_participants a ON a.thread_id=mt.id AND a.user_id=? INNER JOIN message_thread_participants b ON b.thread_id=mt.id AND b.user_id=? WHERE mt.conversation_key=? LIMIT 1');
    $stmt->execute([$viewerId, $peerId, $key]);
    return (bool)$stmt->fetchColumn();
}

function mg_feed_chat_profile(PDO $pdo, int $viewerId, string $profileId): array
{
    $stmt = $pdo->prepare("SELECT pp.public_id,pp.user_id,pp.slug,pp.display_name,pp.avatar_url,pp.profile_type,MAX(us.last_seen_at) last_seen_at,CASE WHEN MAX(us.last_seen_at)>=DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END is_online FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id LEFT JOIN user_sessions us ON us.user_id=pp.user_id AND us.revoked_at IS NULL AND us.expires_at>NOW() WHERE pp.public_id=? AND pp.status='active' AND pp.visibility IN ('public','unlisted') AND u.status='active' GROUP BY pp.id LIMIT 1");
    $stmt->execute([trim($profileId)]);
    $peer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$peer) throw new RuntimeException('Profile is not available.');
    $peerId = (int)$peer['user_id'];
    if ($peerId === $viewerId) throw new InvalidArgumentException('Choose another profile.');
    if (mg_social_is_blocked($pdo, $viewerId, $peerId)) throw new RuntimeException('Profile is not available.');
    $rel = $pdo->prepare("SELECT 1 FROM social_follows WHERE status='active' AND follower_user_id=? AND followed_user_id=? LIMIT 1");
    $rel->execute([$viewerId, $peerId]);
    if (!$rel->fetchColumn() && !mg_feed_chat_existing_thread_exists($pdo, $viewerId, $peerId)) throw new RuntimeException('Connected profile required.');
    return $peer;
}

function mg_feed_chat_project_profile(array $row): array
{
    return [
        'id'=>(string)$row['public_id'],
        'name'=>(string)($row['display_name'] ?: 'Microgifter member'),
        'slug'=>(string)$row['slug'],
        'avatar_url'=>mg_feed_chat_avatar($row['avatar_url'] ?? null),
        'profile_type'=>(string)($row['profile_type'] ?? 'profile'),
        'online'=>(bool)($row['is_online'] ?? false),
        'last_seen_at'=>(string)($row['last_seen_at'] ?? ''),
        'unread'=>(int)($row['unread'] ?? 0),
    ];
}

function mg_feed_chat_list_profile_rows(PDO $pdo, int $viewerId): array
{
    $rowsByUserId = [];
    $followed = $pdo->prepare("SELECT pp.public_id,pp.user_id,pp.slug,pp.display_name,pp.avatar_url,pp.profile_type,MAX(us.last_seen_at) last_seen_at,CASE WHEN MAX(us.last_seen_at)>=DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END is_online,MAX(sf.updated_at) sort_at FROM social_follows sf INNER JOIN public_profiles pp ON pp.user_id=sf.followed_user_id INNER JOIN users u ON u.id=pp.user_id AND u.status='active' LEFT JOIN user_sessions us ON us.user_id=pp.user_id AND us.revoked_at IS NULL AND us.expires_at>NOW() WHERE sf.follower_user_id=? AND sf.status='active' AND pp.status='active' AND pp.visibility IN ('public','unlisted') AND NOT EXISTS (SELECT 1 FROM social_blocks b WHERE (b.blocking_user_id=? AND b.blocked_user_id=pp.user_id) OR (b.blocking_user_id=pp.user_id AND b.blocked_user_id=?)) GROUP BY pp.id ORDER BY is_online DESC,last_seen_at DESC,sort_at DESC,pp.updated_at DESC LIMIT 10");
    $followed->execute([$viewerId, $viewerId, $viewerId]);
    foreach ($followed->fetchAll(PDO::FETCH_ASSOC) as $row) $rowsByUserId[(int)$row['user_id']] = $row;

    $threads = $pdo->prepare("SELECT pp.public_id,pp.user_id,pp.slug,pp.display_name,pp.avatar_url,pp.profile_type,MAX(us.last_seen_at) last_seen_at,CASE WHEN MAX(us.last_seen_at)>=DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END is_online,MAX(mt.updated_at) sort_at FROM message_threads mt INNER JOIN message_thread_participants mine ON mine.thread_id=mt.id AND mine.user_id=? INNER JOIN message_thread_participants peerp ON peerp.thread_id=mt.id AND peerp.user_id<>? INNER JOIN public_profiles pp ON pp.user_id=peerp.user_id INNER JOIN users u ON u.id=pp.user_id AND u.status='active' LEFT JOIN user_sessions us ON us.user_id=pp.user_id AND us.revoked_at IS NULL AND us.expires_at>NOW() WHERE mt.conversation_key LIKE 'social_direct:%' AND pp.status='active' AND pp.visibility IN ('public','unlisted') AND NOT EXISTS (SELECT 1 FROM social_blocks b WHERE (b.blocking_user_id=? AND b.blocked_user_id=pp.user_id) OR (b.blocking_user_id=pp.user_id AND b.blocked_user_id=?)) GROUP BY pp.id ORDER BY sort_at DESC LIMIT 10");
    $threads->execute([$viewerId, $viewerId, $viewerId, $viewerId]);
    foreach ($threads->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $userId = (int)$row['user_id'];
        if (!isset($rowsByUserId[$userId])) $rowsByUserId[$userId] = $row;
    }
    return array_slice(array_values($rowsByUserId), 0, 10);
}

function mg_feed_chat_thread(PDO $pdo, int $viewerId, int $peerId, bool $create): ?array
{
    $key = mg_feed_chat_key($viewerId, $peerId);
    $stmt = $pdo->prepare('SELECT mt.id,mt.public_id,mt.subject,mt.conversation_key,mtp.last_read_at FROM message_threads mt INNER JOIN message_thread_participants mtp ON mtp.thread_id=mt.id AND mtp.user_id=? WHERE mt.conversation_key=? LIMIT 1');
    $stmt->execute([$viewerId, $key]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($thread || !$create) return $thread ?: null;
    $public = mg_public_uuid();
    $pdo->prepare('INSERT INTO message_threads (public_id,gift_id,pppm_item_id,conversation_key,created_by_user_id,subject,created_at,updated_at) VALUES (?,NULL,NULL,?,?,?,NOW(),NOW())')->execute([$public, $key, $viewerId, 'Social chat']);
    $threadId = (int)$pdo->lastInsertId();
    $participants = $pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at) VALUES (?,?,NOW())');
    $participants->execute([$threadId, $viewerId]);
    $participants->execute([$threadId, $peerId]);
    return ['id'=>$threadId,'public_id'=>$public,'subject'=>'Social chat','conversation_key'=>$key,'last_read_at'=>null];
}

function mg_feed_chat_messages(PDO $pdo, array $thread, int $viewerId): array
{
    $stmt = $pdo->prepare("SELECT m.public_id,m.body,m.created_at,m.sender_user_id,u.display_name,u.full_name,u.email FROM messages m INNER JOIN users u ON u.id=m.sender_user_id WHERE m.thread_id=? AND COALESCE(m.moderation_status,'clear') NOT IN ('hidden','removed') ORDER BY m.created_at DESC,m.id DESC LIMIT 20");
    $stmt->execute([(int)$thread['id']]);
    return array_map(static function(array $row) use ($viewerId): array {
        $name = trim((string)($row['display_name'] ?? $row['full_name'] ?? $row['email'] ?? 'Microgifter member'));
        return ['id'=>(string)$row['public_id'],'body'=>(string)$row['body'],'created_at'=>(string)$row['created_at'],'sender_name'=>$name,'mine'=>(int)$row['sender_user_id'] === $viewerId];
    }, array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)));
}

function mg_feed_chat_unread(PDO $pdo, int $viewerId, array $thread): int
{
    $last = trim((string)($thread['last_read_at'] ?? ''));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE thread_id=? AND sender_user_id<>? AND COALESCE(moderation_status,'clear') NOT IN ('hidden','removed') AND (?='' OR created_at>?)");
    $stmt->execute([(int)$thread['id'], $viewerId, $last, $last]);
    return (int)$stmt->fetchColumn();
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_api_user();
$viewerId = (int)$user['id'];
$pdo = mg_db();

try {
    if ($method === 'GET') {
        mg_rate_limit('social.online_chat.read', 'user:' . $viewerId, 300, 60);
        $profileId = trim((string)($_GET['profile_id'] ?? ''));
        if ($profileId !== '') {
            $peer = mg_feed_chat_profile($pdo, $viewerId, $profileId);
            $thread = mg_feed_chat_thread($pdo, $viewerId, (int)$peer['user_id'], false);
            $messages = $thread ? mg_feed_chat_messages($pdo, $thread, $viewerId) : [];
            $markRead = (string)($_GET['mark_read'] ?? '') === '1';
            if ($thread && $markRead) $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'], $viewerId]);
            $unread = $thread && !$markRead ? mg_feed_chat_unread($pdo, $viewerId, $thread) : 0;
            mg_ok(['profile'=>mg_feed_chat_project_profile($peer),'thread'=>$thread ? ['id'=>(string)$thread['public_id'],'subject'=>(string)$thread['subject'],'unread'=>$unread] : null,'messages'=>$messages,'poll_after_ms'=>5000]);
            return;
        }
        $profiles = [];
        foreach (mg_feed_chat_list_profile_rows($pdo, $viewerId) as $row) {
            $thread = mg_feed_chat_thread($pdo, $viewerId, (int)$row['user_id'], false);
            $row['unread'] = $thread ? mg_feed_chat_unread($pdo, $viewerId, $thread) : 0;
            $profiles[] = mg_feed_chat_project_profile($row);
        }
        mg_ok(['profiles'=>$profiles,'checked_at'=>date('Y-m-d H:i:s'),'poll_after_ms'=>15000]);
        return;
    }

    if ($method === 'POST') {
        mg_rate_limit('social.online_chat.write', 'user:' . $viewerId, 60, 60);
        mg_require_user_not_restricted($pdo, $viewerId, 'messaging');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $peer = mg_feed_chat_profile($pdo, $viewerId, (string)($input['profile_id'] ?? ''));
        $peerId = (int)$peer['user_id'];
        $body = mg_message_validate_body($input['body'] ?? '');
        $delivery = null;
        $notificationId = '';
        $pdo->beginTransaction();
        $thread = mg_feed_chat_thread($pdo, $viewerId, $peerId, true);
        $messageId = mg_public_uuid();
        $pdo->prepare('INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,source_type,source_reference,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([$messageId,(int)$thread['id'],$viewerId,$peerId,$body,'social_chat',(string)$thread['conversation_key']]);
        $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$thread['id']]);
        $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'],$viewerId]);
        $senderName = mg_notification_user_label($pdo, $viewerId);
        $senderProfile = mg_feed_chat_profile_for_user($pdo, $viewerId);
        $senderProfileId = is_array($senderProfile) ? (string)$senderProfile['public_id'] : '';
        $messageFallbackUrl = '/messages.php?thread=' . rawurlencode((string)$thread['public_id']);
        $actionUrl = $senderProfileId !== '' ? '/feed.php?chat=' . rawurlencode($senderProfileId) . '&thread=' . rawurlencode((string)$thread['public_id']) : $messageFallbackUrl;
        try {
            $notificationId = mg_create_notification($pdo, $peerId, 'message', 'New Feed Chat message', $senderName . ': ' . mb_substr($body, 0, 500), $actionUrl, ['actor_user_id'=>$viewerId,'event_key'=>'message.social_chat.' . strtolower((string)$thread['public_id']),'aggregate'=>true,'message_id'=>$messageId,'thread_id'=>(int)$thread['id'],'thread_public_id'=>(string)$thread['public_id'],'sender_profile_id'=>$senderProfileId ?: null,'recipient_profile_id'=>(string)$peer['public_id'],'fallback_url'=>$messageFallbackUrl,'source_type'=>'social_chat','source_reference'=>(string)$thread['conversation_key'],'source_system'=>'social_feed','source_label'=>'Feed Chat']);
        } catch (Throwable $notifyError) {
            mg_security_log('warning','social.online_chat_notification_failed','Feed chat message saved but notification creation failed.',['exception_class'=>$notifyError::class,'message'=>$notifyError->getMessage(),'thread_id'=>(string)$thread['public_id'],'recipient_user_id'=>$peerId],$viewerId);
            $notificationId = '';
        }
        $delivery = mg_message_delivery_validate($pdo, ['thread_id'=>(int)$thread['id'],'thread_public_id'=>(string)$thread['public_id'],'message_id'=>$messageId,'sender_user_id'=>$viewerId,'recipient_user_ids'=>[$peerId],'notification_ids'=>$notificationId !== '' ? [$notificationId] : [],'source_type'=>'social_chat','source_reference'=>(string)$thread['conversation_key'],'conversation_key'=>(string)$thread['conversation_key']]);
        mg_message_delivery_throw_if_failed($delivery);
        $pdo->commit();
        mg_audit('message.social_chat_sent','message_thread',['thread_id'=>(string)$thread['public_id'],'recipient_profile_id'=>(string)$peer['public_id']],$viewerId);
        mg_event('message.social_chat_sent',['thread_id'=>(string)$thread['public_id'],'message_id'=>$messageId,'recipient_user_id'=>$peerId],$viewerId);
        mg_ok(['thread'=>['id'=>(string)$thread['public_id'],'subject'=>(string)$thread['subject']],'message'=>['id'=>$messageId,'body'=>$body,'mine'=>true,'created_at'=>date('Y-m-d H:i:s'),'sender_name'=>$senderName],'notification_id'=>$notificationId ?: null,'delivery_validation'=>$delivery], 'Message sent.', 201);
        return;
    }
    mg_fail('Method not allowed.', 405);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','social.online_chat_failed','Online chat failed.',['exception_class'=>$error::class,'message'=>$error->getMessage(),'method'=>$method],$viewerId);
    mg_fail($method === 'POST' ? 'Unable to send chat message right now.' : 'Unable to load online chat right now.', 500);
}
