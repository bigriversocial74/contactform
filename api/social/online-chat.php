<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/messages/_delivery_validation.php';
require_once dirname(__DIR__) . '/social/_account_restrictions.php';

function mg_online_chat_direct_key(int $viewerId, int $peerId): string
{
    $a = min($viewerId, $peerId);
    $b = max($viewerId, $peerId);
    return 'social_direct:' . $a . ':' . $b;
}

function mg_online_chat_safe_avatar(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if ($url[0] === '/' && !str_starts_with($url, '//')) return $url;
    if (preg_match('#^https://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL)) return $url;
    return null;
}

function mg_online_chat_peer(PDO $pdo, int $viewerId, string $profilePublicId): array
{
    $profilePublicId = trim($profilePublicId);
    if ($profilePublicId === '' || strlen($profilePublicId) > 40) throw new InvalidArgumentException('Profile is required.');
    $stmt = $pdo->prepare(
        "SELECT pp.public_id,pp.user_id,pp.slug,pp.display_name,pp.avatar_url,pp.profile_type,u.status
         FROM public_profiles pp
         INNER JOIN users u ON u.id=pp.user_id
         WHERE pp.public_id=? AND pp.status='active' AND pp.visibility IN ('public','unlisted') AND u.status='active'
         LIMIT 1"
    );
    $stmt->execute([$profilePublicId]);
    $peer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$peer) throw new RuntimeException('Profile is not available.');
    $peerId = (int)$peer['user_id'];
    if ($peerId === $viewerId) throw new InvalidArgumentException('Choose another profile to message.');
    if (mg_social_is_blocked($pdo, $viewerId, $peerId)) throw new RuntimeException('Profile is not available.');
    $allowed = $pdo->prepare("SELECT 1 FROM social_follows WHERE status='active' AND ((follower_user_id=? AND followed_user_id=?) OR (follower_user_id=? AND followed_user_id=?)) LIMIT 1");
    $allowed->execute([$viewerId, $peerId, $peerId, $viewerId]);
    if (!$allowed->fetchColumn()) throw new RuntimeException('You can only chat with connected profiles.');
    return $peer;
}

function mg_online_chat_thread(PDO $pdo, int $viewerId, int $peerId, bool $create): ?array
{
    $key = mg_online_chat_direct_key($viewerId, $peerId);
    $stmt = $pdo->prepare(
        'SELECT mt.id,mt.public_id,mt.subject,mt.conversation_key,mtp.last_read_at
         FROM message_threads mt
         INNER JOIN message_thread_participants mtp ON mtp.thread_id=mt.id AND mtp.user_id=?
         WHERE mt.conversation_key=?
         LIMIT 1'
    );
    $stmt->execute([$viewerId, $key]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($thread || !$create) return $thread ?: null;

    $threadPublicId = mg_public_uuid();
    $pdo->prepare(
        'INSERT INTO message_threads (public_id,gift_id,pppm_item_id,conversation_key,created_by_user_id,subject,created_at,updated_at)
         VALUES (?,NULL,NULL,?,?,?,NOW(),NOW())'
    )->execute([$threadPublicId, $key, $viewerId, 'Social chat']);
    $threadId = (int)$pdo->lastInsertId();
    $participant = $pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at) VALUES (?,?,NOW())');
    $participant->execute([$threadId, $viewerId]);
    $participant->execute([$threadId, $peerId]);
    return ['id'=>$threadId,'public_id'=>$threadPublicId,'subject'=>'Social chat','conversation_key'=>$key,'last_read_at'=>null];
}

function mg_online_chat_messages(PDO $pdo, array $thread, int $viewerId): array
{
    $stmt = $pdo->prepare(
        "SELECT m.public_id,m.body,m.created_at,m.sender_user_id,m.recipient_user_id,u.display_name,u.full_name,u.email
         FROM messages m
         INNER JOIN users u ON u.id=m.sender_user_id
         WHERE m.thread_id=? AND m.moderation_status NOT IN ('hidden','removed')
         ORDER BY m.created_at DESC,m.id DESC LIMIT 20"
    );
    $stmt->execute([(int)$thread['id']]);
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    return array_map(static function(array $row) use ($viewerId): array {
        $name = trim((string)($row['display_name'] ?? $row['full_name'] ?? $row['email'] ?? 'Microgifter user'));
        return [
            'id'=>(string)$row['public_id'],
            'body'=>(string)$row['body'],
            'created_at'=>(string)$row['created_at'],
            'sender_name'=>$name !== '' ? $name : 'Microgifter user',
            'mine'=>(int)$row['sender_user_id'] === $viewerId,
        ];
    }, $rows);
}

function mg_online_chat_unread(PDO $pdo, int $viewerId, array $thread): int
{
    $lastReadAt = trim((string)($thread['last_read_at'] ?? ''));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM messages
         WHERE thread_id=? AND sender_user_id<>? AND moderation_status NOT IN ('hidden','removed')
           AND (?='' OR created_at>?)"
    );
    $stmt->execute([(int)$thread['id'], $viewerId, $lastReadAt, $lastReadAt]);
    return (int)$stmt->fetchColumn();
}

function mg_online_chat_profile_payload(array $row): array
{
    return [
        'id'=>(string)$row['public_id'],
        'name'=>(string)($row['display_name'] ?: 'Microgifter member'),
        'slug'=>(string)$row['slug'],
        'avatar_url'=>mg_online_chat_safe_avatar($row['avatar_url'] ?? null),
        'profile_type'=>(string)($row['profile_type'] ?? 'profile'),
        'online'=>true,
        'last_seen_at'=>(string)($row['last_seen_at'] ?? ''),
        'unread'=>(int)($row['unread'] ?? 0),
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();
$user = $method === 'POST' ? mg_require_permission('gift.message.send') : mg_require_api_user();
$viewerId = (int)$user['id'];

try {
    mg_require_user_not_restricted($pdo, $viewerId, 'messaging');
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 409);
}

try {
    if ($method === 'GET') {
        mg_rate_limit('social.online_chat.read', 'user:' . $viewerId, 180, 60);
        $profileId = trim((string)($_GET['profile_id'] ?? ''));
        if ($profileId !== '') {
            $peer = mg_online_chat_peer($pdo, $viewerId, $profileId);
            $thread = mg_online_chat_thread($pdo, $viewerId, (int)$peer['user_id'], false);
            $messages = [];
            if ($thread) {
                $messages = mg_online_chat_messages($pdo, $thread, $viewerId);
                $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'], $viewerId]);
            }
            mg_ok([
                'profile'=>mg_online_chat_profile_payload($peer),
                'thread'=>$thread ? ['id'=>(string)$thread['public_id'],'subject'=>(string)($thread['subject'] ?? 'Social chat'),'unread'=>0] : null,
                'messages'=>$messages,
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            "SELECT pp.public_id,pp.user_id,pp.slug,pp.display_name,pp.avatar_url,pp.profile_type,MAX(us.last_seen_at) last_seen_at
             FROM social_follows sf
             INNER JOIN public_profiles pp ON pp.user_id=sf.follower_user_id
             INNER JOIN users u ON u.id=pp.user_id AND u.status='active'
             INNER JOIN user_sessions us ON us.user_id=pp.user_id AND us.revoked_at IS NULL AND us.expires_at>NOW() AND us.last_seen_at>=DATE_SUB(NOW(), INTERVAL 15 MINUTE)
             WHERE sf.followed_user_id=? AND sf.status='active'
               AND pp.status='active' AND pp.visibility IN ('public','unlisted')
               AND NOT EXISTS (SELECT 1 FROM social_blocks b WHERE (b.blocking_user_id=? AND b.blocked_user_id=pp.user_id) OR (b.blocking_user_id=pp.user_id AND b.blocked_user_id=?))
             GROUP BY pp.id
             ORDER BY last_seen_at DESC
             LIMIT 18"
        );
        $stmt->execute([$viewerId, $viewerId, $viewerId]);
        $profiles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $thread = mg_online_chat_thread($pdo, $viewerId, (int)$row['user_id'], false);
            $row['unread'] = $thread ? mg_online_chat_unread($pdo, $viewerId, $thread) : 0;
            $profiles[] = mg_online_chat_profile_payload($row);
        }
        mg_ok(['profiles'=>$profiles,'checked_at'=>date('Y-m-d H:i:s')]);
        return;
    }

    if ($method === 'POST') {
        mg_rate_limit('social.online_chat.write', 'user:' . $viewerId, 60, 60);
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $profileId = trim((string)($input['profile_id'] ?? ''));
        $body = mg_message_validate_body($input['body'] ?? '');
        $peer = mg_online_chat_peer($pdo, $viewerId, $profileId);
        $peerId = (int)$peer['user_id'];

        $pdo->beginTransaction();
        try {
            $thread = mg_online_chat_thread($pdo, $viewerId, $peerId, true);
            if (!$thread) throw new RuntimeException('Chat thread is not available.');
            $messagePublicId = mg_public_uuid();
            $pdo->prepare(
                'INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,source_type,source_reference,created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())'
            )->execute([$messagePublicId,(int)$thread['id'],$viewerId,$peerId,$body,'social_chat',(string)$thread['conversation_key']]);
            $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$thread['id']]);
            $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'],$viewerId]);

            $senderName = mg_notification_user_label($pdo, $viewerId);
            $notificationId = mg_create_notification(
                $pdo,
                $peerId,
                'message',
                'New chat message',
                $senderName . ': ' . mb_substr($body, 0, 500),
                '/feed.php?chat=' . rawurlencode((string)$peer['public_id']),
                [
                    'actor_user_id'=>$viewerId,
                    'event_key'=>'message.social_chat.' . strtolower((string)$thread['public_id']),
                    'aggregate'=>true,
                    'message_id'=>$messagePublicId,
                    'thread_id'=>(int)$thread['id'],
                    'thread_public_id'=>(string)$thread['public_id'],
                    'recipient_profile_id'=>(string)$peer['public_id'],
                    'source_type'=>'social_chat',
                    'source_reference'=>(string)$thread['conversation_key'],
                    'source_system'=>'messages',
                    'source_label'=>'Feed Chat',
                ]
            );
            $deliveryValidation = mg_message_delivery_validate($pdo, [
                'thread_id'=>(int)$thread['id'],
                'thread_public_id'=>(string)$thread['public_id'],
                'message_id'=>$messagePublicId,
                'sender_user_id'=>$viewerId,
                'recipient_user_ids'=>[$peerId],
                'notification_ids'=>$notificationId !== '' ? [$notificationId] : [],
                'source_type'=>'social_chat',
                'source_reference'=>(string)$thread['conversation_key'],
                'conversation_key'=>(string)$thread['conversation_key'],
            ]);
            mg_message_delivery_throw_if_failed($deliveryValidation);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }

        mg_audit('message.social_chat_sent','message_thread',['thread_id'=>(string)$thread['public_id'],'recipient_profile_id'=>(string)$peer['public_id']],$viewerId);
        mg_event('message.social_chat_sent',['thread_id'=>(string)$thread['public_id'],'message_id'=>$messagePublicId,'recipient_user_id'=>$peerId],$viewerId);
        mg_ok([
            'thread'=>['id'=>(string)$thread['public_id'],'subject'=>(string)($thread['subject'] ?? 'Social chat')],
            'message'=>['id'=>$messagePublicId,'body'=>$body,'mine'=>true,'created_at'=>date('Y-m-d H:i:s'),'sender_name'=>mg_notification_user_label($pdo, $viewerId)],
            'notification_id'=>$notificationId ?: null,
            'delivery_validation'=>$deliveryValidation,
        ], 'Message sent.', 201);
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
    mg_security_log('error','social.online_chat_failed','Online chat rail failed.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$viewerId);
    mg_fail('Unable to load online chat right now.', 500);
}
