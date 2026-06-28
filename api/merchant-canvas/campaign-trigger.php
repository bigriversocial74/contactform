<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_messaging.php';
require_once dirname(__DIR__) . '/store/_canvas_rewards.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

function mg_canvas_trigger_session(PDO $pdo, int $merchantUserId, string $sessionPublicId): array
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

function mg_canvas_trigger_recent(PDO $pdo, array $session, string $key): bool
{
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM mg_store_session_events
             WHERE store_session_id=? AND event_type='campaign_trigger_zone' AND event_data_json LIKE ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MINUTE)
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
    $triggerKey = trim((string)($input['trigger_key'] ?? 'in_out_box_zone'));
    $triggerKey = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $triggerKey) ?: 'in_out_box_zone';
    $triggerKey = mb_substr($triggerKey, 0, 90);
    $triggerLabel = trim((string)($input['trigger_label'] ?? 'IN/OUT Box Campaign Trigger'));
    $triggerLabel = mb_substr($triggerLabel !== '' ? $triggerLabel : 'IN/OUT Box Campaign Trigger', 0, 140);

    mg_rate_limit('merchant_canvas.campaign_trigger', 'user:' . $merchantUserId, 80, 60);
    $session = mg_canvas_trigger_session($pdo, $merchantUserId, $sessionId);
    $customerName = trim((string)($session['customer_name'] ?? '')) ?: 'there';
    $firstName = preg_split('/\s+/u', $customerName, -1, PREG_SPLIT_NO_EMPTY)[0] ?? $customerName;
    $eventKey = $triggerKey . ':' . $sessionId;

    if (mg_canvas_trigger_recent($pdo, $session, $eventKey)) {
        mg_ok(['triggered'=>false,'recent'=>true,'trigger_key'=>$triggerKey], 'Campaign trigger recently fired.');
        return;
    }

    $messageResult = null;
    $rewardResult = null;
    $messageBody = 'Hi ' . $firstName . ' — you just entered a campaign trigger zone in the store. I sent this to your IN/OUT Box so you can review the offer or ask questions.';

    try {
        $messageResult = mg_store_send_direct_message_via_messaging($pdo, $merchantUserId, $sessionId, $messageBody);
    } catch (Throwable $messageError) {
        mg_security_log('error', 'merchant_canvas.trigger_message_failed', 'Campaign trigger message failed.', ['exception_class'=>$messageError::class,'message'=>$messageError->getMessage()], $merchantUserId);
    }

    try {
        $options = mg_store_reward_options($pdo, $merchantUserId);
        $campaign = $options['campaigns'][0] ?? null;
        $template = $options['templates'][0] ?? null;
        if (is_array($campaign) && is_array($template) && !empty($campaign['id']) && !empty($template['id'])) {
            $rewardResult = mg_store_reward_issue(
                $pdo,
                $user,
                $sessionId,
                (string)$campaign['id'],
                (string)$template['id'],
                'Triggered by ' . $triggerLabel . ' on the Merchant Store Canvas.',
                null,
                null,
                'canvas-trigger:' . hash('sha256', $merchantUserId . '|' . $sessionId . '|' . $triggerKey . '|' . gmdate('YmdHi'))
            );
        }
    } catch (Throwable $rewardError) {
        mg_security_log('error', 'merchant_canvas.trigger_reward_failed', 'Campaign trigger reward failed.', ['exception_class'=>$rewardError::class,'message'=>$rewardError->getMessage()], $merchantUserId);
    }

    mg_store_log_event($pdo, $session, 'campaign_trigger_zone', $triggerLabel, [
        'trigger_key' => $triggerKey,
        'event_key' => $eventKey,
        'source_system' => 'store_canvas',
        'source_channel' => 'merchant_canvas_motion',
        'message_id' => is_array($messageResult) ? ($messageResult['id'] ?? null) : null,
        'thread_id' => is_array($messageResult) ? ($messageResult['thread_id'] ?? null) : null,
        'wallet_item_id' => is_array($rewardResult) ? ($rewardResult['wallet_item_id'] ?? null) : null,
        'campaign_id' => is_array($rewardResult) ? ($rewardResult['campaign_id'] ?? null) : null,
        'reward_template_id' => is_array($rewardResult) ? ($rewardResult['reward_template_id'] ?? null) : null,
    ]);

    mg_event('store_canvas.campaign_trigger_zone', ['session_id'=>$sessionId,'trigger_key'=>$triggerKey,'message_sent'=>is_array($messageResult),'reward_sent'=>is_array($rewardResult)], $merchantUserId);
    mg_ok([
        'triggered' => true,
        'trigger_key' => $triggerKey,
        'message_sent' => is_array($messageResult),
        'reward_sent' => is_array($rewardResult),
        'message' => $messageResult,
        'reward' => $rewardResult,
    ], 'Campaign trigger fired.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.campaign_trigger_failed', 'Merchant canvas campaign trigger failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to fire campaign trigger.', 500);
}
