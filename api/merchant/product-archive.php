<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/catalog/_catalog.php';
require_once __DIR__ . '/_storefront.php';

mg_require_method('POST');
$user = mg_require_permission('catalog.products.manage');
$input = mg_input();
mg_require_csrf_for_write($input);

$productPublicId = strtolower(trim((string)($input['product_id'] ?? $input['id'] ?? '')));
if ($productPublicId === '' || strlen($productPublicId) > 64) {
    mg_fail('Choose a valid product to archive.', 422);
}

$pdo = mg_db();
$userId = (int)$user['id'];

try {
    $pdo->beginTransaction();

    $productStmt = $pdo->prepare('SELECT * FROM catalog_products WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
    $productStmt->execute([$productPublicId, $userId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) throw new RuntimeException('Product not found.');

    if ((string)$product['status'] === 'archived') {
        $pdo->commit();
        mg_ok([
            'product_id'=>$productPublicId,
            'status'=>'archived',
            'duplicate'=>true,
            'storefront_revision_created'=>false,
        ], 'Product was already archived.');
    }

    $storeStmt = $pdo->prepare(
        'SELECT s.id storefront_id,s.public_id storefront_public_id,ss.published_revision_id,
                r.public_id revision_public_id,r.version_number,r.display_name,r.headline,r.description,
                r.logo_asset_id,r.cover_asset_id,r.contact_json,r.theme_json
         FROM merchant_storefronts s
         LEFT JOIN merchant_storefront_states ss ON ss.storefront_id=s.id
         LEFT JOIN merchant_storefront_revisions r ON r.id=ss.published_revision_id
         WHERE s.merchant_user_id=? LIMIT 1 FOR UPDATE'
    );
    $storeStmt->execute([$userId]);
    $store = $storeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $newRevisionPublicId = null;
    $removedPublishedPlacements = 0;

    if ($store && !empty($store['published_revision_id'])) {
        $placementStmt = $pdo->prepare(
            'SELECT catalog_product_id,sort_order,is_featured,visibility
             FROM merchant_storefront_revision_products
             WHERE storefront_revision_id=? ORDER BY sort_order,id'
        );
        $placementStmt->execute([(int)$store['published_revision_id']]);
        $placements = $placementStmt->fetchAll(PDO::FETCH_ASSOC);
        $remaining = [];
        foreach ($placements as $placement) {
            if ((int)$placement['catalog_product_id'] === (int)$product['id']) {
                $removedPublishedPlacements++;
                continue;
            }
            $remaining[] = $placement;
        }

        if ($removedPublishedPlacements > 0) {
            $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM merchant_storefront_revisions WHERE storefront_id=?');
            $versionStmt->execute([(int)$store['storefront_id']]);
            $versionNumber = (int)$versionStmt->fetchColumn();
            $newRevisionPublicId = mg_catalog_uuid();
            $checksum = mg_storefront_checksum([
                $store['display_name'] ?? '', $store['headline'] ?? null, $store['description'] ?? null,
                $store['logo_asset_id'] ?? null, $store['cover_asset_id'] ?? null,
                $store['contact_json'] ?? null, $store['theme_json'] ?? null, $remaining,
            ]);

            $pdo->prepare(
                "INSERT INTO merchant_storefront_revisions
                 (public_id,storefront_id,version_number,revision_status,display_name,headline,description,
                  logo_asset_id,cover_asset_id,contact_json,theme_json,checksum,published_at,created_by_user_id,created_at,updated_at)
                 VALUES (?,?,?,'published',?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())"
            )->execute([
                $newRevisionPublicId, (int)$store['storefront_id'], $versionNumber,
                (string)($store['display_name'] ?? 'Storefront'),
                ($store['headline'] ?? null) ?: null,
                ($store['description'] ?? null) ?: null,
                $store['logo_asset_id'] ?? null,
                $store['cover_asset_id'] ?? null,
                $store['contact_json'] ?? null,
                $store['theme_json'] ?? null,
                $checksum, $userId,
            ]);
            $newRevisionId = (int)$pdo->lastInsertId();
            $insert = $pdo->prepare(
                'INSERT INTO merchant_storefront_revision_products
                 (storefront_revision_id,catalog_product_id,sort_order,is_featured,visibility,created_at,updated_at)
                 VALUES (?,?,?,?,?,NOW(),NOW())'
            );
            foreach ($remaining as $placement) {
                $insert->execute([
                    $newRevisionId, (int)$placement['catalog_product_id'], (int)$placement['sort_order'],
                    !empty($placement['is_featured']) ? 1 : 0,
                    (string)$placement['visibility'] === 'hidden' ? 'hidden' : 'visible',
                ]);
            }
            $pdo->prepare("UPDATE merchant_storefront_revisions SET revision_status='retired',updated_at=NOW() WHERE id=?")
                ->execute([(int)$store['published_revision_id']]);
            $pdo->prepare(
                'UPDATE merchant_storefront_states SET published_revision_id=?,updated_at=NOW() WHERE storefront_id=?'
            )->execute([$newRevisionId, (int)$store['storefront_id']]);
        }
    }

    $draftDelete = $pdo->prepare(
        "DELETE rp FROM merchant_storefront_revision_products rp
         INNER JOIN merchant_storefront_revisions r ON r.id=rp.storefront_revision_id AND r.revision_status='draft'
         INNER JOIN merchant_storefronts s ON s.id=r.storefront_id
         WHERE s.merchant_user_id=? AND rp.catalog_product_id=?"
    );
    $draftDelete->execute([$userId, (int)$product['id']]);
    $removedDraftPlacements = $draftDelete->rowCount();

    $templateStmt = $pdo->prepare(
        "UPDATE catalog_pppm_templates t
         INNER JOIN catalog_product_versions v ON v.id=t.product_version_id
         SET t.status='retired',t.updated_at=NOW()
         WHERE v.product_id=? AND t.status<>'retired'"
    );
    $templateStmt->execute([(int)$product['id']]);
    $retiredTemplates = $templateStmt->rowCount();

    $feedStmt = $pdo->prepare(
        "UPDATE feed_posts SET status='archived',visibility='private',archived_at=COALESCE(archived_at,NOW()),updated_at=NOW()
         WHERE merchant_user_id=? AND catalog_product_id=? AND status<>'archived'"
    );
    $feedStmt->execute([$userId, (int)$product['id']]);
    $archivedFeedPosts = $feedStmt->rowCount();

    $pdo->prepare(
        "UPDATE catalog_products SET status='archived',archived_at=NOW(),updated_at=NOW() WHERE id=?"
    )->execute([(int)$product['id']]);

    $pdo->commit();

    mg_audit('catalog.product_archived', 'catalog_product', [
        'product_id'=>$productPublicId,
        'removed_published_placements'=>$removedPublishedPlacements,
        'removed_draft_placements'=>$removedDraftPlacements,
        'retired_templates'=>$retiredTemplates,
        'archived_feed_posts'=>$archivedFeedPosts,
        'storefront_revision_id'=>$newRevisionPublicId,
    ], $userId);

    mg_ok([
        'product_id'=>$productPublicId,
        'status'=>'archived',
        'duplicate'=>false,
        'storefront_revision_created'=>$newRevisionPublicId !== null,
        'storefront_revision_id'=>$newRevisionPublicId,
        'removed_published_placements'=>$removedPublishedPlacements,
        'removed_draft_placements'=>$removedDraftPlacements,
        'retired_templates'=>$retiredTemplates,
        'archived_feed_posts'=>$archivedFeedPosts,
    ], 'Product archived and removed from active catalog distribution.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'catalog.product_archive_failed', 'Merchant product archive failed.', [
        'product_id'=>$productPublicId,
        'exception_type'=>$error::class,
    ], $userId);
    mg_fail('Unable to archive the product.', 500);
}
