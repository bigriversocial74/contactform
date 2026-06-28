<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_campaign_contact_no_recent_activity(mixed $value): bool
{
    $timestamp = strtotime((string)$value);
    return $timestamp > 0 && $timestamp < strtotime('-30 days');
}

function mg_campaign_contact_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_campaign_contact_row(array $row): array
{
    $lastActivityAt = $row['last_activity_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? null;
    $resolvedUserId = (int)($row['resolved_user_id'] ?? $row['user_id'] ?? 0);
    $rules = mg_campaign_contact_rules($row['campaign_rules_json'] ?? null);
    $ruleMode = (string)($rules['mode'] ?? '');
    $winnerActionAllowed = (string)($row['campaign_type'] ?? '') === 'contest_giveaway'
        && in_array($ruleMode, ['manual_winner', 'random_draw'], true)
        && (int)($row['winner_count'] ?? 0) <= 0;

    return [
        'id' => (string)$row['public_id'],
        'campaign_id' => (string)($row['campaign_public_id'] ?? ''),
        'campaign_title' => (string)($row['campaign_title'] ?? ''),
        'campaign_type' => (string)($row['campaign_type'] ?? ''),
        'campaign_rules' => $rules,
        'winner_action_allowed' => $winnerActionAllowed,
        'email' => (string)$row['email'],
        'phone' => (string)($row['phone'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'source' => (string)$row['source'],
        'opt_in_status' => (string)$row['opt_in_status'],
        'has_account' => $resolvedUserId > 0,
        'account_linked' => (int)($row['user_id'] ?? 0) > 0,
        'account_resolved_by_email' => $resolvedUserId > 0 && (int)($row['user_id'] ?? 0) <= 0,
        'email_verified' => !empty($row['email_verified_at']),
        'wallet_count' => (int)($row['wallet_count'] ?? 0),
        'issued_count' => (int)($row['issued_count'] ?? 0),
        'claimed_count' => (int)($row['claimed_count'] ?? 0),
        'redeemed_count' => (int)($row['redeemed_count'] ?? 0),
        'winner_count' => (int)($row['winner_count'] ?? 0),
        'invite_pending_count' => (int)($row['invite_pending_count'] ?? 0),
        'emails_queued_count' => (int)($row['emails_queued_count'] ?? 0),
        'emails_delivered_count' => (int)($row['emails_delivered_count'] ?? 0),
        'emails_failed_count' => (int)($row['emails_failed_count'] ?? 0),
        'last_activity_at' => $lastActivityAt,
        'no_recent_activity' => mg_campaign_contact_no_recent_activity($lastActivityAt),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$campaignPublicId = strtolower(trim((string)($_GET['campaign_id'] ?? $_GET['campaign'] ?? '')));

try {
    $sql = "SELECT cc.*,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,c.rules_json campaign_rules_json,
                   COALESCE(cc.user_id,email_user.id) resolved_user_id,
                   COALESCE(linked_user.email_verified_at,email_user.email_verified_at) email_verified_at,
                   cc.updated_at last_activity_at,
                   COUNT(DISTINCT wi.id) wallet_count,
                   COUNT(DISTINCT CASE WHEN wi.status='issued' THEN wi.id END) issued_count,
                   COUNT(DISTINCT CASE WHEN wi.status='claimed' THEN wi.id END) claimed_count,
                   COUNT(DISTINCT CASE WHEN wi.status='redeemed' THEN wi.id END) redeemed_count,
                   COUNT(DISTINCT CASE WHEN wi.source_type='contest_winner' AND wi.status<>'cancelled' THEN wi.id END) winner_count,
                   COUNT(DISTINCT cri.id) invite_pending_count,
                   COUNT(DISTINCT CASE WHEN ce.event_type='outbound_email.queued' THEN ce.id END) emails_queued_count,
                   COUNT(DISTINCT CASE WHEN mdj.status='delivered' THEN mdj.id END) emails_delivered_count,
                   COUNT(DISTINCT CASE WHEN mdj.status IN ('failed','dead_letter') THEN mdj.id END) emails_failed_count
            FROM campaign_contacts cc
            INNER JOIN campaigns c ON c.id=cc.campaign_id
            LEFT JOIN users linked_user ON linked_user.id=cc.user_id
            LEFT JOIN users email_user ON cc.user_id IS NULL AND LOWER(email_user.email)=LOWER(cc.email)
            LEFT JOIN wallet_items wi ON wi.contact_id=cc.id
            LEFT JOIN crm_reward_invites cri ON cri.contact_id=cc.id AND cri.status='sent' AND (cri.expires_at IS NULL OR cri.expires_at>NOW())
            LEFT JOIN campaign_events ce ON ce.contact_id=cc.id
            LEFT JOIN message_events me ON me.event_type='campaign.outbound_email' AND JSON_UNQUOTE(JSON_EXTRACT(me.payload_json,'$.contact_public_id'))=cc.public_id
            LEFT JOIN message_delivery_jobs mdj ON mdj.message_event_id=me.id AND mdj.channel='email'
            WHERE cc.merchant_user_id=?";
    $params = [$merchantId];

    if ($campaignPublicId !== '') {
        $sql .= ' AND (c.public_id=? OR c.public_slug=?)';
        $params[] = $campaignPublicId;
        $params[] = $campaignPublicId;
    }

    $sql .= ' GROUP BY cc.id,c.public_id,c.title,c.campaign_type,c.rules_json,linked_user.email_verified_at,email_user.id,email_user.email_verified_at ORDER BY cc.updated_at DESC,cc.id DESC LIMIT 250';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contacts = array_map('mg_campaign_contact_row', $stmt->fetchAll(PDO::FETCH_ASSOC));

    $totals = [
        'contacts' => count($contacts),
        'accounts' => count(array_filter($contacts, fn($c) => $c['has_account'])),
        'no_accounts' => count(array_filter($contacts, fn($c) => !$c['has_account'])),
        'verified' => count(array_filter($contacts, fn($c) => $c['email_verified'])),
        'wallets' => array_sum(array_column($contacts, 'wallet_count')),
        'reward_issued' => count(array_filter($contacts, fn($c) => (int)$c['issued_count'] > 0)),
        'reward_claimed' => count(array_filter($contacts, fn($c) => (int)$c['claimed_count'] > 0 || (int)$c['redeemed_count'] > 0)),
        'winner_rewards' => array_sum(array_column($contacts, 'winner_count')),
        'invite_pending' => count(array_filter($contacts, fn($c) => (int)$c['invite_pending_count'] > 0)),
        'no_recent_activity' => count(array_filter($contacts, fn($c) => $c['no_recent_activity'])),
        'emails_queued' => array_sum(array_column($contacts, 'emails_queued_count')),
        'emails_delivered' => array_sum(array_column($contacts, 'emails_delivered_count')),
        'emails_failed' => array_sum(array_column($contacts, 'emails_failed_count')),
    ];

    mg_ok(['contacts' => $contacts, 'totals' => $totals, 'count' => count($contacts), 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_contacts.unavailable', 'Campaign contacts unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_ok(['contacts' => [], 'totals' => ['contacts' => 0, 'accounts' => 0, 'no_accounts' => 0, 'verified' => 0, 'wallets' => 0, 'reward_issued' => 0, 'reward_claimed' => 0, 'winner_rewards' => 0, 'invite_pending' => 0, 'no_recent_activity' => 0, 'emails_queued' => 0, 'emails_delivered' => 0, 'emails_failed' => 0], 'count' => 0, 'schema_ready' => false], 'Campaign contacts unavailable until schemas are installed.');
}
