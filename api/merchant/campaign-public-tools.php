<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

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

    $base = rtrim((string) (defined('MG_APP_URL') ? MG_APP_URL : ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }
    $slugOrId = $campaign['public_slug'] ?: $campaign['public_id'];
    $publicUrl = $base . '/campaign.php?c=' . rawurlencode((string) $slugOrId);
    $qrUrl = $campaign['qr_code_token'] ? $base . '/campaign.php?token=' . rawurlencode((string) $campaign['qr_code_token']) : null;
    $submitEndpoint = '/api/public/campaigns/signup.php';
    if ($campaign['campaign_type'] === 'qr_reward_drop') $submitEndpoint = '/api/public/campaigns/qr-pickup.php';
    if ($campaign['campaign_type'] === 'contest_giveaway') $submitEndpoint = '/api/public/campaigns/contest-entry.php';

    mg_ok(['tools' => [
        'campaign_id' => (string) $campaign['public_id'],
        'title' => (string) $campaign['title'],
        'campaign_type' => (string) $campaign['campaign_type'],
        'status' => (string) $campaign['status'],
        'public_url' => $publicUrl,
        'qr_url' => $qrUrl,
        'qr_token' => $campaign['qr_code_token'] ?? null,
        'submit_endpoint' => $submitEndpoint,
    ], 'schema_ready' => true]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.campaign_public_tools.unavailable', 'Campaign public tools unavailable.', ['exception_class' => $error::class], $merchantId);
    mg_fail('Campaign tools unavailable.', 500);
}
