<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/merchant-crm-search.php';

function mg_merchant_agent_crm_mentions(mixed $value): array
{
    $message = trim((string)$value);
    if ($message === '' || preg_match_all('/(?:^|\s)@([a-z0-9][a-z0-9._-]{0,79})\b/i', $message, $matches) !== 1 && empty($matches[1])) {
        return [];
    }
    $handles = [];
    foreach (($matches[1] ?? []) as $handle) {
        $handle = strtolower(trim((string)$handle));
        if ($handle !== '' && !in_array($handle, $handles, true)) $handles[] = $handle;
        if (count($handles) >= 8) break;
    }
    return $handles;
}

function mg_merchant_agent_crm_has_mentions(mixed $value): bool
{
    return mg_merchant_agent_crm_mentions($value) !== [];
}

function mg_merchant_agent_crm_exact_contact(PDO $pdo, int $merchantId, string $handle): ?array
{
    $result = mg_merchant_crm_search($pdo, $merchantId, $handle, 100, 0);
    foreach (($result['contacts'] ?? []) as $contact) {
        if (strtolower((string)($contact['username'] ?? '')) === strtolower($handle)) return $contact;
    }
    return null;
}

function mg_merchant_agent_crm_contact_details(PDO $pdo, int $merchantId, array $contact, int $days): array
{
    $publicId = strtolower(trim((string)($contact['id'] ?? '')));
    if ($publicId === '' || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) return [];

    $stmt = $pdo->prepare('SELECT id,public_id,user_id,lifecycle_stage,crm_status,last_campaign_type,last_source_type,first_seen_at,last_seen_at,last_engaged_at,last_purchased_at,last_reward_issued_at,last_reward_claimed_at,last_reward_redeemed_at,total_purchase_cents,total_rewards_issued,total_rewards_claimed,total_rewards_redeemed,tags_json FROM merchant_crm_contacts WHERE merchant_user_id=? AND public_id=? LIMIT 1');
    $stmt->execute([$merchantId, $publicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return [];

    $contactId = (int)$row['id'];
    $events = [];
    if (mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contact_events')) {
        $eventStmt = $pdo->prepare('SELECT event_type,campaign_type,source_type,value_cents,created_at FROM merchant_crm_contact_events WHERE merchant_user_id=? AND crm_contact_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY) ORDER BY id DESC LIMIT 12');
        $eventStmt->execute([$merchantId, $contactId, max(7, min(365, $days))]);
        $events = array_map(static fn(array $event): array => [
            'event_type'=>(string)$event['event_type'],
            'campaign_type'=>(string)$event['campaign_type'],
            'source_type'=>(string)$event['source_type'],
            'value_cents'=>$event['value_cents'] === null ? null : (int)$event['value_cents'],
            'created_at'=>$event['created_at'] ?? null,
        ], $eventStmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $campaigns = [];
    if (mg_merchant_crm_search_table_exists($pdo, 'merchant_crm_contact_campaigns') && mg_merchant_crm_search_table_exists($pdo, 'campaigns')) {
        $campaignStmt = $pdo->prepare('SELECT c.public_id,c.title,mcc.campaign_type,mcc.event_count,mcc.first_event_at,mcc.last_event_at FROM merchant_crm_contact_campaigns mcc INNER JOIN campaigns c ON c.id=mcc.campaign_id WHERE mcc.merchant_user_id=? AND mcc.crm_contact_id=? ORDER BY mcc.last_event_at DESC,mcc.id DESC LIMIT 8');
        $campaignStmt->execute([$merchantId, $contactId]);
        $campaigns = array_map(static fn(array $campaign): array => [
            'id'=>(string)$campaign['public_id'],
            'title'=>(string)$campaign['title'],
            'campaign_type'=>(string)$campaign['campaign_type'],
            'event_count'=>(int)$campaign['event_count'],
            'first_event_at'=>$campaign['first_event_at'] ?? null,
            'last_event_at'=>$campaign['last_event_at'] ?? null,
        ], $campaignStmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $tags = json_decode((string)($row['tags_json'] ?? '[]'), true);
    if (!is_array($tags)) $tags = [];

    return [
        'id'=>$publicId,
        'mention'=>(string)($contact['mention'] ?? ''),
        'name'=>(string)($contact['name'] ?? 'CRM contact'),
        'lifecycle_stage'=>(string)$row['lifecycle_stage'],
        'crm_status'=>(string)$row['crm_status'],
        'campaign'=>(string)($contact['campaign_title'] ?: $row['last_campaign_type']),
        'source'=>(string)$row['last_source_type'],
        'first_seen_at'=>$row['first_seen_at'] ?? null,
        'last_seen_at'=>$row['last_seen_at'] ?? null,
        'last_engaged_at'=>$row['last_engaged_at'] ?? null,
        'last_purchased_at'=>$row['last_purchased_at'] ?? null,
        'last_reward_issued_at'=>$row['last_reward_issued_at'] ?? null,
        'last_reward_claimed_at'=>$row['last_reward_claimed_at'] ?? null,
        'last_reward_redeemed_at'=>$row['last_reward_redeemed_at'] ?? null,
        'total_purchase_cents'=>(int)$row['total_purchase_cents'],
        'total_rewards_issued'=>(int)$row['total_rewards_issued'],
        'total_rewards_claimed'=>(int)$row['total_rewards_claimed'],
        'total_rewards_redeemed'=>(int)$row['total_rewards_redeemed'],
        'engagement_score'=>(int)($contact['score'] ?? 0),
        'engagement_label'=>(string)($contact['score_label'] ?? ''),
        'next_best_action'=>(string)($contact['next_best_action'] ?? ''),
        'has_account'=>!empty($contact['has_account']),
        'email_verified'=>!empty($contact['email_verified']),
        'tags'=>array_values(array_slice(array_filter(array_map('strval', $tags)), 0, 12)),
        'recent_events'=>$events,
        'campaign_history'=>$campaigns,
        'profile_url'=>(string)($contact['profile_url'] ?? ''),
        'crm_url'=>(string)($contact['crm_url'] ?? ''),
    ];
}

function mg_merchant_agent_crm_contact_context(PDO $pdo, int $merchantId, string $message, int $days = 90): array
{
    $handles = mg_merchant_agent_crm_mentions($message);
    $contacts = [];
    $unresolved = [];
    foreach ($handles as $handle) {
        $contact = mg_merchant_agent_crm_exact_contact($pdo, $merchantId, $handle);
        if (!$contact) {
            $unresolved[] = '@' . $handle;
            continue;
        }
        $details = mg_merchant_agent_crm_contact_details($pdo, $merchantId, $contact, $days);
        if ($details === []) $unresolved[] = '@' . $handle;
        else $contacts[] = $details;
    }
    return [
        'selected_contacts'=>$contacts,
        'unresolved_mentions'=>$unresolved,
        'selected_count'=>count($contacts),
        'mention_count'=>count($handles),
        'boundary'=>'Only exact CRM contacts explicitly mentioned in this merchant prompt are included. Data is scoped to the authorized merchant workspace.',
    ];
}
