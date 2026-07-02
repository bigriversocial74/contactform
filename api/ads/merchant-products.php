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
    $cents = (int)($row['value_amount_cents'] ?? $row['unit_value_cents'] ?? 0);
    if ($cents <= 0) return '';
    return $currency . ' ' . number_format($cents / 100, 2);
}

function mg_ads_product_asset_url(array $row): ?string
{
    $publicId = strtolower(trim((string)($row['asset_public_id'] ?? $row['public_id'] ?? '')));
    $provider = strtolower(trim((string)($row['storage_provider'] ?? '')));
    $key = trim((string)($row['storage_key'] ?? ''));
    if ($publicId !== '' && preg_match('/^[a-f0-9-]{36}$/', $publicId) === 1 && in_array($provider, ['persistent_local','private_local','local'], true) && function_exists('mg_storage_asset_public_url')) {
        return mg_storage_asset_public_url($publicId);
    }
    if (preg_match('#^https://#i', $key) === 1 && filter_var($key, FILTER_VALIDATE_URL)) return $key;
    if ($provider === 'local' && $key !== '' && !str_contains($key, '..') && !str_contains($key, '\\')) return '/' . ltrim($key, '/');
    return null;
}

function mg_ads_product_public_row(array $row): array
{
    $meta = mg_ads_product_meta($row['metadata_json'] ?? null);
    $pack = is_array($meta['media_pack'] ?? null) ? $meta['media_pack'] : [];
    $cover = mg_ads_safe_url($pack['cover_image_url'] ?? '') ?: null;
    $title = mg_ads_text($row['title'] ?? 'Reward product', 190, 'Reward product');
    $rawDescription = trim((string)($row['description'] ?? ''));
    if ($rawDescription === '') $rawDescription = trim((string)($row['agent_summary'] ?? ''));
    $description = mg_ads_text($rawDescription, 360, '');
    $value = mg_ads_product_value($row);
    $summaryParts = array_values(array_filter([$description, $value !== '' ? 'Value: ' . $value : '']));
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'source' => 'reward_template',
        'source_label' => 'Reward',
        'title' => $title,
        'headline' => $title,
        'description' => $description !== '' ? $description : 'Claim this local reward, save it to your wallet, and redeem it with the merchant.',
        'ad_description' => $summaryParts !== [] ? implode(' · ', $summaryParts) : 'Claim this local reward, save it to your wallet, and redeem it with the merchant.',
        'image_url' => $cover,
        'cta_label' => 'Claim Reward',
        'destination_url' => '/feed.php?offer_id=' . rawurlencode((string)($row['public_id'] ?? '')),
        'reward_type' => (string)($row['reward_type'] ?? ''),
        'value_type' => (string)($row['value_type'] ?? ''),
        'value_amount_cents' => (int)($row['value_amount_cents'] ?? 0),
        'value_label' => trim('Reward' . ($value !== '' ? ' · ' . $value : '')),
        'currency' => (string)($row['currency'] ?? 'USD'),
        'status' => (string)($row['status'] ?? ''),
        'agent_add_to_wallet_allowed' => (bool)((int)($row['agent_add_to_wallet_allowed'] ?? 0)),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_ads_catalog_product_public_row(array $row): array
{
    $title = mg_ads_text($row['title'] ?? 'Merchant product', 190, 'Merchant product');
    $description = mg_ads_text($row['description'] ?? '', 360, '');
    $value = mg_ads_product_value($row);
    $image = mg_ads_product_asset_url($row);
    $slug = trim((string)($row['slug'] ?? ''));
    $destination = $slug !== '' ? '/product.php?p=' . rawurlencode($slug) : '/merchant-product.php?id=' . rawurlencode((string)($row['public_id'] ?? ''));
    $summaryParts = array_values(array_filter([$description, $value !== '' ? 'Value: ' . $value : '']));
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'source' => 'catalog_product',
        'source_label' => 'Product',
        'title' => $title,
        'headline' => $title,
        'description' => $description !== '' ? $description : 'Promote this merchant product with a sponsored Campaign Ads card.',
        'ad_description' => $summaryParts !== [] ? implode(' · ', $summaryParts) : 'Promote this merchant product with a sponsored Campaign Ads card.',
        'image_url' => $image,
        'cta_label' => 'View Product',
        'destination_url' => $destination,
        'reward_type' => (string)($row['product_type'] ?? 'product'),
        'value_type' => 'catalog_product',
        'value_amount_cents' => (int)($row['unit_value_cents'] ?? 0),
        'value_label' => trim('Product' . ($value !== '' ? ' · ' . $value : '')),
        'currency' => (string)($row['currency'] ?? 'USD'),
        'status' => (string)($row['status'] ?? ''),
        'agent_add_to_wallet_allowed' => false,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_ads_campaign_product_public_row(array $row): array
{
    $title = mg_ads_text($row['title'] ?? 'Merchant campaign', 190, 'Merchant campaign');
    $headline = mg_ads_text($row['form_headline'] ?? $title, 190, $title);
    $description = mg_ads_text($row['form_description'] ?? $row['description'] ?? '', 360, '');
    $slug = trim((string)($row['public_slug'] ?? ''));
    $ref = $slug !== '' ? $slug : (string)($row['public_id'] ?? '');
    $type = (string)($row['campaign_type'] ?? 'campaign');
    return [
        'id' => (string)($row['public_id'] ?? ''),
        'source' => 'campaign',
        'source_label' => 'Campaign',
        'title' => $title,
        'headline' => $headline,
        'description' => $description !== '' ? $description : 'Promote this merchant campaign with a sponsored Campaign Ads card.',
        'ad_description' => $description !== '' ? $description : 'Promote this merchant campaign with a sponsored Campaign Ads card.',
        'image_url' => null,
        'cta_label' => $type === 'contest_giveaway' ? 'Enter Campaign' : 'Join Campaign',
        'destination_url' => '/campaign.php?c=' . rawurlencode($ref),
        'reward_type' => $type,
        'value_type' => 'merchant_campaign',
        'value_amount_cents' => 0,
        'value_label' => 'Campaign · ' . (string)($row['status'] ?? ''),
        'currency' => 'USD',
        'status' => (string)($row['status'] ?? ''),
        'agent_add_to_wallet_allowed' => false,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

try {
    $merchantId = (int)($user['id'] ?? 0);
    $status = trim((string)($_GET['status'] ?? 'active'));
    $allowed = ['active','draft','paused','published','all'];
    if (!in_array($status, $allowed, true)) $status = 'active';
    $products = [];
    $sources = [];

    if (mg_ads_table_exists($pdo, 'reward_templates')) {
        $sql = "SELECT public_id,title,description,reward_type,value_type,value_amount_cents,currency,agent_summary,agent_add_to_wallet_allowed,metadata_json,status,updated_at FROM reward_templates WHERE merchant_user_id=? AND status<>'archived'";
        $params = [$merchantId];
        if ($status !== 'all') {
            $rewardStatus = $status === 'published' ? 'active' : $status;
            $sql .= ' AND status=?';
            $params[] = $rewardStatus;
        }
        $sql .= ' ORDER BY updated_at DESC,id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $products = array_merge($products, array_map('mg_ads_product_public_row', $rows));
        $sources['reward_templates'] = count($rows);
    }

    if (mg_ads_table_exists($pdo, 'campaigns')) {
        $sql = "SELECT public_id,public_slug,campaign_type,title,description,form_headline,form_description,status,updated_at FROM campaigns WHERE merchant_user_id=? AND status<>'archived'";
        $params = [$merchantId];
        if ($status !== 'all') {
            $campaignStatus = $status === 'published' ? 'active' : $status;
            $sql .= ' AND status=?';
            $params[] = $campaignStatus;
        }
        $sql .= ' ORDER BY updated_at DESC,id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $products = array_merge($products, array_map('mg_ads_campaign_product_public_row', $rows));
        $sources['campaigns'] = count($rows);
    }

    if (mg_ads_table_exists($pdo, 'catalog_products') && mg_ads_table_exists($pdo, 'catalog_product_versions')) {
        $sql = "SELECT p.public_id,p.product_type,p.slug,p.status,p.updated_at,v.title,v.description,v.unit_value_cents,v.currency,a.public_id asset_public_id,a.storage_provider,a.storage_key
                FROM catalog_products p
                LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
                LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=p.current_version_id AND pva.role IN ('cover','thumbnail','gallery')
                LEFT JOIN catalog_assets a ON a.id=pva.asset_id AND a.asset_type='image' AND a.status='ready'
                WHERE p.merchant_user_id=? AND p.status<>'archived'";
        $params = [$merchantId];
        if ($status !== 'all') {
            $catalogStatus = in_array($status, ['active','published'], true) ? 'published' : $status;
            $sql .= ' AND p.status=?';
            $params[] = $catalogStatus;
        }
        $sql .= ' GROUP BY p.id ORDER BY p.updated_at DESC,p.id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $products = array_merge($products, array_map('mg_ads_catalog_product_public_row', $rows));
        $sources['catalog_products'] = count($rows);
    }

    usort($products, static function (array $a, array $b): int {
        return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
    });

    mg_ok(['schema_ready' => true, 'source' => 'combined', 'sources' => $sources, 'products' => array_values($products)], 'Merchant ad products loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'ads.merchant_products_failed', 'Campaign Ads merchant product picker failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], (int)($user['id'] ?? 0));
    mg_fail('Unable to load merchant products.', 422);
}
