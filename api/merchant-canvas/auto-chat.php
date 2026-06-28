<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_messaging.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_canvas_auto_chat_session(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
{
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas');
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $stmt = $pdo->prepare(
        "SELECT s.*,cp.display_name customer_name,fp.headline source_post_headline
         FROM mg_store_sessions s
         LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id
         LEFT JOIN feed_posts fp ON fp.id=s.source_feed_post_id
         WHERE s.public_id=? AND s.merchant_user_id=? AND s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL
         LIMIT 1"
    );
    $stmt->execute([$sessionPublicId, $merchantUserId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new RuntimeException('Active customer session is not available.');
    return $session;
}

function mg_canvas_auto_chat_recent(PDO $pdo, array $session, string $key): bool
{
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM mg_store_session_events
             WHERE store_session_id=? AND event_type='auto_chat_sent' AND event_data_json LIKE ? AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)
             LIMIT 1"
        );
        $stmt->execute([(int)$session['id'], '%' . $key . '%']);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

try {
    $merchantUserId = (int)$user['id'];
    $sessionId = mg_store_safe_public_id($input['session_id'] ?? '', 'Store session');
    $context = strtolower(trim((string)($input['context'] ?? 'merchant_proximity')));
    if (!in_array($context, ['merchant_proximity','avatar_overlap','manual'], true)) {
        $context = 'merchant_proximity';
    }
    $peerSessionId = trim((string)($input['peer_session_id'] ?? ''));
    if ($peerSessionId !== '') $peerSessionId = mg_store_safe_public_id($peerSessionId, 'Peer session');

    mg_rate_limit('merchant_canvas.auto_chat', 'user:' . $merchantUserId, 45, 60);
    $session = mg_canvas_auto_chat_session($pdo, $merchantUserId, $sessionId);
    $customerName = trim((string)($session['customer_name'] ?? '')) ?: 'there';
    $firstName = preg_split('/\s+/u', $customerName, -1, PREG_SPLIT_NO_EMPTY)[0] ?? $customerName;
    $source = trim((string)($session['source_post_headline'] ?? ''));
    $key = $context . ':' . $sessionId . ':' . $peerSessionId;

    if (mg_canvas_auto_chat_recent($pdo, $session, $key)) {
        mg_ok(['sent'=>false,'recent'=>true,'context'=>$context], 'Auto chat recently sent.');
        return;
    }

    $body = match ($context) {
        'avatar_overlap' => 'Hi ' . $firstName . ' — I see you are browsing with another customer in the store. I can help compare offers, rewards, or gift options if you need anything.',
        'manual' => 'Hi ' . $firstName . ' — thanks for being in the store. I am here if you want help with a reward, offer, or gift option.',
        default => 'Hi ' . $firstName . ' — welcome in. I can help with rewards, offers, or questions while you are browsing' . ($source !== '' ? ' "' . mb_substr($source, 0, 80) . '"' : '') . '.',
    };

    $message = mg_store_send_direct_message_via_messaging($pdo, $merchantUserId, $sessionId, $body);
    mg_store_log_event($pdo, $session, 'auto_chat_sent', 'Merchant auto chat sent', ['context'=>$context,'key'=>$key,'peer_session_id'=>$peerSessionId ?: null,'message_id'=>$message['id'] ?? null]);
    mg_ok(['sent'=>true,'context'=>$context,'message'=>$message], 'Auto chat sent through Messages.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.auto_chat_failed', 'Merchant canvas auto chat failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to send auto chat.', 500);
}
