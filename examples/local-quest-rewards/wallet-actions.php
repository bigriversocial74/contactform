<?php
declare(strict_types=1);

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
    if ($rewardId === '') {
        throw new RuntimeException('Reward ID is missing.');
    }
    $externalClaimId = 'lqr_claim_' . substr(hash('sha256', $questId . '|' . (string)$user['external_user_id'] . '|' . $rewardId), 0, 24);
    $quest = is_array($quests[$questId] ?? null) ? $quests[$questId] : [];
    $geo = [
        'lat' => (string)($_POST['geo_lat'] ?? ''),
        'lng' => (string)($_POST['geo_lng'] ?? ''),
        'accuracy' => (string)($_POST['geo_accuracy'] ?? ''),
        'captured_at' => (string)($_POST['geo_captured_at'] ?? ''),
    ];
    $qrPayload = trim((string)($_POST['qr_payload'] ?? ''));
    $response = lqr_call_microgifter($config, 'POST', '/api/public/v1/rewards/claim.php', [
        'reward_id' => $rewardId,
        'item_id' => $itemId,
        'linked_account_id' => (string)$user['linked_account_id'],
        'external_user_id' => (string)$user['external_user_id'],
        'external_claim_id' => $externalClaimId,
        'claim_action' => 'claimed_in_app',
        'metadata' => [
            'app' => 'local-quest-rewards',
            'quest_id' => $questId,
            'quest_title' => (string)($quest['title'] ?? $questId),
            'qr_payload' => $qrPayload,
            'claim_geolocation' => $geo,
        ],
    ], [
        'X-Request-ID: req_' . $externalClaimId,
        'X-Idempotency-Key: ' . $externalClaimId,
    ]);
    if ((int)$response['status'] >= 400) {
        $user['rewards'][$questId]['claim_status'] = 'claim_report_failed';
        $user['rewards'][$questId]['claim_report_status'] = 'microgifter_claim_api_failed';
        $user['rewards'][$questId]['claim_report_response'] = $response;
        $user['rewards'][$questId]['claim_geo'] = $geo;
        $user['rewards'][$questId]['claim_qr_payload'] = $qrPayload;
        lqr_put_user($state, $user);
        lqr_add_event($state, 'quest.reward.claim_failed', 'Microgifter rejected the Quest reward claim report.', ['quest_id'=>$questId, 'reward_id'=>$rewardId, 'status'=>$response['status']]);
        throw new RuntimeException('Microgifter claim report failed.');
    }
    $body = is_array($response['body']) ? $response['body'] : [];
    $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
    $user['rewards'][$questId]['claim_status'] = 'claimed_in_quest_app';
    $user['rewards'][$questId]['claimed_at'] = gmdate('c');
    $user['rewards'][$questId]['claim_report_status'] = 'reported_to_microgifter';
    $user['rewards'][$questId]['claim_report_endpoint'] = '/api/public/v1/rewards/claim.php';
    $user['rewards'][$questId]['claim_report_response'] = $response;
    $user['rewards'][$questId]['claim_geo'] = $geo;
    $user['rewards'][$questId]['claim_qr_payload'] = $qrPayload;
    $user['rewards'][$questId]['microgifter_event_id'] = (string)($data['microgifter_event_id'] ?? '');
    if (!empty($data['item_status'])) {
        $user['rewards'][$questId]['item_status'] = (string)$data['item_status'];
    }
    lqr_put_user($state, $user);
    lqr_add_event($state, 'quest.reward.claimed', 'Reward claimed in Local Quest and reported to Microgifter.', ['quest_id'=>$questId, 'reward_id'=>$rewardId, 'item_id'=>$itemId, 'microgifter_event_id'=>(string)($data['microgifter_event_id'] ?? ''), 'qr_payload'=>$qrPayload, 'geo'=>$geo]);
    return 'Reward claimed in the Quest app and reported to Microgifter.';
}
