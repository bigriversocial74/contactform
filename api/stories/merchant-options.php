<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int)$user['id'];
mg_rate_limit('stories.merchant_options', 'user:' . $userId, 120, 60);

if (!mg_stories_user_can_merchant($user, $pdo)) {
    mg_ok(['merchant' => false, 'products' => [], 'campaigns' => []], 'No merchant story options are available.');
}

$products = [];
if (mg_stories_table_exists($pdo, 'catalog_products') && mg_stories_table_exists($pdo, 'catalog_product_versions')) {
    $stmt = $pdo->prepare("SELECT p.public_id,p.slug,p.status,p.product_type,v.title,v.description,v.unit_value_cents,v.currency FROM catalog_products p LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE p.merchant_user_id=? AND p.status<>'archived' ORDER BY p.updated_at DESC,p.id DESC LIMIT 100");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $title = mg_stories_text($row['title'] ?? $row['slug'] ?? 'Product', 160, 'Product');
        $products[] = [
            'id' => (string)$row['public_id'],
            'title' => $title,
            'status' => (string)$row['status'],
            'type' => (string)$row['product_type'],
            'value_cents' => (int)($row['unit_value_cents'] ?? 0),
            'currency' => (string)($row['currency'] ?? 'USD'),
            'url' => mg_stories_product_url($row),
        ];
    }
}

$campaigns = [];
if (mg_stories_table_exists($pdo, 'campaigns')) {
    $stmt = $pdo->prepare("SELECT public_id,public_slug,title,description,campaign_type,status,starts_at,ends_at FROM campaigns WHERE merchant_user_id=? AND status<>'archived' ORDER BY updated_at DESC,id DESC LIMIT 100");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $campaigns[] = [
            'id' => (string)$row['public_id'],
            'title' => mg_stories_text($row['title'] ?? 'Campaign', 160, 'Campaign'),
            'status' => (string)$row['status'],
            'type' => (string)$row['campaign_type'],
            'url' => mg_stories_campaign_url($row),
        ];
    }
}

mg_ok(['merchant' => true, 'products' => $products, 'campaigns' => $campaigns, 'rewards' => []], 'Merchant story options loaded.');
