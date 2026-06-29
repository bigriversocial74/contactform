<?php
declare(strict_types=1);

require_once __DIR__ . '/_conversations.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('world_canvas.conversation_message', 'user:' . (int)$user['id'], 40, 60);
    mg_world_conversations_require($pdo);
    $conversation = mg_world_conversation_load_by_public_id($pdo, (string)($input['conversation_id'] ?? ''));
    if ((string)$conversation['status'] !== 'active' && (string)$conversation['status'] !== 'quiet') {
        throw new RuntimeException('This World Canvas conversation is not active.');
    }

    $body = mg_world_conversation_clean_text($input['message_body'] ?? '', 700);
    if ($body === '' || mb_strlen($body) < 1) throw new InvalidArgumentException('Message is required.');

    $session = mg_world_conversation_load_active_session($pdo, (int)$user['id']);
    $identity = mg_world_conversation_identity($pdo, $user, $session);
    $member = null;
    if ($session) {
        $member = mg_world_canvas_rows($pdo, 'SELECT * FROM world_conversation_members WHERE conversation_id=? AND store_session_id=? LIMIT 1', [(int)$conversation['id'], (int)$session['id']])[0] ?? null;
    }
    if (!$member) {
        $stmt = $pdo->prepare("INSERT INTO world_conversation_members (conversation_id,user_id,store_session_id,merchant_user_id,member_public_id,identity_mode,display_label,role,status,joined_at,last_seen_at,metadata_json) VALUES (?,?,?,?,?,?,?,?, 'active', NOW(), NOW(), ?)");
        $stmt->execute([
            (int)$conversation['id'],
            (int)$user['id'],
            $session ? (int)$session['id'] : null,
            $session ? (int)$session['merchant_user_id'] : null,
            $session ? (string)$session['public_id'] : 'user:' . (int)$user['id'],
            (string)$identity['mode'],
            (string)$identity['label'],
            (string)$identity['role'],
            json_encode(['profile_url'=>$identity['profile_url'] ?? ''], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $memberId = (int)$pdo->lastInsertId();
        $member = mg_world_canvas_rows($pdo, 'SELECT * FROM world_conversation_members WHERE id=?', [$memberId])[0] ?? null;
    }

    $stmt = $pdo->prepare("INSERT INTO world_conversation_messages (public_id,conversation_id,member_id,user_id,store_session_id,identity_mode,sender_label,message_body,message_type,status,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?, 'text','visible',?,NOW())");
    $stmt->execute([
        mg_public_uuid(),
        (int)$conversation['id'],
        $member ? (int)$member['id'] : null,
        (int)$user['id'],
        $session ? (int)$session['id'] : null,
        (string)$identity['mode'],
        (string)$identity['label'],
        $body,
        json_encode(['world_canvas'=>true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);

    $messageCount = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_conversation_messages WHERE conversation_id=? AND status='visible'", [(int)$conversation['id']]);
    $participantCount = mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM world_conversation_members WHERE conversation_id=? AND status='active'", [(int)$conversation['id']]);
    $pdo->prepare("UPDATE world_conversations SET message_count=?,participant_count=GREATEST(participant_count,?),last_message_at=NOW(),status='active',updated_at=NOW() WHERE id=?")->execute([$messageCount, $participantCount, (int)$conversation['id']]);
    if ($session) mg_store_log_event($pdo, $session, 'world_conversation_message', 'World conversation message', ['conversation_id'=>(string)$conversation['public_id']]);

    $conversation = mg_world_conversation_load_by_public_id($pdo, (string)$conversation['public_id']);
    mg_ok(['conversation' => mg_world_conversation_project($pdo, $conversation, $member)], 'Message sent.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.conversation_message_failed', 'World Canvas conversation message failed.', ['exception_class'=>$error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to send World Canvas message.', 500);
}
