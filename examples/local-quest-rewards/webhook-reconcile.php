<?php
declare(strict_types=1);

function lqr_webhook_payload_array(mixed $body): array
{
    return is_array($body) ? $body : [];
}

function lqr_webhook_value(array $payload, array $paths): string
{
    foreach ($paths as $path) {
        $cursor = $payload;
        foreach (explode('.', $path) as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                $cursor = null;
                break;
            }
            $cursor = $cursor[$part];
        }
        if ($cursor !== null && $cursor !== '') return (string)$cursor;
    }
    return '';
}

function lqr_webhook_status_from_event(string $event, array $payload): string
{
    $explicit = lqr_webhook_value($payload, ['status', 'reward.status', 'item.status', 'claim_status']);
    if ($explicit !== '') return $explicit;
    return match ($event) {
        'reward.queued' => 'queued',
        'reward.delivered' => 'delivered',
        'reward.viewed_in_app' => 'viewed_in_app',
        'reward.claimed_in_app' => 'claimed_in_app',
        'reward.redeem_started' => 'redeem_started',
        'reward.redeem_handoff' => 'redeem_handoff',
        'reward.redeemed' => 'redeemed',
        'reward.failed' => 'failed',
        default => '',
    };
}

function lqr_reconcile_microgifter_webhook(array &$state, string $event, array $payload, string $deliveryId = ''): array
{
    $rewardId = lqr_webhook_value($payload, ['reward_id', 'reward.id', 'id']);
    $itemId = lqr_webhook_value($payload, ['item_id', 'pppm_item_id', 'item.id', 'reward.pppm_item_id']);
    $externalUserId = lqr_webhook_value($payload, ['external_user_id', 'recipient.external_user_id', 'linked_account.external_user_id']);
    $status = lqr_webhook_status_from_event($event, $payload);
    $matched = [];

    if (!isset($state['users']) || !is_array($state['users'])) return ['matched' => [], 'reason' => 'no_users'];

    foreach ($state['users'] as $userId => $user) {
        if (!is_array($user)) continue;
        if ($externalUserId !== '' && (string)($user['external_user_id'] ?? '') !== $externalUserId) continue;
        foreach (($user['rewards'] ?? []) as $questId => $reward) {
            if (!is_array($reward)) continue;
            $localRewardId = (string)($reward['reward_id'] ?? '');
            $localItemId = function_exists('lqr_reward_item_id') ? lqr_reward_item_id($reward) : (string)($reward['item_id'] ?? '');
            $rewardMatches = $rewardId !== '' && $localRewardId === $rewardId;
            $itemMatches = $itemId !== '' && $localItemId === $itemId;
            if (!$rewardMatches && !$itemMatches) continue;

            if ($status !== '') $reward['status'] = $status;
            if ($itemId !== '') $reward['item_id'] = $itemId;
            $reward['last_webhook_event'] = $event;
            $reward['last_webhook_delivery'] = $deliveryId;
            $reward['last_webhook_at'] = gmdate('c');
            $reward['last_webhook_payload'] = $payload;

            if (in_array($event, ['reward.claimed_in_app', 'reward.redeem_started', 'reward.redeem_handoff', 'reward.redeemed'], true)) {
                $reward['claim_status'] = $status !== '' ? $status : $event;
                $reward['claim_report_status'] = 'confirmed_by_microgifter_webhook';
            }

            $user['rewards'][$questId] = $reward;
            $user['updated_at'] = gmdate('c');
            $state['users'][(string)$userId] = $user;
            $matched[] = ['user_id' => (string)$userId, 'quest_id' => (string)$questId, 'reward_id' => $localRewardId, 'item_id' => $itemId ?: $localItemId];
        }
    }

    if ($matched) {
        lqr_add_event($state, 'webhook.reconciled', 'Microgifter webhook reconciled into local wallet state.', ['event'=>$event, 'delivery'=>$deliveryId, 'matched'=>$matched]);
    }

    return ['matched' => $matched, 'reward_id' => $rewardId, 'item_id' => $itemId, 'status' => $status];
}
