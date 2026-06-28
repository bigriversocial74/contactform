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
        "SELECT s.*,cp.display_name customer_name,mp.display_name merchant_name,fp.headline source_post_headline
         FROM mg_store_sessions s
         LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id
         LEFT JOIN public_profiles mp ON mp.user_id=s.merchant_user_id
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

function mg_canvas_auto_chat_behavior(array $input): array
{
    $raw = is_array($input['merchant_behavior'] ?? null) ? $input['merchant_behavior'] : [];
    $mode = strtolower(trim((string)($raw['interaction_mode'] ?? 'guided')));
    if (!in_array($mode, ['guided','proactive','observe_only','manual_first'], true)) $mode = 'guided';
    $tone = strtolower(trim((string)($raw['response_tone'] ?? 'warm_professional')));
    if (!in_array($tone, ['warm_professional','concise','hospitality','sales_assist','premium'], true)) $tone = 'warm_professional';
    $handoff = strtolower(trim((string)($raw['handoff_behavior'] ?? 'offer_handoff')));
    if (!in_array($handoff, ['offer_handoff','always_offer','never_offer'], true)) $handoff = 'offer_handoff';
    $triggerReaction = strtolower(trim((string)($raw['trigger_reaction'] ?? 'message_when_triggered')));
    if (!in_array($triggerReaction, ['message_when_triggered','reward_context','analytics_only'], true)) $triggerReaction = 'message_when_triggered';
    $message = trim((string)($raw['greeting_message'] ?? ''));
    if (mb_strlen($message) > 1000) $message = mb_substr($message, 0, 1000);
    return [
        'interaction_mode'=>$mode,
        'response_tone'=>$tone,
        'handoff_behavior'=>$handoff,
        'trigger_reaction'=>$triggerReaction,
        'greeting_message'=>$message,
        'auto_greet'=>!empty($raw['auto_greet']),
        'recommend_campaigns'=>!empty($raw['recommend_campaigns']),
    ];
}

function mg_canvas_auto_chat_render_template(string $template, array $session, string $firstName, string $source): string
{
    $merchantName = trim((string)($session['merchant_name'] ?? 'Merchant'));
    $message = strtr($template, [
        '{first_name}' => $firstName,
        '{source}' => $source,
        '{merchant_name}' => $merchantName !== '' ? $merchantName : 'Merchant',
    ]);
    $message = trim(preg_replace('/\s+/', ' ', $message) ?: $message);
    return $message !== '' ? mb_substr($message, 0, 1000) : '';
}

function mg_canvas_auto_chat_tone_suffix(array $behavior): string
{
    if (($behavior['handoff_behavior'] ?? '') === 'always_offer') {
        return ' I can also hand this off to a person if you want help.';
    }
    if (empty($behavior['recommend_campaigns'])) return '';
    return match ($behavior['response_tone'] ?? 'warm_professional') {
        'concise' => ' I can point you to the best offer.',
        'hospitality' => ' I am happy to help you find the right reward or local gift.',
        'sales_assist' => ' I can help match you with the best current campaign.',
        'premium' => ' I can curate the best available offer for you.',
        default => ' I can help with rewards, offers, or questions while you are browsing.',
    };
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
    $behavior = mg_canvas_auto_chat_behavior($input);

    if ($behavior['interaction_mode'] === 'observe_only') {
        mg_ok(['sent'=>false,'disabled'=>true,'context'=>$context,'reason'=>'merchant_observe_only'], 'Merchant avatar is set to observe only.');
        return;
    }
    if ($context === 'merchant_proximity' && !$behavior['auto_greet'] && $behavior['interaction_mode'] !== 'proactive') {
        mg_ok(['sent'=>false,'disabled'=>true,'context'=>$context,'reason'=>'merchant_auto_greet_disabled'], 'Merchant avatar auto-greeting is disabled.');
        return;
    }

    mg_rate_limit('merchant_canvas.auto_chat', 'user:' . $merchantUserId, 45, 60);
    $session = mg_canvas_auto_chat_session($pdo, $merchantUserId, $sessionId);
    $customerName = trim((string)($session['customer_name'] ?? '')) ?: 'there';
    $firstName = preg_split('/\s+/u', $customerName, -1, PREG_SPLIT_NO_EMPTY)[0] ?? $customerName;
    $source = trim((string)($session['source_post_headline'] ?? ''));
    $key = $context . ':' . $sessionId . ':' . $peerSessionId . ':' . $behavior['interaction_mode'] . ':' . $behavior['response_tone'];

    if (mg_canvas_auto_chat_recent($pdo, $session, $key)) {
        mg_ok(['sent'=>false,'recent'=>true,'context'=>$context], 'Auto chat recently sent.');
        return;
    }

    $customGreeting = mg_canvas_auto_chat_render_template((string)$behavior['greeting_message'], $session, $firstName, $source);
    if ($context === 'merchant_proximity' && $customGreeting !== '') {
        $body = $customGreeting;
        if (!str_contains($body, 'offer') && !str_contains($body, 'reward') && !str_contains($body, 'campaign')) {
            $body .= mg_canvas_auto_chat_tone_suffix($behavior);
        }
    } else {
        $body = match ($context) {
            'avatar_overlap' => 'Hi ' . $firstName . ' — I see you are browsing with another customer in the store. I can help compare offers, rewards, or gift options if you need anything.',
            'manual' => 'Hi ' . $firstName . ' — thanks for being in the store. I am here if you want help with a reward, offer, or gift option.',
            default => 'Hi ' . $firstName . ' — welcome in.' . mg_canvas_auto_chat_tone_suffix($behavior) . ($source !== '' ? ' You came in from "' . mb_substr($source, 0, 80) . '".' : ''),
        };
    }

    $message = mg_store_send_direct_message_via_messaging($pdo, $merchantUserId, $sessionId, $body);
    mg_store_log_event($pdo, $session, 'auto_chat_sent', 'Merchant auto chat sent', [
        'context'=>$context,
        'key'=>$key,
        'peer_session_id'=>$peerSessionId ?: null,
        'message_id'=>$message['id'] ?? null,
        'merchant_behavior'=>$behavior,
    ]);
    mg_ok(['sent'=>true,'context'=>$context,'message'=>$message,'merchant_behavior'=>$behavior], 'Auto chat sent through Messages.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.auto_chat_failed', 'Merchant canvas auto chat failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to send auto chat.', 500);
}
