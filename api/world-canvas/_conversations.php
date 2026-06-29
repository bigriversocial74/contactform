<?php
declare(strict_types=1);

require_once __DIR__ . '/_world.php';
require_once dirname(__DIR__) . '/store/_canvas.php';

function mg_world_conversations_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'world_conversations')
        && mg_world_canvas_table($pdo, 'world_conversation_members')
        && mg_world_canvas_table($pdo, 'world_conversation_messages');
}

function mg_world_conversations_require(PDO $pdo): void
{
    if (!mg_world_conversations_ready($pdo)) {
        throw new RuntimeException('World Canvas conversation tables are not installed yet.');
    }
}

function mg_world_conversation_clean_text(mixed $value, int $max = 500): string
{
    $text = trim((string)$value);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    return mb_substr($text, 0, $max);
}

function mg_world_conversation_cluster_key(mixed $value): string
{
    $key = mg_world_conversation_clean_text($value, 220);
    if ($key === '') throw new InvalidArgumentException('Conversation cluster key is required.');
    return $key;
}

function mg_world_conversation_identity(PDO $pdo, array $user, ?array $session): array
{
    $userId = (int)($user['id'] ?? 0);
    $profile = null;
    try {
        $stmt = $pdo->prepare('SELECT display_name, avatar_url, slug, visibility, status, profile_type FROM public_profiles WHERE user_id=? LIMIT 1');
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable) {}

    $isMerchantOwner = $session && (int)($session['merchant_user_id'] ?? 0) === $userId;
    $isPublic = $profile && (string)($profile['status'] ?? '') === 'active' && (string)($profile['visibility'] ?? '') === 'public';
    $name = $profile ? trim((string)($profile['display_name'] ?? '')) : '';

    if ($isMerchantOwner) {
        return ['mode'=>'merchant_owned','label'=>$name !== '' ? $name : 'Merchant','role'=>'merchant','profile_url'=>$profile && trim((string)($profile['slug'] ?? '')) !== '' ? '/profile.php?slug=' . rawurlencode((string)$profile['slug']) : ''];
    }
    if ($isPublic && $name !== '') {
        return ['mode'=>'public_profile','label'=>$name,'role'=>'avatar','profile_url'=>trim((string)($profile['slug'] ?? '')) !== '' ? '/profile.php?slug=' . rawurlencode((string)$profile['slug']) : ''];
    }
    return ['mode'=>'anonymous','label'=>'Anonymous avatar','role'=>'avatar','profile_url'=>''];
}

function mg_world_conversation_load_active_session(PDO $pdo, int $userId): ?array
{
    if (!mg_store_canvas_schema_ready($pdo)) return null;
    try {
        return mg_store_active_session_for_customer($pdo, $userId, false);
    } catch (Throwable) {
        return null;
    }
}

function mg_world_conversation_project(PDO $pdo, array $conversation, ?array $member = null): array
{
    $messages = mg_world_canvas_rows($pdo, "SELECT public_id,identity_mode,sender_label,message_body,message_type,created_at FROM world_conversation_messages WHERE conversation_id=? AND status='visible' ORDER BY id DESC LIMIT 40", [(int)$conversation['id']]);
    $messages = array_reverse(array_map(static function (array $row): array {
        return [
            'id' => (string)$row['public_id'],
            'identity_mode' => (string)$row['identity_mode'],
            'sender_label' => (string)$row['sender_label'],
            'message_body' => (string)$row['message_body'],
            'message_type' => (string)$row['message_type'],
            'created_at' => (string)$row['created_at'],
        ];
    }, $messages));
    return [
        'id' => (string)$conversation['public_id'],
        'title' => (string)$conversation['title'],
        'cluster_key' => (string)$conversation['cluster_key'],
        'conversation_type' => (string)$conversation['conversation_type'],
        'location_key' => (string)($conversation['location_key'] ?? ''),
        'participant_count' => (int)$conversation['participant_count'],
        'message_count' => (int)$conversation['message_count'],
        'status' => (string)$conversation['status'],
        'member' => $member ? [
            'id' => (string)$member['id'],
            'identity_mode' => (string)$member['identity_mode'],
            'display_label' => (string)$member['display_label'],
            'role' => (string)$member['role'],
        ] : null,
        'messages' => $messages,
    ];
}

function mg_world_conversation_resolve(PDO $pdo, array $user, array $input): array
{
    mg_world_conversations_require($pdo);
    $clusterKey = mg_world_conversation_cluster_key($input['cluster_key'] ?? '');
    $clusterHash = hash('sha256', $clusterKey);
    $title = mg_world_conversation_clean_text($input['title'] ?? 'World Canvas conversation', 180) ?: 'World Canvas conversation';
    $type = mg_world_conversation_clean_text($input['conversation_type'] ?? 'cluster', 60) ?: 'cluster';
    $locationKey = mg_world_conversation_clean_text($input['location_key'] ?? '', 160);
    $nodeCount = max(0, min(9999, (int)($input['node_count'] ?? 0)));
    $session = mg_world_conversation_load_active_session($pdo, (int)$user['id']);
    $identity = mg_world_conversation_identity($pdo, $user, $session);
    $metadata = [
        'node_count' => $nodeCount,
        'source' => 'world_canvas_cluster',
        'resolved_at' => gmdate('c'),
    ];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM world_conversations WHERE cluster_key_hash=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$clusterHash]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$conversation) {
            $stmt = $pdo->prepare("INSERT INTO world_conversations (public_id,cluster_key_hash,cluster_key,title,conversation_type,location_key,merchant_user_id,participant_count,status,expires_at,metadata_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?, 'active', DATE_ADD(NOW(), INTERVAL 2 DAY),?,?,NOW(),NOW())");
            $stmt->execute([
                mg_public_uuid(),
                $clusterHash,
                $clusterKey,
                $title,
                $type,
                $locationKey !== '' ? $locationKey : null,
                $session ? (int)$session['merchant_user_id'] : null,
                $nodeCount,
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                (int)$user['id'],
            ]);
            $conversationId = (int)$pdo->lastInsertId();
            $conversation = mg_world_canvas_rows($pdo, 'SELECT * FROM world_conversations WHERE id=?', [$conversationId])[0] ?? null;
        } else {
            $stmt = $pdo->prepare("UPDATE world_conversations SET title=?,location_key=COALESCE(NULLIF(?,''),location_key),participant_count=GREATEST(participant_count,?),status=IF(status='expired','active',status),expires_at=DATE_ADD(NOW(), INTERVAL 2 DAY),updated_at=NOW() WHERE id=?");
            $stmt->execute([$title, $locationKey, $nodeCount, (int)$conversation['id']]);
            $conversation = mg_world_canvas_rows($pdo, 'SELECT * FROM world_conversations WHERE id=?', [(int)$conversation['id']])[0] ?? $conversation;
        }
        if (!$conversation) throw new RuntimeException('Unable to resolve conversation.');

        $member = null;
        if ($session) {
            $stmt = $pdo->prepare('SELECT * FROM world_conversation_members WHERE conversation_id=? AND store_session_id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([(int)$conversation['id'], (int)$session['id']]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$member) {
                $stmt = $pdo->prepare("INSERT INTO world_conversation_members (conversation_id,user_id,store_session_id,merchant_user_id,member_public_id,identity_mode,display_label,role,status,joined_at,last_seen_at,metadata_json) VALUES (?,?,?,?,?,?,?,?, 'active', NOW(), NOW(), ?)");
                $stmt->execute([
                    (int)$conversation['id'],
                    (int)$user['id'],
                    (int)$session['id'],
                    (int)$session['merchant_user_id'],
                    (string)$session['public_id'],
                    (string)$identity['mode'],
                    (string)$identity['label'],
                    (string)$identity['role'],
                    json_encode(['profile_url'=>$identity['profile_url'] ?? ''], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
                $memberId = (int)$pdo->lastInsertId();
                $member = mg_world_canvas_rows($pdo, 'SELECT * FROM world_conversation_members WHERE id=?', [$memberId])[0] ?? null;
            } else {
                $pdo->prepare('UPDATE world_conversation_members SET identity_mode=?,display_label=?,role=?,status=\'active\',last_seen_at=NOW() WHERE id=?')->execute([(string)$identity['mode'], (string)$identity['label'], (string)$identity['role'], (int)$member['id']]);
                $member = mg_world_canvas_rows($pdo, 'SELECT * FROM world_conversation_members WHERE id=?', [(int)$member['id']])[0] ?? $member;
            }
        }
        $count = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_conversation_members WHERE conversation_id=? AND status='active'", [(int)$conversation['id']]);
        $pdo->prepare('UPDATE world_conversations SET participant_count=GREATEST(participant_count,?),updated_at=NOW() WHERE id=?')->execute([$count, (int)$conversation['id']]);
        $conversation = mg_world_canvas_rows($pdo, 'SELECT * FROM world_conversations WHERE id=?', [(int)$conversation['id']])[0] ?? $conversation;
        $pdo->commit();
        return mg_world_conversation_project($pdo, $conversation, $member);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_world_conversation_load_by_public_id(PDO $pdo, string $publicId): array
{
    mg_world_conversations_require($pdo);
    $publicId = trim($publicId);
    if ($publicId === '' || preg_match('/^[A-Za-z0-9._:-]+$/', $publicId) !== 1) throw new InvalidArgumentException('Conversation is required.');
    $row = mg_world_canvas_rows($pdo, 'SELECT * FROM world_conversations WHERE public_id=? LIMIT 1', [$publicId])[0] ?? null;
    if (!$row) throw new RuntimeException('Conversation was not found.');
    return $row;
}
