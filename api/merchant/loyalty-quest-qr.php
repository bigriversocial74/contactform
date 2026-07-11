<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/design-studio-renderer.php';

function mg_lqqr_base_url(): string
{
    $base = rtrim((string)(defined('MG_APP_URL') ? MG_APP_URL : ''), '/');
    if ($base !== '') return $base;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    return $scheme . '://' . $host;
}

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$campaignId = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
if (strlen($campaignId)!==36 || preg_match('/^[a-f0-9-]{36}$/',$campaignId)!==1) mg_fail('Invalid Loyalty Quest.',422);
$stmt = mg_db()->prepare("SELECT public_id,public_slug,title FROM campaigns WHERE public_id=? AND merchant_user_id=? AND campaign_type='loyalty_quest' LIMIT 1");
$stmt->execute([$campaignId,$merchantId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign) mg_fail('Loyalty Quest not found.',404);
$ref = (string)($campaign['public_slug'] ?: $campaign['public_id']);
$url = mg_lqqr_base_url() . '/loyalty-quest.php?campaign=' . rawurlencode($ref);
try {
    $svg = mg_design_renderer_qr_svg($url,8);
} catch (Throwable $error) {
    mg_security_log('error','merchant.loyalty_quest_qr_failed','Unable to render Loyalty Quest QR.',['exception_class'=>$error::class],$merchantId);
    mg_fail('Unable to render Loyalty Quest QR.',500);
}
header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: private, max-age=300');
header('Content-Disposition: ' . (!empty($_GET['download']) ? 'attachment' : 'inline') . '; filename="loyalty-quest-' . preg_replace('/[^a-z0-9-]+/','-',strtolower((string)$campaign['title'])) . '.svg"');
echo $svg;
