<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/public/campaigns/_followups.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';

function mg_wallet_lifecycle_contact(PDO $pdo, array $item): array
{
    if (empty($item['contact_id'])) return [];
    try {
        $stmt = $pdo->prepare('SELECT cc.public_id, cc.email, cc.phone, cc.name, c.campaign_type, c.title campaign_title FROM campaign_contacts cc LEFT JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.id=? LIMIT 1');
        $stmt->execute([(int)$item['contact_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function mg_wallet_lifecycle_followup(PDO $pdo, array $item, string $eventType, array $context = []): array
{
    if (empty($item['campaign_id']) || empty($item['merchant_user_id']) || !function_exists('mg_campaign_followup_schedule')) {
        return ['scheduled' => 0, 'skipped' => true];
    }
    try {
        return mg_campaign_followup_schedule($pdo, [
            'merchant_user_id' => (int)$item['merchant_user_id'],
            'campaign_id' => (int)$item['campaign_id'],
            'contact_id' => !empty($item['contact_id']) ? (int)$item['contact_id'] : null,
            'wallet_item_id' => (int)$item['id'],
            'trigger_event' => $eventType,
            'context' => $context + ['wallet_item_id' => (string)$item['public_id']],
        ]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'wallet.lifecycle.followup_failed', 'Unable to schedule wallet lifecycle follow-up.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($item['merchant_user_id'] ?? 0));
        return ['scheduled' => 0, 'skipped' => true, 'error' => 'followup_failed'];
    }
}

function mg_wallet_lifecycle_crm(PDO $pdo, array $item, string $eventType, int $actorUserId = 0, array $actor = [], array $context = []): array
{
    $contact = mg_wallet_lifecycle_contact($pdo, $item);
    $campaignType = (string)($contact['campaign_type'] ?? $item['campaign_type'] ?? $item['source_type'] ?? 'wallet_reward');
    $crmEvent = $eventType === 'wallet_item.redeemed' ? 'reward.redeemed' : ($eventType === 'wallet_item.claimed' ? 'reward.claimed' : $eventType);
    try {
        return mg_merchant_crm_record_event($pdo, [
            'merchant_user_id' => (int)$item['merchant_user_id'],
            'campaign_id' => !empty($item['campaign_id']) ? (int)$item['campaign_id'] : null,
            'campaign_type' => $campaignType,
            'event_type' => $crmEvent,
            'source_type' => 'wallet_lifecycle',
            'source_public_id' => (string)$item['public_id'],
            'user_id' => $actorUserId > 0 ? $actorUserId : (!empty($item['user_id']) ? (int)$item['user_id'] : null),
            'email' => (string)($actor['email'] ?? $contact['email'] ?? ''),
            'phone' => (string)($contact['phone'] ?? ''),
            'name' => (string)($actor['display_name'] ?? $actor['full_name'] ?? $contact['name'] ?? ''),
            'value_cents' => (int)($item['value_cents_snapshot'] ?? 0),
            'metadata' => $context + ['wallet_item_id' => (string)$item['public_id'], 'wallet_status_event' => $eventType],
        ]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'wallet.lifecycle.crm_failed', 'Unable to record wallet lifecycle CRM event.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($item['merchant_user_id'] ?? 0));
        return ['schema_ready' => false, 'skipped' => true, 'error' => 'crm_failed'];
    }
}

function mg_wallet_lifecycle_notify(PDO $pdo, array $item, string $eventType, int $actorUserId = 0, array $actor = [], array $context = []): array
{
    $title = (string)($item['title_snapshot'] ?? 'reward');
    $result = [];
    if ($eventType === 'wallet_item.claimed' && !empty($item['merchant_user_id'])) {
        $name = trim((string)($actor['display_name'] ?? $actor['full_name'] ?? $actor['email'] ?? 'A customer'));
        $result['merchant_notification_id'] = mg_create_notification($pdo, (int)$item['merchant_user_id'], 'merchant_reward_claimed', 'Reward claimed', $name . ' claimed ' . $title, '/merchant-crm.php', ['actor_user_id' => $actorUserId ?: null, 'event_key' => 'wallet.claimed.' . (string)$item['public_id'], 'wallet_item_id' => (int)$item['id']]);
    }
    if ($eventType === 'wallet_item.redeemed') {
        if (!empty($item['merchant_user_id'])) {
            $result['merchant_notification_id'] = mg_create_notification($pdo, (int)$item['merchant_user_id'], 'merchant_reward_redeemed', 'Reward redeemed', $title . ' was redeemed.', '/merchant-crm.php', ['actor_user_id' => $actorUserId ?: null, 'event_key' => 'wallet.redeemed.merchant.' . (string)$item['public_id'], 'wallet_item_id' => (int)$item['id']]);
        }
        $recipientId = $actorUserId ?: (!empty($item['user_id']) ? (int)$item['user_id'] : 0);
        if ($recipientId > 0) {
            $result['customer_notification_id'] = mg_create_notification($pdo, $recipientId, 'reward_redeemed', 'Reward redeemed', 'Your ' . $title . ' reward was redeemed.', '/inbox.php', ['actor_user_id' => (int)($item['merchant_user_id'] ?? 0), 'event_key' => 'wallet.redeemed.customer.' . (string)$item['public_id'], 'wallet_item_id' => (int)$item['id']]);
        }
    }
    return $result;
}

function mg_wallet_lifecycle_automation(PDO $pdo, array $item, string $eventType, int $actorUserId = 0, array $actor = [], array $context = []): array
{
    $crm = mg_wallet_lifecycle_crm($pdo, $item, $eventType, $actorUserId, $actor, $context);
    $followups = mg_wallet_lifecycle_followup($pdo, $item, $eventType, $context + ['merchant_crm' => $crm]);
    $notifications = mg_wallet_lifecycle_notify($pdo, $item, $eventType, $actorUserId, $actor, $context + ['merchant_crm' => $crm, 'followups' => $followups]);
    return ['merchant_crm' => $crm, 'followups' => $followups, 'notifications' => $notifications];
}
