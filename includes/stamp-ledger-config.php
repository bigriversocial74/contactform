<?php
declare(strict_types=1);

function mg_stamp_debit_actions(): array
{
    return [
        [
            'key' => 'direct_microgift_send',
            'label' => 'Direct Microgift send',
            'channel' => 'Direct',
            'stamp_value' => 1,
            'scope' => 'Microgift',
            'description' => 'Sending one paid Microgift directly to one recipient.',
            'enabled' => true,
        ],
        [
            'key' => 'direct_reward_send',
            'label' => 'Direct Reward send',
            'channel' => 'Direct',
            'stamp_value' => 1,
            'scope' => 'Reward',
            'description' => 'Sending one promotional Reward directly to one recipient.',
            'enabled' => true,
        ],
        [
            'key' => 'campaign_feed_send',
            'label' => 'Campaign feed send',
            'channel' => 'Feed',
            'stamp_value' => 1,
            'scope' => 'Campaign',
            'description' => 'Publishing one campaign distribution item into the Microgifter feed.',
            'enabled' => true,
        ],
        [
            'key' => 'email_list_send',
            'label' => 'Email list send',
            'channel' => 'Email',
            'stamp_value' => 1,
            'scope' => 'CRM',
            'description' => 'Sending one campaign, Microgift, or Reward message to one email recipient.',
            'enabled' => true,
        ],
        [
            'key' => 'sms_send',
            'label' => 'SMS send',
            'channel' => 'SMS',
            'stamp_value' => 3,
            'scope' => 'CRM',
            'description' => 'Sending one campaign, Microgift, or Reward message to one SMS recipient.',
            'enabled' => true,
        ],
        [
            'key' => 'qr_claim_prompt_send',
            'label' => 'QR claim prompt send',
            'channel' => 'QR',
            'stamp_value' => 1,
            'scope' => 'Claim',
            'description' => 'Sending a claim prompt or follow-up from a QR/table tent campaign.',
            'enabled' => true,
        ],
        [
            'key' => 'regift_send',
            'label' => 'Regift send',
            'channel' => 'Direct',
            'stamp_value' => 0,
            'scope' => 'Microgift',
            'description' => 'Regifting an already-purchased Microgift or promotional Reward to a new recipient. This send is free.',
            'enabled' => true,
        ],
        [
            'key' => 'agentic_discovery_send',
            'label' => 'Agentic discovery send',
            'channel' => 'Discovery',
            'stamp_value' => 2,
            'scope' => 'Automation',
            'description' => 'Automated discovery, recommendation, or agent-driven distribution send.',
            'enabled' => true,
        ],
    ];
}

function mg_stamp_ledger_preview(string $scope = 'merchant'): array
{
    $actor = $scope === 'admin' ? 'Admin review' : 'Merchant workspace';
    return [
        [
            'posted_at' => 'Monthly reset',
            'type' => 'credit',
            'label' => 'Included monthly Stamps',
            'reference' => 'PKG-PRICING-GROWTH',
            'delta' => 10000,
            'balance' => 10000,
            'actor' => 'System',
        ],
        [
            'posted_at' => 'Campaign send',
            'type' => 'debit',
            'label' => 'Email list send · 250 recipients',
            'reference' => 'email_list_send × 250',
            'delta' => -250,
            'balance' => 9750,
            'actor' => $actor,
        ],
        [
            'posted_at' => 'SMS send',
            'type' => 'debit',
            'label' => 'SMS send · 10 recipients',
            'reference' => 'sms_send × 10 × 3 Stamps',
            'delta' => -30,
            'balance' => 9720,
            'actor' => $actor,
        ],
        [
            'posted_at' => 'Bulk purchase',
            'type' => 'credit',
            'label' => 'Purchased Stamp bundle',
            'reference' => 'BULK-STAMPS-5000',
            'delta' => 5000,
            'balance' => 14720,
            'actor' => 'Merchant billing',
        ],
        [
            'posted_at' => 'Failed delivery void',
            'type' => 'credit',
            'label' => 'Voided failed SMS debit',
            'reference' => 'sms_send refund × 1 × 3 Stamps',
            'delta' => 3,
            'balance' => 14723,
            'actor' => 'System',
        ],
    ];
}

function mg_stamp_debit_action_summary(): array
{
    $actions = mg_stamp_debit_actions();
    $enabled = array_values(array_filter($actions, static fn(array $action): bool => !empty($action['enabled'])));
    $sms = array_values(array_filter($actions, static fn(array $action): bool => ($action['key'] ?? '') === 'sms_send'));
    return [
        'total_actions' => count($actions),
        'enabled_actions' => count($enabled),
        'sms_stamp_value' => (int)($sms[0]['stamp_value'] ?? 0),
    ];
}
