<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('catalog.assets.manage');
$pdo = mg_db();

function mg_asset_delete_status_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

$rawIds = trim((string) ($_GET['ids'] ?? ''));
$ids = array_values(array_unique(array_filter(array_map('trim', explode(',', $rawIds)), static function ($value): bool {
    return $value !== '' && strlen($value) <= 64;
})));
$ids = array_slice($ids, 0, 100);

if (!$ids) {
    mg_ok(['assets' => []]);
}

$whereDeleted = mg_asset_delete_status_column_exists($pdo, 'catalog_assets', 'deleted_at') ? ' AND a.deleted_at IS NULL' : '';
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT a.id, a.public_id, a.asset_type, a.original_filename,
            (SELECT COUNT(DISTINCT p.id)
               FROM catalog_product_version_assets pva
               INNER JOIN catalog_product_versions v ON v.id = pva.product_version_id
               INNER JOIN catalog_products p ON p.id = v.product_id
              WHERE pva.asset_id = a.id AND p.status IN ('published','archived')) AS protected_product_count
       FROM catalog_assets a
      WHERE a.owner_user_id = ? AND a.public_id IN ($placeholders)$whereDeleted"
);
$stmt->execute(array_merge([(int) $user['id']], $ids));

$assets = [];
foreach ($stmt->fetchAll() as $row) {
    $protectedCount = (int) ($row['protected_product_count'] ?? 0);
    $assets[(string) $row['public_id']] = [
        'id' => (string) $row['public_id'],
        'asset_type' => (string) $row['asset_type'],
        'original_filename' => (string) ($row['original_filename'] ?? ''),
        'protected_product_count' => $protectedCount,
        'can_delete' => $protectedCount === 0,
    ];
}

mg_ok(['assets' => $assets]);
