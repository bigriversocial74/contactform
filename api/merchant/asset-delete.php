<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('POST');
$user = mg_require_permission('catalog.assets.manage');
$input = mg_input();
mg_require_csrf_for_write($input);

$pdo = mg_db();
$assetId = trim((string) ($input['id'] ?? ''));
if ($assetId === '' || strlen($assetId) > 64) {
    mg_fail('Invalid media identifier.', 422);
}

function mg_asset_delete_table_exists(PDO $pdo, string $tableName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function mg_asset_delete_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM catalog_assets WHERE owner_user_id = ? AND public_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int) $user['id'], $assetId]);
    $asset = $stmt->fetch();
    if (!$asset) {
        mg_fail('Media item not found.', 404);
    }

    if (mg_asset_delete_column_exists($pdo, 'catalog_assets', 'deleted_at') && !empty($asset['deleted_at'])) {
        mg_fail('Media item has already been deleted.', 409);
    }

    $protectedCount = 0;
    if (mg_asset_delete_table_exists($pdo, 'catalog_product_version_assets')) {
        $protectedStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT p.id)
               FROM catalog_product_version_assets pva
               INNER JOIN catalog_product_versions v ON v.id = pva.product_version_id
               INNER JOIN catalog_products p ON p.id = v.product_id
              WHERE pva.asset_id = ? AND p.status IN ('published','archived')"
        );
        $protectedStmt->execute([(int) $asset['id']]);
        $protectedCount = (int) $protectedStmt->fetchColumn();
    }
    if ($protectedCount > 0) {
        mg_fail('This media is attached to a published or archived product and cannot be deleted.', 409);
    }

    if (mg_asset_delete_table_exists($pdo, 'catalog_product_version_assets')) {
        $pdo->prepare('DELETE FROM catalog_product_version_assets WHERE asset_id = ?')
            ->execute([(int) $asset['id']]);
    }

    if (mg_asset_delete_table_exists($pdo, 'feed_post_assets')) {
        $pdo->prepare('DELETE FROM feed_post_assets WHERE asset_id = ?')
            ->execute([(int) $asset['id']]);
    }

    if (mg_asset_delete_table_exists($pdo, 'merchant_storefront_revisions')) {
        $pdo->prepare('UPDATE merchant_storefront_revisions SET logo_asset_id = NULL WHERE logo_asset_id = ?')
            ->execute([(int) $asset['id']]);
        $pdo->prepare('UPDATE merchant_storefront_revisions SET cover_asset_id = NULL WHERE cover_asset_id = ?')
            ->execute([(int) $asset['id']]);
    }

    $usedSoftDelete = false;
    if (mg_asset_delete_column_exists($pdo, 'catalog_assets', 'deleted_at')) {
        $pdo->prepare('UPDATE catalog_assets SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?')
            ->execute([(int) $asset['id']]);
        $usedSoftDelete = true;
    } else {
        $pdo->prepare('DELETE FROM catalog_assets WHERE id = ?')
            ->execute([(int) $asset['id']]);
    }

    $pdo->commit();
    mg_audit('catalog.asset_deleted', 'catalog_asset', [
        'asset_id' => $assetId,
        'soft_delete' => $usedSoftDelete,
    ], (int) $user['id']);
    mg_ok(['asset_id' => $assetId, 'deleted' => true], 'Media deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'merchant.asset_delete_failed', 'Merchant media delete failed.', [
        'asset_id' => $assetId,
        'exception_type' => get_class($e),
    ], (int) $user['id']);
    mg_fail('Unable to delete the media item.', 500);
}
