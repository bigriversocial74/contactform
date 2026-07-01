<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_ads.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
mg_ads_require_merchant_user($user, $pdo);

function mg_ads_product_meta(?string $json): array
{
    if ($json === null || trim($json) === '') return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function mg_ads_product_value(array $row): string
{
    $currency = (string)($row['currency'] ?? 'USD');
    $cents = (int)($row['value_amount_cents'] ?? 0);
    if ($cents <= 0) return '';
    return $currency . ' ' . number_format($cents / 100, 2);
}

function mg_ads_product_public_row(array $row): array
{
    $meta = mg_ads_product_meta($row['metadata_json'] ?? null);
    $pack = is_array($meta['media_pack'] ?? null) ? $meta['media_pack'] : [];
    $cover = mg_ads_safe_url($pack['cover_image_url'] ?? '');
    $title = mg_ads_text($row['title'] ?? 'Reward product', 190, 'Reward product');
    $description = mg_ads_text($row['description'] ?? $row['agent_summary'] ?? '', 360, '');
    $value = mg_ads_product_value($row);
    $headline = $title;
    $summaryParts = array_values(array_filter([$description, $value !== '' ? 'Value: ' . $value : '']));
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'source' => 'reward_template',
        'title' => $title,
        'headline' => $headline,
        'description' => $description !== '' ? $description : 'Claim this local reward, save it to your wallet, and redeem it with the merchant.',
        'ad_description' => $summaryParts !== [] ? implode(' · ', $summaryParts) : 'Claim this local reward, save it to your wallet, and redeem it with the merchant.',
        'image_url' => $cover,
        'cta_label' => 'Claim Reward',
        'destination_url' => '/feed.php?offer_id=' . rawurlencode((string)($row['public_id'] ?? '')),
        'reward_type' => (string)($row['reward_type'] ?? ''),
        'value_type' => (string)($row['value_type'] ?? ''),
        'value_amount_cents' => (int)($row['value_amount_cents'] ?? 0),
        'value_label' => $value,
        'currency' => (string)($row['currency'] ?? 'USD'),
        'status' => (string)($row['status'] ?? ''),
        'agent_add_to_wallet_allowed' => (bool)((int)($row['agent_add_to_wallet_allowed'] ?? 0)),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

try {
    if (!mg_ads_table_exists($pdo, 'reward_templates')) {
        mg_ok(['schema_ready' => false, 'products' => [], 'source' => 'reward_templates'], 'Reward template products are not available yet.');
    }
    $merchantId = (int)($user['id'] ?? 0);
    $status = trim((string)($_GET['status'] ?? 'active'));
    $allowed = ['active','draft','paused','all'];
    if (!in_array($status, $allowed, true)) $status = 'active';
    $sql = 'SELECT public_id,title,description,reward_type,value_type,value_amount_cents,currency,agent_summary,agent_add_to_wallet_allowed,metadata_json,status,updated_at FROM reward_templates WHERE merchant_user_id=? AND status<>\'archived\'';
    $params = [$merchantId];
    if ($status !== 'all') {
        $sql .= ' AND status=?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY updated_at DESC,id DESC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = array_map('mg_ads_product_public_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    mg_ok(['schema_ready' => true, 'source' => 'reward_templates', 'products' => $products], 'Merchant ad products loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'ads.merchant_products_failed', 'Campaign Ads merchant product picker failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($user['id'] ?? 0));
    mg_fail('Unable to load merchant products.', 422);
}
