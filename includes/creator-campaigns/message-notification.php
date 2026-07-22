<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/api/communications/_communications.php';

function mg_creator_campaign_message_notify(PDO $pdo, array $context, array $participant, int $actorUserId, string $messagePublicId, string $body, string $kind): array
{
    $senderName = mg_notification_user_label($pdo, $actorUserId);
    $title = $kind === 'system' ? 'Creator Campaign update' : 'New Creator Campaign message';
    $notificationIds = [];
    foreach (mg_creator_campaign_message_recipients($pdo, (int)$context['thread_id'], $actorUserId) as $recipient) {
        if (empty($recipient['notifications_enabled'])) continue;
        if (!empty($recipient['muted_until']) && strtotime((string)$recipient['muted_until']) > time()) continue;
        $notificationId = mg_create_notification(
            $pdo,
            (int)$recipient['user_id'],
            'creator_campaign_message',
            $title,
            ($kind === 'system' ? '' : $senderName . ': ') . mb_substr($body, 0, 500),
            '/messages.php?thread=' . rawurlencode((string)$context['thread_public_id']),
            [
                'actor_user_id'=>$actorUserId,
                'event_key'=>'creator_campaign.message.thread.' . strtolower((string)$context['thread_public_id']),
                'aggregate'=>true,
                'merchant_user_id'=>(int)$participant['workspace_owner_user_id'],
                'message_id'=>$messagePublicId,
                'thread_id'=>(int)$context['thread_id'],
                'thread_public_id'=>(string)$context['thread_public_id'],
                'source_type'=>$kind === 'system' ? 'creator_campaign_system' : 'creator_campaign_message',
                'source_reference'=>(string)$participant['campaign_public_id'],
                'source_system'=>'creator_campaigns',
                'source_label'=>'Creator Campaign',
                'campaign_id'=>(string)$participant['campaign_public_id'],
                'participant_id'=>(string)$participant['public_id'],
            ]
        );
        if ($notificationId !== '') $notificationIds[] = $notificationId;
    }
    return $notificationIds;
}
