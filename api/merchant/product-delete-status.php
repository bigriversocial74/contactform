<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('catalog.products.view');
$pdo = mg_db();

function mg_product_delete_status_table_exists(PDO $pdo, string $tableName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

$rawIds = trim((string) ($_GET['ids'] ?? ''));
$ids = array_values(array_unique(array_filter(array_map('trim', explode(',', $rawIds)), static function ($value): bool {
    return $value !== '' && strlen($value) <= 64;
})));
$ids = array_slice($ids, 0, 100);

if (!$ids) {
    mg_ok(['products' => []]);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id, public_id, status FROM catalog_products WHERE merchant_user_id = ? AND public_id IN ($placeholders)");
$stmt->execute(array_merge([(int) $user['id']], $ids));
$rows = $stmt->fetchAll();

$productDbIds = [];
foreach ($rows as $row) {
    $productDbIds[] = (int) $row['id'];
}

$purchaseCounts = [];
if ($productDbIds && mg_product_delete_status_table_exists($pdo, 'commerce_order_items')) {
    $purchasePlaceholders = implode(',', array_fill(0, count($productDbIds), '?'));
    $purchaseStmt = $pdo->prepare("SELECT product_id, COUNT(*) AS purchase_count FROM commerce_order_items WHERE product_id IN ($purchasePlaceholders) GROUP BY product_id");
    $purchaseStmt->execute($productDbIds);
    foreach ($purchaseStmt->fetchAll() as $purchaseRow) {
        $purchaseCounts[(int) $purchaseRow['product_id']] = (int) $purchaseRow['purchase_count'];
    }
}

$products = [];
foreach ($rows as $row) {
    $purchaseCount = $purchaseCounts[(int) $row['id']] ?? 0;
    $products[(string) $row['public_id']] = [
        'id' => (string) $row['public_id'],
        'status' => (string) $row['status'],
        'purchase_count' => $purchaseCount,
        'has_purchases' => $purchaseCount > 0,
        'can_delete' => $purchaseCount === 0,
        'can_archive' => $purchaseCount > 0 && (string) $row['status'] !== 'archived',
    ];
}

mg_ok(['products' => $products]);
