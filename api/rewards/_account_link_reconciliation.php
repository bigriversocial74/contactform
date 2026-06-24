<?php
declare(strict_types=1);

require_once __DIR__ . '/_zero_value_bridge.php';

function mg_reward_reconcile_account_rewards(PDO $pdo, int $userId, string $email): array
{
    $email = strtolower(trim($email));
    if ($userId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['linked_contacts'=>0,'linked_wallet_items'=>0,'projected_rewards'=>0];

    $pdo->prepare('UPDATE campaign_contacts SET user_id=?, updated_at=NOW() WHERE email=? AND (user_id IS NULL OR user_id=0)')
        ->execute([$userId, $email]);
    $linkedContacts = (int) $pdo->prepare('SELECT ROW_COUNT()')->execute() ?: 0;

    $wallets = $pdo->prepare("SELECT wi.*,cc.public_id contact_public_id,cc.email contact_email,cc.name contact_name,c.public_id campaign_public_id,c.campaign_type,c.description campaign_description,rt.public_id reward_template_public_id,rt.title reward_title,rt.description reward_description,rt.redemption_instructions,rt.currency,rt.value_amount_cents,rt.expiration_rule,rt.expiration_days,rt.expires_at reward_expires_at FROM wallet_items wi INNER JOIN campaign_contacts cc ON cc.id=wi.contact_id LEFT JOIN campaigns c ON c.id=wi.campaign_id LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id WHERE cc.email=? AND (wi.user_id IS NULL OR wi.user_id=0) AND wi.status<>'cancelled' ORDER BY wi.id ASC LIMIT 50");
    $wallets->execute([$email]);
    $rows = $wallets->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $linkedWalletItems = 0;
    $projectedRewards = 0;
    foreach ($rows as $row) {
        $pdo->prepare('UPDATE wallet_items SET user_id=?, updated_at=NOW() WHERE id=? AND (user_id IS NULL OR user_id=0)')->execute([$userId, (int) $row['id']]);
        $linkedWalletItems++;
        $expiresAt = $row['expires_at'] ?? null;
        if (!$expiresAt) {
            $rule = (string) ($row['expiration_rule'] ?? 'none');
            if (($rule === 'fixed_date' || $rule === 'event_date') && !empty($row['reward_expires_at'])) $expiresAt = (string) $row['reward_expires_at'];
            if ($rule === 'after_issue' && !empty($row['expiration_days'])) $expiresAt = date('Y-m-d H:i:s', time() + ((int) $row['expiration_days'] * 86400));
        }
        $bridge = mg_zero_reward_issue_from_wallet($pdo, [
            'merchant_user_id'=>(int) $row['merchant_user_id'],
            'recipient_user_id'=>$userId,
            'recipient_external_id'=>(string) ($row['contact_public_id'] ?? ''),
            'recipient_name'=>$row['contact_name'] ?? null,
            'wallet_item_db_id'=>(int) $row['id'],
            'wallet_item_public_id'=>(string) $row['public_id'],
            'campaign_public_id'=>(string) ($row['campaign_public_id'] ?? ''),
            'reward_template_public_id'=>(string) ($row['reward_template_public_id'] ?? ''),
            'source_type'=>(string) ($row['source_type'] ?? 'campaign_reward'),
            'source_reference'=>(string) $row['public_id'],
            'source_line_reference'=>(string) ($row['contact_public_id'] ?? $row['public_id']),
            'title'=>(string) ($row['reward_title'] ?? $row['title_snapshot'] ?? 'Microgifter reward'),
            'description'=>$row['reward_description'] ?? $row['campaign_description'] ?? null,
            'currency'=>(string) ($row['currency_snapshot'] ?? $row['currency'] ?? 'USD'),
            'display_value_cents'=>(int) ($row['value_cents_snapshot'] ?? $row['value_amount_cents'] ?? 0),
            'expires_at'=>$expiresAt,
            'redemption_instructions'=>$row['redemption_instructions'] ?? null,
            'terms'=>['campaign_type'=>(string) ($row['campaign_type'] ?? 'campaign_reward')],
        ]);
        if (empty($bridge['pending_account_link'])) $projectedRewards++;
    }

    return ['linked_contacts'=>count($rows) + $linkedContacts,'linked_wallet_items'=>$linkedWalletItems,'projected_rewards'=>$projectedRewards];
}
