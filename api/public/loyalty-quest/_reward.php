<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/communications/_loyalty_quest_notifications.php';

function mg_lqr_issue_reward(PDO $pdo, array $campaign, array $contact, array $participation, array $user): array
{
    $existing = $pdo->prepare("SELECT public_id,status FROM wallet_items WHERE campaign_id=? AND user_id=? AND source_type='loyalty_quest' AND status<>'cancelled' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $existing->execute([(int)$campaign['id'], (int)$user['id']]);
    $wallet = $existing->fetch(PDO::FETCH_ASSOC);
    if ($wallet) {
        $reward = mg_lqp_issue_reward($pdo, $campaign, $contact, $participation, $user);
        $walletId = (string)($reward['wallet_item_id'] ?? $wallet['public_id']);
        mg_lqn_notify_participant($pdo, 'reward_delivered', $campaign, (int)$user['id'], [
            'participation_id'=>(string)$participation['public_id'],
            'wallet_item_id'=>$walletId,
            'pppm_item_id'=>(string)($reward['pppm_bridge']['pppm_item_id'] ?? ''),
            'source_public_id'=>$walletId,
            'reward_title'=>(string)($reward['reward_title'] ?? $campaign['reward_template_title'] ?? 'Microgifter reward'),
            'expires_at'=>$reward['expires_at'] ?? null,
        ]);
        return $reward;
    }

    $walletPublicId = mg_lqp_uuid();
    $stampLedger = mg_public_campaign_debit_reward_stamp(
        $pdo,
        $campaign,
        $walletPublicId,
        'loyalty_quest',
        [
            'participation_id'=>(string)$participation['public_id'],
            'participant_user_id'=>(int)$user['id'],
            'contact_id'=>(string)$contact['public_id'],
            'wallet_item_id'=>$walletPublicId,
        ]
    );
    $reward = mg_lqp_issue_reward($pdo, $campaign, $contact, $participation, $user, $walletPublicId);
    $reward['stamp_ledger'] = $stampLedger;
    mg_lqp_event($pdo, $campaign, null, (int)$contact['id'], 'quest.reward_stamp_debited', [
        'participation_id'=>(string)$participation['public_id'],
        'wallet_item_id'=>$walletPublicId,
        'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id'] ?? null,
    ]);
    mg_lqn_notify_participant($pdo, 'reward_delivered', $campaign, (int)$user['id'], [
        'participation_id'=>(string)$participation['public_id'],
        'wallet_item_id'=>$walletPublicId,
        'pppm_item_id'=>(string)($reward['pppm_bridge']['pppm_item_id'] ?? ''),
        'source_public_id'=>$walletPublicId,
        'reward_title'=>(string)($reward['reward_title'] ?? $campaign['reward_template_title'] ?? 'Microgifter reward'),
        'expires_at'=>$reward['expires_at'] ?? null,
    ]);
    return $reward;
}
