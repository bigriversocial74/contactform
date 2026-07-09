<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';

function mg_campaign_public_tools_base(): string
{
    $base = rtrim((string)(defined('MG_APP_URL') ? MG_APP_URL : ''), '/');
    if ($base !== '') return $base;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
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
    $stmt = $pdo->prepare('SELECT public_id,public_slug,qr_code_token,campaign_type,title,status FROM campaigns WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
    $stmt->execute([$campaignId, $merchantId]);
    $campaign = $stmt->fetch();
    if (!$campaign) mg_fail('Campaign not found.', 404);

    $base = mg_campaign_public_tools_base();
    $type = (string)$campaign['campaign_type'];
    $definition = mg_campaign_type_get($type) ?? [];
    $publicEnabled = !empty($definition['public_enabled']);
    $publicPath = (string)($definition['public_path'] ?? '');
    $slugOrId = $campaign['public_slug'] ?: $campaign['public_id'];
    $publicUrl = $publicEnabled && $publicPath !== '' ? $base . $publicPath . '?campaign=' . rawurlencode((string)$slugOrId) : '';
    $qrUrl = $publicEnabled && $type === 'qr_reward_drop' && !empty($campaign['qr_code_token']) ? $base . $publicPath . '?token=' . rawurlencode((string)$campaign['qr_code_token']) : null;
    $submitEndpoint = (string)($definition['submit_endpoint'] ?? '');

    mg_ok(['tools' => [
        'campaign_id' => (string) $campaign['public_id'],
        'title' => (string) $campaign['title'],
        'campaign_type' => $type,
        'campaign_type_label' => (string)($definition['label'] ?? mg_campaign_type_label($type)),
        'status' => (string) $campaign['status'],
        'public_enabled' => $publicEnabled,
        'internal_only' => !empty($definition['internal_only']),
        'public_url' => $publicUrl,
        'qr_url' => $qrUrl,
        'qr_token' => $campaign['qr_code_token'] ?? null,
        'submit_endpoint' => $submitEndpoint,
    ], 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_public_tools.unavailable', 'Campaign public tools unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Campaign tools unavailable.', 500);
}
