<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_messaging.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';

function mg_store_campaign_recommendation_note(mixed $value): string
{
    $note = trim((string)$value);
    if (mb_strlen($note) > 1000) throw new InvalidArgumentException('Recommendation note is too long.');
    return $note;
}

function mg_store_campaign_recommendation_campaign(PDO $pdo, int $merchantUserId, string $campaignPublicId, bool $forUpdate = false): array
{
    $campaignPublicId = mg_store_safe_public_id($campaignPublicId, 'Campaign');
    $sql = "SELECT c.id,c.public_id,c.public_slug,c.title,c.description,c.campaign_type,c.status,c.starts_at,c.ends_at,
                   c.quantity_limit,c.issued_count,c.per_user_limit,
                   rt.public_id reward_template_public_id,rt.title reward_template_title,rt.status reward_template_status
            FROM campaigns c
            LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.merchant_user_id=c.merchant_user_id
            WHERE c.public_id=? AND c.merchant_user_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$campaignPublicId, $merchantUserId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) throw new RuntimeException('Campaign is not available.');
    if ((string)$campaign['status'] !== 'active') throw new RuntimeException('Campaign is not active.');
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > time()) throw new RuntimeException('Campaign has not started yet.');
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < time()) throw new RuntimeException('Campaign has ended.');

    $type = (string)$campaign['campaign_type'];
    $definition = mg_campaign_type_registry()[$type] ?? null;
    if (!is_array($definition) || empty($definition['public_enabled']) || !empty($definition['internal_only'])) {
        throw new RuntimeException('Campaign does not have an active public participation page.');
    }
    if (empty($campaign['reward_template_public_id']) || (string)$campaign['reward_template_status'] !== 'active') {
        throw new RuntimeException('Campaign requires an active attached reward before it can be recommended.');
    }
    if ($campaign['quantity_limit'] !== null && (int)$campaign['issued_count'] >= (int)$campaign['quantity_limit']) {
        throw new RuntimeException('Campaign reward inventory has been exhausted.');
    }

    $publicPath = trim((string)($definition['public_path'] ?? ''));
    if ($publicPath === '' || $publicPath[0] !== '/' || str_starts_with($publicPath, '//')) {
        throw new RuntimeException('Campaign participation route is unavailable.');
    }
    $campaignRef = trim((string)($campaign['public_slug'] ?? '')) ?: (string)$campaign['public_id'];
    $campaign['public_path'] = $publicPath;
    $campaign['landing_url'] = $publicPath . '?campaign=' . rawurlencode($campaignRef);
    $campaign['type_label'] = (string)($definition['label'] ?? ucwords(str_replace('_', ' ', $type)));
    return $campaign;
}

function mg_store_send_campaign_recommendation_notification(
    PDO $pdo,
    array $merchantUser,
    string $sessionPublicId,
    string $campaignPublicId,
    string $note,
    string $idempotencyKey
): array {
    mg_store_canvas_core_schema_require($pdo);
    mg_store_manual_ops_require_schema($pdo);

    $merchantUserId = (int)($merchantUser['id'] ?? 0);
    if ($merchantUserId < 1) throw new RuntimeException('Merchant account is required.');
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $campaignPublicId = mg_store_safe_public_id($campaignPublicId, 'Campaign');
    $note = mg_store_campaign_recommendation_note($note);
    $idempotencyKey = mg_store_manual_ops_idempotency_key($idempotencyKey);
    $requestHash = mg_store_manual_ops_request_hash([
        'merchant_user_id' => $merchantUserId,
        'session_id' => $sessionPublicId,
        'campaign_id' => $campaignPublicId,
        'note' => $note,
    ]);

    $pdo->beginTransaction();
    try {
        $session = mg_store_manual_ops_session($pdo, $merchantUserId, $sessionPublicId, true);
        if (empty($session['active_key']) || !in_array((string)$session['status'], ['entered','active','idle'], true) || !empty($session['exited_at'])) {
            throw new RuntimeException('Active customer session is not available.');
        }
        if (strtotime((string)$session['last_active_at']) < (time() - (MG_STORE_EXPIRE_MINUTES * 60))) {
            throw new RuntimeException('Customer session has expired. Refresh the Store Canvas.');
        }
        $customerUserId = (int)($session['customer_user_id'] ?? 0);
        if ($customerUserId < 1 || $customerUserId === $merchantUserId) {
            throw new RuntimeException('Active customer session cannot receive recommendations.');
        }

        $receipt = mg_store_manual_ops_receipt_claim(
            $pdo,
            $merchantUserId,
            $customerUserId,
            (int)$session['id'],
            $merchantUserId,
            'campaign_recommendation',
            $idempotencyKey,
            $requestHash
        );
        if (!empty($receipt['duplicate'])) {
            $result = is_array($receipt['response']) ? $receipt['response'] : [];
            $result['duplicate'] = true;
            $pdo->commit();
            return $result;
        }

        mg_store_manual_ops_assert_message_allowed($pdo, $merchantUserId, $customerUserId, true);
        $campaign = mg_store_campaign_recommendation_campaign($pdo, $merchantUserId, $campaignPublicId, true);

        $recentStmt = $pdo->prepare(
            "SELECT 1 FROM mg_store_session_events
             WHERE store_session_id=? AND event_type='campaign_recommendation_sent'
               AND created_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE)
               AND JSON_UNQUOTE(JSON_EXTRACT(event_data_json,'$.campaign_id'))=?
             LIMIT 1"
        );
        $recentStmt->execute([(int)$session['id'], (string)$campaign['public_id']]);
        if ($recentStmt->fetchColumn()) {
            throw new RuntimeException('This campaign was recommended to the customer within the last five minutes.');
        }

        $merchantLabel = trim((string)($merchantUser['display_name'] ?? $merchantUser['name'] ?? 'Merchant')) ?: 'Merchant';
        try {
            $labelStmt = $pdo->prepare('SELECT display_name FROM public_profiles WHERE user_id=? LIMIT 1');
            $labelStmt->execute([$merchantUserId]);
            $merchantLabel = trim((string)($labelStmt->fetchColumn() ?: $merchantLabel)) ?: 'Merchant';
        } catch (Throwable) {}

        $recommendationId = (string)$receipt['public_id'];
        $actionUrl = '/campaign-recommendation.php?recommendation=' . rawurlencode($recommendationId);
        $body = $note !== ''
            ? $note
            : 'Complete the campaign requirements to receive the approved campaign reward in your wallet.';
        $notificationId = mg_create_notification(
            $pdo,
            $customerUserId,
            'campaign_recommendation',
            $merchantLabel . ' recommends: ' . (string)$campaign['title'],
            $body,
            $actionUrl,
            [
                'actor_user_id' => $merchantUserId,
                'event_key' => 'campaign.recommendation.' . substr(hash('sha256', $recommendationId), 0, 48),
                'merchant_user_id' => $merchantUserId,
                'campaign_public_id' => (string)$campaign['public_id'],
                'campaign_type' => (string)$campaign['campaign_type'],
                'reward_template_public_id' => (string)$campaign['reward_template_public_id'],
                'store_session_id' => $sessionPublicId,
                'recommendation_id' => $recommendationId,
                'source_system' => 'store_canvas',
                'source_channel' => 'merchant_canvas_campaign_recommendation',
                'reward_issue_policy' => 'campaign_completion_only',
            ]
        );
        if ($notificationId === '') throw new RuntimeException('Customer notification delivery is disabled or unavailable.');

        mg_store_log_event($pdo, $session, 'campaign_recommendation_sent', 'Campaign recommendation sent', [
            'campaign_id' => (string)$campaign['public_id'],
            'campaign_title' => (string)$campaign['title'],
            'campaign_type' => (string)$campaign['campaign_type'],
            'reward_template_id' => (string)$campaign['reward_template_public_id'],
            'reward_template_title' => (string)$campaign['reward_template_title'],
            'notification_id' => $notificationId,
            'recommendation_id' => $recommendationId,
            'landing_url' => (string)$campaign['landing_url'],
            'reward_issued' => false,
            'reward_issue_policy' => 'campaign_completion_only',
            'initiated_by_user_id' => $merchantUserId,
            'action_receipt_id' => $recommendationId,
        ]);

        $result = [
            'recommendation_id' => $recommendationId,
            'notification_id' => $notificationId,
            'action_url' => $actionUrl,
            'campaign' => [
                'id' => (string)$campaign['public_id'],
                'title' => (string)$campaign['title'],
                'campaign_type' => (string)$campaign['campaign_type'],
                'type_label' => (string)$campaign['type_label'],
                'landing_url' => (string)$campaign['landing_url'],
                'reward_template_id' => (string)$campaign['reward_template_public_id'],
                'reward_template_title' => (string)$campaign['reward_template_title'],
            ],
            'delivery' => [
                'channel' => 'notification',
                'reward_issued' => false,
                'reward_destination' => 'wallet_after_campaign_completion',
                'pppm_projection' => 'after_wallet_issue',
            ],
            'duplicate' => false,
        ];
        mg_store_manual_ops_receipt_complete($pdo, (int)$receipt['id'], $notificationId, $result);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_event('store_canvas.campaign_recommendation_sent', [
        'recommendation_id' => $result['recommendation_id'] ?? null,
        'notification_id' => $result['notification_id'] ?? null,
        'campaign_id' => $campaignPublicId,
        'session_id' => $sessionPublicId,
        'reward_issued' => false,
    ], $merchantUserId);

    return $result;
}
