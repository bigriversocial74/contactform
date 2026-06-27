<?php
declare(strict_types=1);

function lqr_claim_geo_from_post(): array
{
    return [
        'lat' => (string)($_POST['geo_lat'] ?? ''),
        'lng' => (string)($_POST['geo_lng'] ?? ''),
        'accuracy' => (string)($_POST['geo_accuracy'] ?? ''),
        'captured_at' => (string)($_POST['geo_captured_at'] ?? ''),
    ];
}

function lqr_claim_payload(array $user, string $questId, array $quest, array $reward, string $itemId, string $externalClaimId): array
{
    return [
        'reward_id' => (string)($reward['reward_id'] ?? ''),
        'item_id' => $itemId,
        'linked_account_id' => (string)$user['linked_account_id'],
        'external_user_id' => (string)$user['external_user_id'],
        'external_claim_id' => $externalClaimId,
        'claim_action' => 'claimed_in_app',
        'metadata' => [
            'app' => 'local-quest-rewards',
            'quest_id' => $questId,
            'quest_title' => (string)($quest['title'] ?? $questId),
            'qr_payload' => trim((string)($_POST['qr_payload'] ?? '')),
            'claim_geolocation' => lqr_claim_geo_from_post(),
            'retry_count' => (int)($reward['claim_report_attempts'] ?? 0),
        ],
    ];
}

function lqr_action_claim_reward_reported(array &$state, array $config, array &$user, string $questId, array $quests): string
{
    lqr_require_real_user($user);
    if (empty($user['rewards'][$questId]) || !is_array($user['rewards'][$questId])) {
        throw new RuntimeException('Reward not found in this Quest wallet.');
    }
    $reward = $user['rewards'][$questId];
    $itemId = lqr_reward_item_id($reward);
    if ($itemId === '') {
        lqr_action_check_status($state, $config, $user, $questId);
        $reward = $user['rewards'][$questId] ?? $reward;
        $itemId = lqr_reward_item_id(is_array($reward) ? $reward : []);
    }
    $rewardId = trim((string)($reward['reward_id'] ?? ''));
    if ($rewardId === '') throw new RuntimeException('Reward ID is missing.');

    $externalClaimId = (string)($reward['external_claim_id'] ?? '');
    if ($externalClaimId === '') {
        $externalClaimId = 'lqr_claim_' . substr(hash('sha256', $questId . '|' . (string)$user['external_user_id'] . '|' . $rewardId), 0, 24);
    }
    $quest = is_array($quests[$questId] ?? null) ? $quests[$questId] : [];
    $payload = lqr_claim_payload($user, $questId, $quest, $reward, $itemId, $externalClaimId);
    $attempts = (int)($reward['claim_report_attempts'] ?? 0) + 1;

    $response = lqr_call_microgifter($config, 'POST', '/api/public/v1/rewards/claim.php', $payload, [
        'X-Request-ID: req_' . $externalClaimId,
        'X-Idempotency-Key: ' . $externalClaimId,
    ]);

    $user['rewards'][$questId]['external_claim_id'] = $externalClaimId;
    $user['rewards'][$questId]['claim_report_attempts'] = $attempts;
    $user['rewards'][$questId]['last_claim_report_at'] = gmdate('c');
    $user['rewards'][$questId]['claim_report_payload'] = $payload;
    $user['rewards'][$questId]['claim_geo'] = lqr_claim_geo_from_post();
    $user['rewards'][$questId]['claim_qr_payload'] = trim((string)($_POST['qr_payload'] ?? ''));

    if ((int)$response['status'] >= 400) {
        $user['rewards'][$questId]['claim_status'] = 'claim_report_failed';
        $user['rewards'][$questId]['claim_report_status'] = 'microgifter_claim_api_failed';
        $user['rewards'][$questId]['claim_report_response'] = $response;
        $user['rewards'][$questId]['claim_retry_available'] = true;
        lqr_put_user($state, $user);
        lqr_add_event($state, 'quest.reward.claim_failed', 'Microgifter rejected the Quest reward claim report.', ['quest_id'=>$questId, 'reward_id'=>$rewardId, 'status'=>$response['status'], 'attempts'=>$attempts]);
        throw new RuntimeException('Microgifter claim report failed. Retry is available from the wallet.');
    }

    $body = is_array($response['body']) ? $response['body'] : [];
    $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
    $user['rewards'][$questId]['claim_status'] = 'claimed_in_quest_app';
    $user['rewards'][$questId]['claimed_at'] = gmdate('c');
    $user['rewards'][$questId]['claim_report_status'] = 'reported_to_microgifter';
    $user['rewards'][$questId]['claim_report_endpoint'] = '/api/public/v1/rewards/claim.php';
    $user['rewards'][$questId]['claim_report_response'] = $response;
    $user['rewards'][$questId]['claim_retry_available'] = false;
    $user['rewards'][$questId]['claim_sync_status'] = 'reported_waiting_for_webhook';
    $user['rewards'][$questId]['microgifter_event_id'] = (string)($data['microgifter_event_id'] ?? '');
    if (!empty($data['item_status'])) $user['rewards'][$questId]['item_status'] = (string)$data['item_status'];
    lqr_put_user($state, $user);
    lqr_add_event($state, 'quest.reward.claimed', 'Reward claimed in Local Quest and reported to Microgifter.', ['quest_id'=>$questId, 'reward_id'=>$rewardId, 'item_id'=>$itemId, 'external_claim_id'=>$externalClaimId, 'microgifter_event_id'=>(string)($data['microgifter_event_id'] ?? ''), 'attempts'=>$attempts]);
    return $attempts > 1 ? 'Claim report retry succeeded and was reported to Microgifter.' : 'Reward claimed in the Quest app and reported to Microgifter.';
}

function lqr_action_retry_claim_report(array &$state, array $config, array &$user, string $questId, array $quests): string
{
    if (empty($user['rewards'][$questId]) || !is_array($user['rewards'][$questId])) throw new RuntimeException('Reward not found in this Quest wallet.');
    $user['rewards'][$questId]['claim_retry_available'] = true;
    lqr_put_user($state, $user);
    return lqr_action_claim_reward_reported($state, $config, $user, $questId, $quests);
}
