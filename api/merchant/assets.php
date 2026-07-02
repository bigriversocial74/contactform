<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('catalog.assets.manage');
$pdo = mg_db();

function mg_merchant_assets_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

$type = trim((string) ($_GET['type'] ?? 'all'));
$status = trim((string) ($_GET['status'] ?? 'all'));
$q = trim((string) ($_GET['q'] ?? ''));

$where = ['a.owner_user_id = ?'];
$params = [(int) $user['id']];

if (mg_merchant_assets_column_exists($pdo, 'catalog_assets', 'deleted_at')) {
    $where[] = 'a.deleted_at IS NULL';
}
if (in_array($type, ['image','audio','video','document','download','qr_template','other'], true)) {
    $where[] = 'a.asset_type = ?';
    $params[] = $type;
}
if (in_array($status, ['pending','processing','ready','failed','rejected','retired','archived'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = 'a.original_filename LIKE ?';
    $params[] = '%' . $q . '%';
}

$sql = 'SELECT a.public_id,a.asset_type,a.storage_provider,a.original_filename,a.mime_type,a.byte_size,a.width_px,a.height_px,a.duration_ms,a.status,a.created_at,a.updated_at,COUNT(DISTINCT pva.product_version_id) usage_count
        FROM catalog_assets a
        LEFT JOIN catalog_product_version_assets pva ON pva.asset_id = a.id
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY a.id
        ORDER BY a.created_at DESC,a.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
mg_ok(['assets' => $stmt->fetchAll()]);
