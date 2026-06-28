<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_s12d_decode_json(?string $json): array
{
    if ($json === null || $json === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_s12d_public_path(string $type): string
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

function mg_s12d_campaign_tool_urls(array $campaign): array
{
    $base = rtrim((string) (defined('MG_APP_URL') ? MG_APP_URL : ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }
    $type = (string)($campaign['campaign_type'] ?? '');
    $path = mg_s12d_public_path($type);
    $slugOrId = $campaign['public_slug'] ?: $campaign['public_id'];
    return [
        'public_url' => $base . $path . '?campaign=' . rawurlencode((string) $slugOrId),
        'qr_url' => !empty($campaign['qr_code_token']) ? $base . '/qr-reward.php?token=' . rawurlencode((string) $campaign['qr_code_token']) : null,
        'qr_token' => $campaign['qr_code_token'] ?? null,
    ];
}

mg_require_method('GET');
$user = mg_require_permission('merchant.campaigns.view');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$campaignId = strtolower(trim((string) ($_GET['campaign_id'] ?? '')));

if ($campaignId === '' || strlen($campaignId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $campaignId)) {
    mg_fail('Invalid campaign.', 422);
}

try {
    $stmt = $pdo->prepare('SELECT c.*, rt.public_id reward_template_public_id, rt.title reward_template_title, rt.reward_type, rt.value_type, rt.value_amount_cents, rt.value_percent, rt.currency, rt.redemption_instructions
        FROM campaigns c
        LEFT JOIN reward_templates rt ON rt.id = c.reward_template_id
        WHERE c.public_id = ? AND c.merchant_user_id = ?
        LIMIT 1');
    $stmt->execute([$campaignId, $merchantId]);
    $campaign = $stmt->fetch();
    if (!$campaign) mg_fail('Campaign not found.', 404);

    $contactStmt = $pdo->prepare('SELECT cc.public_id,cc.email,cc.phone,cc.name,cc.source,cc.opt_in_status,cc.created_at,cc.updated_at,
        COUNT(DISTINCT wi.id) wallet_count,
        COUNT(DISTINCT CASE WHEN wi.status = \'claimed\' THEN wi.id END) claimed_count,
        COUNT(DISTINCT CASE WHEN wi.status = \'redeemed\' THEN wi.id END) redeemed_count,
        COUNT(DISTINCT CASE WHEN wi.source_type = \'contest_winner\' THEN wi.id END) winner_count
        FROM campaign_contacts cc
        LEFT JOIN wallet_items wi ON wi.contact_id = cc.id
        WHERE cc.campaign_id = ? AND cc.merchant_user_id = ?
        GROUP BY cc.id
        ORDER BY cc.updated_at DESC, cc.id DESC
        LIMIT 100');
    $contactStmt->execute([(int) $campaign['id'], $merchantId]);
    $contacts = array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'email' => (string) $row['email'],
        'phone' => (string) ($row['phone'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'source' => (string) $row['source'],
        'opt_in_status' => (string) $row['opt_in_status'],
        'wallet_count' => (int) $row['wallet_count'],
        'claimed_count' => (int) $row['claimed_count'],
        'redeemed_count' => (int) $row['redeemed_count'],
        'winner_count' => (int) $row['winner_count'],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ], $contactStmt->fetchAll());

    $eventStmt = $pdo->prepare('SELECT ce.public_id,ce.event_type,ce.event_context_json,ce.created_at,wi.public_id wallet_item_id,cc.public_id contact_id,cc.email contact_email
        FROM campaign_events ce
        LEFT JOIN wallet_items wi ON wi.id = ce.wallet_item_id
        LEFT JOIN campaign_contacts cc ON cc.id = ce.contact_id
        WHERE ce.campaign_id = ? AND ce.merchant_user_id = ?
        ORDER BY ce.created_at DESC, ce.id DESC
        LIMIT 100');
    $eventStmt->execute([(int) $campaign['id'], $merchantId]);
    $events = array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'event_type' => (string) $row['event_type'],
        'event_context' => mg_s12d_decode_json($row['event_context_json'] ?? null),
        'wallet_item_id' => $row['wallet_item_id'] ?? null,
        'contact_id' => $row['contact_id'] ?? null,
        'contact_email' => $row['contact_email'] ?? null,
        'created_at' => $row['created_at'] ?? null,
    ], $eventStmt->fetchAll());

    $walletStmt = $pdo->prepare('SELECT status, COUNT(*) total FROM wallet_items WHERE campaign_id = ? AND merchant_user_id = ? GROUP BY status');
    $walletStmt->execute([(int) $campaign['id'], $merchantId]);
    $walletStatus = ['issued' => 0, 'viewed' => 0, 'claimed' => 0, 'redeemed' => 0, 'expired' => 0, 'cancelled' => 0];
    foreach ($walletStmt->fetchAll() as $row) {
        $walletStatus[(string) $row['status']] = (int) $row['total'];
    }

    mg_ok(['campaign' => [
        'id' => (string) $campaign['public_id'],
        'slug' => $campaign['public_slug'] ?? null,
        'campaign_type' => (string) $campaign['campaign_type'],
        'status' => (string) $campaign['status'],
        'title' => (string) $campaign['title'],
        'description' => (string) ($campaign['description'] ?? ''),
        'form_headline' => (string) ($campaign['form_headline'] ?? ''),
        'form_description' => (string) ($campaign['form_description'] ?? ''),
        'success_message' => (string) ($campaign['success_message'] ?? ''),
        'agent_discoverable' => (bool) $campaign['agent_discoverable'],
        'quantity_limit' => $campaign['quantity_limit'] === null ? null : (int) $campaign['quantity_limit'],
        'issued_count' => (int) $campaign['issued_count'],
        'claimed_count' => (int) ($walletStatus['claimed'] ?? 0),
        'redeemed_count' => (int) ($walletStatus['redeemed'] ?? 0),
        'starts_at' => $campaign['starts_at'] ?? null,
        'ends_at' => $campaign['ends_at'] ?? null,
        'rules' => mg_s12d_decode_json($campaign['rules_json'] ?? null),
        'reward_template' => [
            'id' => $campaign['reward_template_public_id'] ?? null,
            'title' => $campaign['reward_template_title'] ?? null,
            'reward_type' => $campaign['reward_type'] ?? null,
            'value_type' => $campaign['value_type'] ?? null,
            'value_amount_cents' => $campaign['value_amount_cents'] === null ? null : (int) $campaign['value_amount_cents'],
            'value_percent' => $campaign['value_percent'] === null ? null : (float) $campaign['value_percent'],
            'currency' => $campaign['currency'] ?? 'USD',
            'redemption_instructions' => $campaign['redemption_instructions'] ?? null,
        ],
        'public_tools' => mg_s12d_campaign_tool_urls($campaign),
        'wallet_status' => $walletStatus,
        'contacts' => $contacts,
        'events' => $events,
    ], 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_detail.unavailable', 'Campaign detail unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Campaign detail unavailable.', 500);
}
