<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/store/_canvas_rewards.php';
require_once dirname(__DIR__) . '/store/_canvas_manual_operations.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

if (!mg_user_has_merchant_access($user, $pdo)) {
    mg_fail('Merchant access required.', 403);
}

$lockName = '';
$lockHeld = false;

try {
    $merchantUserId = (int)$user['id'];
    $sessionId = mg_store_safe_public_id($input['session_id'] ?? '', 'Store session');
    $campaignId = mg_store_safe_public_id($input['campaign_id'] ?? '', 'Campaign');
    $templateId = trim((string)($input['reward_template_id'] ?? $input['template_id'] ?? ''));
    $templateId = $templateId !== '' ? mg_store_safe_public_id($templateId, 'Reward template') : null;
    $note = $input['note'] ?? '';
    $expirationDays = $input['expiration_days'] ?? null;
    $expiresAt = $input['expires_at'] ?? null;
    $idempotencyKey = mg_store_manual_ops_idempotency_key($input['idempotency_key'] ?? '');
    mg_store_manual_ops_require_schema($pdo);

    $campaignReward = $pdo->prepare(
        "SELECT rt.public_id
         FROM campaigns c
         INNER JOIN reward_templates rt
           ON rt.id=c.reward_template_id
          AND rt.merchant_user_id=c.merchant_user_id
          AND rt.status='active'
         WHERE c.public_id=?
           AND c.merchant_user_id=?
           AND c.status='active'
         LIMIT 1"
    );
    $campaignReward->execute([$campaignId, $merchantUserId]);
    $attachedTemplateId = trim((string)($campaignReward->fetchColumn() ?: ''));
    if ($attachedTemplateId === '') {
        throw new RuntimeException('Selected campaign requires an active attached reward template before Store Canvas rewards can be sent.');
    }
    if ($templateId !== null && !hash_equals($attachedTemplateId, $templateId)) {
        throw new InvalidArgumentException('Store Canvas must use the reward template attached to the selected campaign.');
    }
    $templateId = $attachedTemplateId;

    $requestHash = mg_store_manual_ops_request_hash([
        'merchant_user_id' => $merchantUserId,
        'session_id' => $sessionId,
        'campaign_id' => $campaignId,
        'reward_template_id' => $templateId,
        'expiration_days' => (string)$expirationDays,
        'expires_at' => (string)$expiresAt,
        'note' => trim((string)$note),
    ]);

    $existingReceipt = $pdo->prepare(
        "SELECT request_hash,response_json
         FROM mg_merchant_canvas_action_receipts
         WHERE merchant_user_id=? AND action_type='manual_reward' AND idempotency_key=? AND status='completed'
         LIMIT 1"
    );
    $existingReceipt->execute([$merchantUserId, $idempotencyKey]);
    $receiptRow = $existingReceipt->fetch(PDO::FETCH_ASSOC);
    if ($receiptRow) {
        if (!hash_equals((string)$receiptRow['request_hash'], $requestHash)) {
            throw new InvalidArgumentException('This idempotency key was already used for a different reward request.');
        }
        $existingResponse = mg_store_manual_ops_decode_json($receiptRow['response_json'] ?? '');
        $existingResponse['duplicate'] = true;
        mg_ok(['reward' => $existingResponse], 'Reward already issued.');
        return;
    }

    $lockName = 'mg_canvas_reward_' . hash('sha256', $merchantUserId . '|' . $idempotencyKey);
    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lockStmt->execute([$lockName]);
    $lockHeld = (int)$lockStmt->fetchColumn() === 1;
    if (!$lockHeld) throw new RuntimeException('Reward delivery is already processing. Retry with the same request key.');

    mg_rate_limit('merchant_canvas.send_reward', 'user:' . $merchantUserId, 60, 60);
    $reward = mg_store_reward_issue($pdo, $user, $sessionId, $campaignId, $templateId, (string)$note, $expirationDays, $expiresAt, $idempotencyKey);

    $sessionStmt = $pdo->prepare('SELECT id,customer_user_id FROM mg_store_sessions WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $sessionStmt->execute([$sessionId, $merchantUserId]);
    $sessionRow = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $receiptPublicId = mg_public_uuid();
    $receipt = $pdo->prepare(
        "INSERT INTO mg_merchant_canvas_action_receipts
         (public_id,merchant_user_id,customer_user_id,store_session_id,action_type,idempotency_key,request_hash,status,result_public_id,response_json,initiated_by_user_id,created_at,updated_at,completed_at)
         VALUES (?,?,?,?, 'manual_reward',?,?, 'completed',?,?,?,NOW(),NOW(),NOW())
         ON DUPLICATE KEY UPDATE response_json=VALUES(response_json),result_public_id=VALUES(result_public_id),status='completed',completed_at=NOW(),updated_at=NOW()"
    );
    $receipt->execute([
        $receiptPublicId,
        $merchantUserId,
        (int)($sessionRow['customer_user_id'] ?? 0),
        isset($sessionRow['id']) ? (int)$sessionRow['id'] : null,
        $idempotencyKey,
        $requestHash,
        (string)($reward['wallet_item_id'] ?? ''),
        json_encode($reward, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $merchantUserId,
    ]);

    mg_ok(['reward' => $reward], !empty($reward['duplicate']) ? 'Reward already issued.' : 'Reward sent to customer IN/OUT Box.', !empty($reward['duplicate']) ? 200 : 201);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    $message = strtolower($error->getMessage());
    $status = str_contains($message, 'limit') || str_contains($message, 'already') || str_contains($message, 'processing') || str_contains($message, 'expired') ? 409 : (str_contains($message, 'setup is incomplete') ? 503 : 400);
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant_canvas.send_reward_failed', 'Merchant canvas reward send failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to send reward.', 500);
} finally {
    if ($lockHeld && $lockName !== '') {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable) {
            // The connection release also frees the advisory lock.
        }
    }
}
