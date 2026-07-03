<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('POST');
$user = mg_require_permission('catalog.products.manage');
$input = mg_input();
mg_require_csrf_for_write($input);

$pdo = mg_db();
$productId = trim((string) ($input['id'] ?? ''));
if ($productId === '' || strlen($productId) > 64) {
    mg_fail('Invalid product identifier.', 422);
}

function mg_product_delete_table_exists(PDO $pdo, string $tableName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function mg_product_delete_placeholders(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM catalog_products WHERE merchant_user_id = ? AND public_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int) $user['id'], $productId]);
    $product = $stmt->fetch();
    if (!$product) {
        mg_fail('Product not found.', 404);
    }

    $purchaseCount = 0;
    if (mg_product_delete_table_exists($pdo, 'commerce_order_items')) {
        $purchaseStmt = $pdo->prepare('SELECT COUNT(*) FROM commerce_order_items WHERE product_id = ?');
        $purchaseStmt->execute([(int) $product['id']]);
        $purchaseCount = (int) $purchaseStmt->fetchColumn();
    }
    if ($purchaseCount > 0) {
        mg_fail('This product has purchases and can only be archived.', 409);
    }

    if (mg_product_delete_table_exists($pdo, 'merchant_storefront_revision_products')) {
        $pdo->prepare('DELETE FROM merchant_storefront_revision_products WHERE catalog_product_id = ?')
            ->execute([(int) $product['id']]);
    }

    if (mg_product_delete_table_exists($pdo, 'feed_posts') && mg_product_delete_table_exists($pdo, 'feed_post_versions')) {
        $feedPostStmt = $pdo->prepare('SELECT id FROM feed_posts WHERE catalog_product_id = ?');
        $feedPostStmt->execute([(int) $product['id']]);
        $feedPostIds = array_map('intval', $feedPostStmt->fetchAll(PDO::FETCH_COLUMN));

        if ($feedPostIds) {
            $feedPostPlaceholders = mg_product_delete_placeholders($feedPostIds);
            $feedVersionStmt = $pdo->prepare('SELECT id FROM feed_post_versions WHERE feed_post_id IN (' . $feedPostPlaceholders . ')');
            $feedVersionStmt->execute($feedPostIds);
            $feedVersionIds = array_map('intval', $feedVersionStmt->fetchAll(PDO::FETCH_COLUMN));

            if ($feedVersionIds) {
                $feedVersionPlaceholders = mg_product_delete_placeholders($feedVersionIds);
                if (mg_product_delete_table_exists($pdo, 'content_engagement_events')) {
                    $pdo->prepare('DELETE FROM content_engagement_events WHERE feed_post_version_id IN (' . $feedVersionPlaceholders . ')')
                        ->execute($feedVersionIds);
                }
                if (mg_product_delete_table_exists($pdo, 'pppm_feed_bindings')) {
                    $pdo->prepare('DELETE FROM pppm_feed_bindings WHERE feed_post_version_id IN (' . $feedVersionPlaceholders . ')')
                        ->execute($feedVersionIds);
                }
                if (mg_product_delete_table_exists($pdo, 'feed_post_elements')) {
                    $pdo->prepare('DELETE FROM feed_post_elements WHERE feed_post_version_id IN (' . $feedVersionPlaceholders . ')')
                        ->execute($feedVersionIds);
                }
                $pdo->prepare('UPDATE feed_posts SET current_version_id = NULL WHERE id IN (' . $feedPostPlaceholders . ')')
                    ->execute($feedPostIds);
                $pdo->prepare('DELETE FROM feed_post_versions WHERE id IN (' . $feedVersionPlaceholders . ')')
                    ->execute($feedVersionIds);
            }

            $pdo->prepare('DELETE FROM feed_posts WHERE id IN (' . $feedPostPlaceholders . ')')
                ->execute($feedPostIds);
        }
    }

    if (mg_product_delete_table_exists($pdo, 'catalog_pppm_templates')) {
        $pdo->prepare(
            'DELETE t FROM catalog_pppm_templates t
             INNER JOIN catalog_product_versions v ON v.id = t.product_version_id
             WHERE v.product_id = ?'
        )->execute([(int) $product['id']]);
    }

    if (mg_product_delete_table_exists($pdo, 'catalog_product_version_assets')) {
        $pdo->prepare(
            'DELETE pva FROM catalog_product_version_assets pva
             INNER JOIN catalog_product_versions v ON v.id = pva.product_version_id
             WHERE v.product_id = ?'
        )->execute([(int) $product['id']]);
    }

    if (mg_product_delete_table_exists($pdo, 'catalog_builder_drafts')) {
        $pdo->prepare('DELETE FROM catalog_builder_drafts WHERE product_id = ?')
            ->execute([(int) $product['id']]);
    }

    if (mg_product_delete_table_exists($pdo, 'catalog_product_version_locations')) {
        $pdo->prepare(
            'DELETE cpvl FROM catalog_product_version_locations cpvl
             INNER JOIN catalog_product_versions v ON v.id = cpvl.product_version_id
             WHERE v.product_id = ?'
        )->execute([(int) $product['id']]);
    }

    $pdo->prepare('UPDATE catalog_products SET current_version_id = NULL WHERE id = ?')
        ->execute([(int) $product['id']]);
    $pdo->prepare('DELETE FROM catalog_product_versions WHERE product_id = ?')
        ->execute([(int) $product['id']]);
    $pdo->prepare('DELETE FROM catalog_products WHERE id = ?')
        ->execute([(int) $product['id']]);

    $pdo->commit();
    mg_audit('catalog.product_deleted', 'catalog_product', ['product_id' => $productId], (int) $user['id']);
    mg_ok(['product_id' => $productId, 'deleted' => true], 'Product deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'merchant.product_delete_failed', 'Merchant product delete failed.', [
        'product_id' => $productId,
        'exception_type' => get_class($e),
    ], (int) $user['id']);
    mg_fail('Unable to delete the product.', 500);
}
