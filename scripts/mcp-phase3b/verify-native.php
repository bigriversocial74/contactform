<?php
declare(strict_types=1);

function phase3b_verify_native_drafts(PDO $pdo, int $userId, array $conversions): void
{
    $giftStmt = $pdo->prepare('SELECT id,status,visibility,value_cents,metadata_json FROM gifts WHERE public_id=? LIMIT 1');
    $giftStmt->execute([(string)$conversions['gift']['native_public_id']]);
    $gift = $giftStmt->fetch(PDO::FETCH_ASSOC);
    phase3b_assert((string)$gift['status'] === 'draft' && (string)$gift['visibility'] === 'private', 'Gift conversion did not remain a private draft.');
    phase3b_assert((int)$gift['value_cents'] === 5000, 'Gift draft quantity/value snapshot is incorrect.');
    $giftMetadata = mg_mcp_draft_json($gift['metadata_json'] ?? null);
    phase3b_assert(($giftMetadata['payment_created'] ?? true) === false && ($giftMetadata['gift_issued'] ?? true) === false, 'Gift conversion crossed its inactive boundary.');
    $giftEvents = $pdo->prepare('SELECT event_type FROM gift_events WHERE gift_id=? ORDER BY id');
    $giftEvents->execute([(int)$gift['id']]);
    phase3b_assert($giftEvents->fetchAll(PDO::FETCH_COLUMN) === ['created'], 'Gift conversion emitted a later lifecycle event.');

    $campaignStmt = $pdo->prepare("SELECT event_context_json FROM campaign_events WHERE merchant_user_id=? AND event_type='crm.campaign_builder.draft' AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.draft_id'))=? LIMIT 1");
    $campaignStmt->execute([$userId, (string)$conversions['campaign']['native_public_id']]);
    $campaignContext = mg_mcp_draft_json($campaignStmt->fetchColumn() ?: null);
    phase3b_assert((string)($campaignContext['status'] ?? '') === 'draft' && ($campaignContext['execution_enabled'] ?? true) === false, 'Campaign conversion did not remain inactive.');
    $launchCount = $pdo->prepare("SELECT COUNT(*) FROM campaign_events WHERE merchant_user_id=? AND event_type='crm.campaign_builder.launched'");
    $launchCount->execute([$userId]);
    phase3b_assert((int)$launchCount->fetchColumn() === 0, 'Campaign conversion recorded a launch.');

    $rewardStmt = $pdo->prepare('SELECT status,agent_discoverable,agent_add_to_wallet_allowed,agent_gift_send_allowed,metadata_json FROM reward_templates WHERE public_id=? LIMIT 1');
    $rewardStmt->execute([(string)$conversions['reward']['native_public_id']]);
    $reward = $rewardStmt->fetch(PDO::FETCH_ASSOC);
    phase3b_assert((string)$reward['status'] === 'draft', 'Reward conversion did not remain a draft.');
    phase3b_assert((int)$reward['agent_discoverable'] === 0 && (int)$reward['agent_add_to_wallet_allowed'] === 0 && (int)$reward['agent_gift_send_allowed'] === 0, 'Reward conversion enabled distribution.');
    $rewardMetadata = mg_mcp_draft_json($reward['metadata_json'] ?? null);
    phase3b_assert(($rewardMetadata['activated'] ?? true) === false && ($rewardMetadata['fulfilled'] ?? true) === false, 'Reward conversion crossed its inactive boundary.');

    $messageStmt = $pdo->prepare("SELECT event_context_json FROM campaign_events WHERE merchant_user_id=? AND event_type='crm.agent.message.draft.created' AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.message_draft_id'))=? LIMIT 1");
    $messageStmt->execute([$userId, (string)$conversions['message']['native_public_id']]);
    $messageContext = mg_mcp_draft_json($messageStmt->fetchColumn() ?: null);
    phase3b_assert(($messageContext['execution_enabled'] ?? true) === false, 'Message conversion enabled execution.');
    $sentCount = $pdo->prepare("SELECT COUNT(*) FROM campaign_events WHERE merchant_user_id=? AND event_type='crm.agent.message.sent'");
    $sentCount->execute([$userId]);
    phase3b_assert((int)$sentCount->fetchColumn() === 0, 'Message conversion recorded a sent event.');
}
