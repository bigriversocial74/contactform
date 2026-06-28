<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_activity_public_path(string $type): string
{
    return match ($type) {
        'newsletter_signup' => '/newsletter-signup.php',
        'contest_giveaway' => '/contest.php',
        'qr_reward_drop' => '/qr-reward.php',
        'referral_reward' => '/referral-reward.php',
        'birthday_vip' => '/birthday-vip.php',
        'agent_offer' => '/agent-offer.php',
        default => '/campaign.php',
    };
}

function mg_activity_rules(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_activity_public_url(array $row): string
{
    $type = (string)$row['campaign_type'];
    $path = mg_activity_public_path($type);
    if ($type === 'qr_reward_drop' && !empty($row['qr_code_token'])) return $path . '?token=' . rawurlencode((string)$row['qr_code_token']);
    $ref = trim((string)($row['public_slug'] ?? '')) !== '' ? (string)$row['public_slug'] : (string)$row['public_id'];
    return $path . '?campaign=' . rawurlencode($ref);
}

function mg_activity_row(array $row): array
{
    return [
        'id' => (string)$row['public_id'],
        'title' => (string)$row['title'],
        'campaign_type' => (string)$row['campaign_type'],
        'status' => (string)$row['status'],
        'public_slug' => $row['public_slug'] ?? null,
        'qr_code_token' => $row['qr_code_token'] ?? null,
        'public_url' => mg_activity_public_url($row),
        'reward_template_title' => $row['reward_template_title'] ?? null,
        'rules' => mg_activity_rules($row['rules_json'] ?? null),
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
        'issued_count' => (int)($row['issued_count'] ?? 0),
        'contacts_count' => (int)($row['contacts_count'] ?? 0),
        'wallet_issued_count' => (int)($row['wallet_issued_count'] ?? 0),
        'wallet_claimed_count' => (int)($row['wallet_claimed_count'] ?? 0),
        'wallet_redeemed_count' => (int)($row['wallet_redeemed_count'] ?? 0),
        'emails_queued_count' => (int)($row['emails_queued_count'] ?? 0),
        'emails_delivered_count' => (int)($row['emails_delivered_count'] ?? 0),
        'emails_failed_count' => (int)($row['emails_failed_count'] ?? 0),
        'events_count' => (int)($row['events_count'] ?? 0),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

try {
    $stmt = $pdo->prepare('SELECT c.public_id,c.public_slug,c.qr_code_token,c.title,c.campaign_type,c.status,c.updated_at,c.rules_json,c.quantity_limit,c.issued_count,rt.title reward_template_title,
        COUNT(DISTINCT cc.id) contacts_count,
        COUNT(DISTINCT wi.id) wallet_issued_count,
        COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) wallet_claimed_count,
        COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) wallet_redeemed_count,
        COUNT(DISTINCT CASE WHEN ce.event_type = \'outbound_email.queued\' THEN ce.id END) emails_queued_count,
        COUNT(DISTINCT CASE WHEN mdj.status = \'delivered\' THEN mdj.id END) emails_delivered_count,
        COUNT(DISTINCT CASE WHEN mdj.status IN (\'failed\',\'dead_letter\') THEN mdj.id END) emails_failed_count,
        COUNT(DISTINCT ce.id) events_count
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        LEFT JOIN campaign_contacts cc ON cc.campaign_id = c.id
        LEFT JOIN wallet_items wi ON wi.campaign_id = c.id
        LEFT JOIN campaign_events ce ON ce.campaign_id = c.id
        LEFT JOIN message_events me ON me.event_type = \'campaign.outbound_email\' AND JSON_UNQUOTE(JSON_EXTRACT(me.payload_json, \'$.campaign_public_id\')) = c.public_id
        LEFT JOIN message_delivery_jobs mdj ON mdj.message_event_id = me.id AND mdj.channel = \'email\'
        WHERE c.merchant_user_id = ?
        GROUP BY c.id, c.public_id, c.public_slug, c.qr_code_token, c.title, c.campaign_type, c.status, c.updated_at, c.rules_json, c.quantity_limit, c.issued_count, rt.title
        ORDER BY c.updated_at DESC, c.id DESC
        LIMIT 100');
    $stmt->execute([$merchantId]);
    $campaigns = array_map('mg_activity_row', $stmt->fetchAll());
    $totals = [
        'campaigns' => count($campaigns),
        'contacts' => array_sum(array_column($campaigns, 'contacts_count')),
        'wallet_issued' => array_sum(array_column($campaigns, 'wallet_issued_count')),
        'wallet_claimed' => array_sum(array_column($campaigns, 'wallet_claimed_count')),
        'wallet_redeemed' => array_sum(array_column($campaigns, 'wallet_redeemed_count')),
        'emails_queued' => array_sum(array_column($campaigns, 'emails_queued_count')),
        'emails_delivered' => array_sum(array_column($campaigns, 'emails_delivered_count')),
        'emails_failed' => array_sum(array_column($campaigns, 'emails_failed_count')),
        'events' => array_sum(array_column($campaigns, 'events_count')),
    ];
    mg_ok(['campaigns' => $campaigns, 'totals' => $totals, 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_activity.schema_unavailable', 'Campaign activity schema is unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_ok(['campaigns' => [], 'totals' => ['campaigns'=>0,'contacts'=>0,'wallet_issued'=>0,'wallet_claimed'=>0,'wallet_redeemed'=>0,'emails_queued'=>0,'emails_delivered'=>0,'emails_failed'=>0,'events'=>0], 'schema_ready' => false], 'Campaign activity unavailable until the Stage 12 schema is installed.');
}
