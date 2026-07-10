<?php
declare(strict_types=1);

function mg_lqr_issue_reward(PDO $pdo, array $campaign, array $contact, array $participation, array $user): array
{
    $existing = $pdo->prepare("SELECT public_id,status FROM wallet_items WHERE campaign_id=? AND user_id=? AND source_type='loyalty_quest' AND status<>'cancelled' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $existing->execute([(int)$campaign['id'], (int)$user['id']]);
    $wallet = $existing->fetch(PDO::FETCH_ASSOC);
    if ($wallet) return mg_lqp_issue_reward($pdo, $campaign, $contact, $participation, $user);

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
    return $reward;
}
