<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-crm.php';
require_once __DIR__ . '/mail.php';
require_once dirname(__DIR__) . '/api/rewards/_zero_value_bridge.php';
require_once dirname(__DIR__) . '/api/communications/_delivery.php';

function mg_crm_reward_invite_uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 15) | 64);
    $b[8] = chr((ord($b[8]) & 63) | 128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function mg_crm_reward_invites_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'crm_reward_invites'");
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_crm_reward_invite_expiry(array $template): ?string
{
    $rule = (string)($template['expiration_rule'] ?? 'none');
    if (($rule === 'fixed_date' || $rule === 'event_date') && !empty($template['expires_at'])) return (string)$template['expires_at'];
    if ($rule === 'after_issue' && !empty($template['expiration_days'])) return date('Y-m-d H:i:s', time() + ((int)$template['expiration_days'] * 86400));
    return null;
}

function mg_crm_reward_invite_record_campaign_event(PDO $pdo, array $row, ?int $walletId, string $type, array $context = []): void
{
    $stmt = $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_crm_reward_invite_uuid(), (int)$row['merchant_user_id'], (int)$row['campaign_id'], $walletId, (int)$row['contact_id'], $type, json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function mg_crm_reward_invite_email_payload(array $contact, array $template, string $inviteId, string $inviteUrl, string $note, ?string $expiresAt): array
{
    $name = trim((string)($contact['name'] ?? '')) ?: 'there';
    $title = (string)$template['title'];
    $body = '<p style="margin:0 0 14px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ', a merchant reserved a Microgifter reward for you.</p>'
        . '<p style="margin:0 0 14px;color:#334155;font-size:16px;line-height:1.6;"><strong>' . mg_mail_escape($title) . '</strong></p>'
        . ($note !== '' ? '<p style="margin:0 0 14px;color:#334155;font-size:15px;line-height:1.6;">' . nl2br(mg_mail_escape($note)) . '</p>' : '')
        . mg_email_button($inviteUrl, 'Create account to claim')
        . '<p style="margin:14px 0 0;color:#64748b;font-size:13px;line-height:1.6;">Use this same email address and the reward will be delivered to your Microgifter inbox automatically.</p>';
    return [
        'subject' => 'A Microgifter reward is waiting for you',
        'html' => mg_email_layout('Reward waiting', $body, 'Create a Microgifter account to claim your reward.'),
        'text' => "Hi {$name},\n\nA merchant reserved this reward for you: {$title}\n\n{$note}\n\nCreate account to claim: {$inviteUrl}",
        'email' => (string)$contact['email'],
        'contact_public_id' => (string)$contact['public_id'],
        'campaign_public_id' => (string)($contact['campaign_public_id'] ?? ''),
        'reward_template_id' => (string)$template['public_id'],
        'invite_id' => $inviteId,
        'message_type' => 'merchant_crm_reward_invite',
        'expires_at' => $expiresAt,
    ];
}

function mg_crm_reward_invites_link_for_user(PDO $pdo, int $userId, string $email): array
{
    $email = strtolower(trim($email));
    if ($userId < 1 || $email === '' || !mg_crm_reward_invites_ready($pdo)) return ['linked' => 0, 'wallet_items' => []];
    $linked = 0;
    $wallets = [];
    try {
        mg_delivery_install_schema($pdo);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT i.*,cc.public_id contact_public_id,cc.name contact_name,cc.campaign_id,cc.merchant_user_id,c.public_id campaign_public_id,c.campaign_type,rt.title,rt.description,rt.public_id template_public_id,rt.currency,rt.value_amount_cents,rt.redemption_instructions,rt.expires_at template_expires_at,rt.expiration_rule,rt.expiration_days,rt.status template_status FROM crm_reward_invites i INNER JOIN campaign_contacts cc ON cc.id=i.contact_id INNER JOIN campaigns c ON c.id=i.campaign_id INNER JOIN reward_templates rt ON rt.id=i.reward_template_id WHERE i.email=? AND i.status='sent' AND (i.expires_at IS NULL OR i.expires_at>NOW()) ORDER BY i.created_at ASC LIMIT 25 FOR UPDATE");
        $stmt->execute([$email]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if ((string)$row['template_status'] !== 'active') continue;
            $dup = $pdo->prepare("SELECT public_id FROM wallet_items WHERE merchant_user_id=? AND user_id=? AND reward_template_id=? AND source_type='manual_send' AND status IN ('issued','viewed','claimed','redeemed') AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1");
            $dup->execute([(int)$row['merchant_user_id'], $userId, (int)$row['reward_template_id']]);
            if ($dup->fetchColumn()) {
                $pdo->prepare("UPDATE crm_reward_invites SET status='linked',user_id=?,linked_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$userId, (int)$row['id']]);
                continue;
            }
            $expiresAt = mg_crm_reward_invite_expiry($row);
            $walletPublicId = mg_crm_reward_invite_uuid();
            $metadata = ['campaign_type' => 'merchant_crm_reward_invite', 'invite_id' => (string)$row['public_id'], 'crm_contact_id' => (string)$row['contact_public_id'], 'reward_template_id' => (string)$row['template_public_id'], 'note' => (string)($row['note'] ?? '')];
            $ins = $pdo->prepare('INSERT INTO wallet_items (public_id,user_id,contact_id,merchant_user_id,reward_template_id,campaign_id,source_type,source_id,status,value_cents_snapshot,currency_snapshot,title_snapshot,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())');
            $ins->execute([$walletPublicId, $userId, (int)$row['contact_id'], (int)$row['merchant_user_id'], (int)$row['reward_template_id'], (int)$row['campaign_id'], 'manual_send', (string)$row['public_id'], 'issued', (int)$row['value_amount_cents'], (string)$row['currency'], (string)$row['title'], json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expiresAt]);
            $walletDbId = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE crm_reward_invites SET status=\'delivered\',user_id=?,wallet_item_id=?,linked_at=NOW(),delivered_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$userId, $walletDbId, (int)$row['id']]);
            $pdo->prepare('UPDATE campaign_contacts SET user_id=?,updated_at=NOW() WHERE id=? AND user_id IS NULL')->execute([$userId, (int)$row['contact_id']]);
            $pdo->prepare('UPDATE reward_templates SET issued_count=issued_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$row['reward_template_id']]);
            $bridge = mg_zero_reward_issue_from_wallet($pdo, ['merchant_user_id' => (int)$row['merchant_user_id'], 'recipient_user_id' => $userId, 'recipient_external_id' => (string)$row['contact_public_id'], 'wallet_item_db_id' => $walletDbId, 'wallet_item_public_id' => $walletPublicId, 'campaign_public_id' => (string)$row['campaign_public_id'], 'reward_template_public_id' => (string)$row['template_public_id'], 'source_type' => 'merchant_crm_reward_invite', 'source_reference' => $walletPublicId, 'source_line_reference' => (string)$row['public_id'], 'title' => (string)$row['title'], 'description' => $row['description'] ?? null, 'currency' => (string)$row['currency'], 'display_value_cents' => (int)$row['value_amount_cents'], 'expires_at' => $expiresAt, 'redemption_instructions' => $row['redemption_instructions'] ?? null, 'terms' => ['invite_id' => (string)$row['public_id'], 'campaign_type' => 'merchant_crm_reward_invite']]);
            mg_crm_reward_invite_record_campaign_event($pdo, $row, $walletDbId, 'crm.reward_invite.delivered', ['wallet_item_id' => $walletPublicId, 'invite_id' => (string)$row['public_id'], 'pppm_bridge' => $bridge]);
            mg_merchant_crm_record_event($pdo, ['merchant_user_id' => (int)$row['merchant_user_id'], 'campaign_id' => (int)$row['campaign_id'], 'campaign_type' => 'merchant_crm_reward_invite', 'event_type' => 'crm.reward_invite.delivered', 'source_type' => 'merchant_crm_reward_invite', 'source_public_id' => (string)$row['contact_public_id'], 'user_id' => $userId, 'email' => $email, 'name' => (string)($row['contact_name'] ?? ''), 'value_cents' => (int)$row['value_amount_cents'], 'metadata' => ['wallet_item_id' => $walletPublicId, 'invite_id' => (string)$row['public_id'], 'reward_template_id' => (string)$row['template_public_id']]]);
            $wallets[] = $walletPublicId;
            $linked++;
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (function_exists('mg_security_log')) mg_security_log('warning', 'crm_reward_invites.link_failed', 'CRM reward invite link failed.', ['exception_class' => $error::class], $userId);
    }
    return ['linked' => $linked, 'wallet_items' => $wallets];
}
