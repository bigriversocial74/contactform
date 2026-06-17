<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/catalog/_catalog.php';
require_once dirname(__DIR__) . '/api/merchant/_storefront.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function mg_spm_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = mg_db();
$runId = 'storefrontproducts' . bin2hex(random_bytes(5));
$result = array_fill_keys([
    'asset_projection', 'published_product_visible', 'storefront_ready',
    'draft_preserves_live_status', 'replacement_version_atomic',
    'version_history_immutable', 'storefront_tracks_current_version', 'rollback_clean',
], false);

$pdo->beginTransaction();
try {
    $userId = mg_it_user($pdo, $runId . '@example.test', 'Storefront Product Merchant');

    $assetPublicId = mg_catalog_uuid();
    $assetStmt = $pdo->prepare(
        "INSERT INTO catalog_assets
         (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,
          byte_size,checksum_sha256,width_px,height_px,status,metadata_json,created_at,updated_at)
         VALUES (?,?, 'image','private_local',?,'cover.png','image/png',1024,?,1200,800,'ready',?,NOW(),NOW())"
    );
    $assetStmt->execute([
        $assetPublicId,
        $userId,
        'catalog/' . $userId . '/' . $assetPublicId . '.png',
        hash('sha256', $runId . '-asset'),
        json_encode(['builder_role' => 'cover'], JSON_UNESCAPED_SLASHES),
    ]);
    $assetId = (int)$pdo->lastInsertId();
    mg_spm_assert(mg_storefront_asset_public_id($pdo, $assetId, $userId) === $assetPublicId, 'Ready owner asset was not projected safely.');
    $result['asset_projection'] = true;

    $productPublicId = mg_catalog_uuid();
    $pdo->prepare(
        "INSERT INTO catalog_products
         (public_id,merchant_user_id,product_type,slug,status,created_by_user_id,published_at,created_at,updated_at)
         VALUES (?,?, 'gift',?,'published',?,NOW(),NOW(),NOW())"
    )->execute([$productPublicId, $userId, $runId . '-gift', $userId]);
    $productId = (int)$pdo->lastInsertId();

    $versionOnePublicId = mg_catalog_uuid();
    $versionOnePayload = [
        'title' => 'Published Phoenix Gift',
        'headline' => 'The live version',
        'value_cents' => 2500,
        'currency' => 'USD',
        'slug' => $runId . '-gift',
        'visibility' => 'public',
    ];
    $pdo->prepare(
        "INSERT INTO catalog_product_versions
         (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,
          fulfillment_json,metadata_json,checksum,created_by_user_id,published_at,created_at)
         VALUES (?,?,1,'published','Published Phoenix Gift','Live storefront product',2500,'USD',?,?,?, ?,NOW(),NOW())"
    )->execute([
        $versionOnePublicId,
        $productId,
        json_encode(['builder_type' => 'greeting_card', 'media_roles' => ['cover']], JSON_UNESCAPED_SLASHES),
        json_encode($versionOnePayload, JSON_UNESCAPED_SLASHES),
        mg_catalog_version_checksum($versionOnePayload),
        $userId,
    ]);
    $versionOneId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE catalog_products SET current_version_id=? WHERE id=?')->execute([$versionOneId, $productId]);
    $pdo->prepare(
        "INSERT INTO catalog_product_version_assets (product_version_id,asset_id,role,sort_order,created_at)
         VALUES (?,?,'cover',0,NOW())"
    )->execute([$versionOneId, $assetId]);

    $draftPublicId = mg_catalog_uuid();
    $draftPayload = $versionOnePayload;
    $draftPayload['headline'] = 'An unpublished replacement draft';
    $pdo->prepare(
        "INSERT INTO catalog_builder_drafts
         (public_id,product_id,builder_type,payload_json,asset_map_json,lock_version,updated_by_user_id,created_at,updated_at)
         VALUES (?,?,'greeting_card',?,?,2,?,NOW(),DATE_ADD(NOW(),INTERVAL 1 SECOND))"
    )->execute([
        $draftPublicId,
        $productId,
        json_encode($draftPayload, JSON_UNESCAPED_SLASHES),
        json_encode(['cover' => $assetPublicId], JSON_UNESCAPED_SLASHES),
        $userId,
    ]);

    $storePublicId = mg_merchant_uuid();
    $pdo->prepare(
        "INSERT INTO merchant_storefronts
         (public_id,merchant_user_id,slug,display_name,headline,description,logo_asset_id,cover_asset_id,status,published_at,created_at,updated_at)
         VALUES (?,?,?,'Phoenix Gift Store','Local gifts','Published storefront',?,?,'published',NOW(),NOW(),NOW())"
    )->execute([$storePublicId, $userId, $runId . '-store', $assetId, $assetId]);
    $storeId = (int)$pdo->lastInsertId();

    $publishedRevisionPublicId = mg_merchant_uuid();
    $contact = json_encode(['email' => 'store@example.test'], JSON_UNESCAPED_SLASHES);
    $theme = json_encode(['accent' => '#2563eb'], JSON_UNESCAPED_SLASHES);
    $pdo->prepare(
        "INSERT INTO merchant_storefront_revisions
         (public_id,storefront_id,version_number,revision_status,display_name,headline,description,logo_asset_id,
          cover_asset_id,contact_json,theme_json,checksum,published_at,created_by_user_id,created_at,updated_at)
         VALUES (?,?,1,'published','Phoenix Gift Store','Local gifts','Published storefront',?,?,?,?,?,NOW(),?,NOW(),NOW())"
    )->execute([
        $publishedRevisionPublicId,
        $storeId,
        $assetId,
        $assetId,
        $contact,
        $theme,
        hash('sha256', $runId . '-storefront-v1'),
        $userId,
    ]);
    $publishedRevisionId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO merchant_storefront_revision_products
         (storefront_revision_id,catalog_product_id,sort_order,is_featured,visibility,created_at,updated_at)
         VALUES (?,?,0,1,'visible',NOW(),NOW())"
    )->execute([$publishedRevisionId, $productId]);
    $pdo->prepare(
        'INSERT INTO merchant_storefront_states (storefront_id,published_revision_id,updated_at) VALUES (?,?,NOW())'
    )->execute([$storeId, $publishedRevisionId]);

    $available = mg_storefront_available_products($pdo, $userId);
    mg_spm_assert(count($available) === 1 && (string)$available[0]['public_id'] === $productPublicId, 'Published product was not available to storefront management.');
    mg_spm_assert((string)$available[0]['cover_asset_id'] === $assetPublicId, 'Published product cover projection was incorrect.');
    $result['published_product_visible'] = true;

    $revision = mg_storefront_revision_management($pdo, mg_storefront_revision($pdo, $storeId, 'published') ?: [], $userId);
    $products = mg_storefront_revision_products($pdo, $publishedRevisionId);
    $readiness = mg_storefront_readiness(['status' => 'published', 'slug' => $runId . '-store'], $revision, $products);
    mg_spm_assert($readiness['required_complete'] === true && $readiness['can_publish'] === true, 'Complete storefront did not pass readiness.');
    mg_spm_assert((string)$revision['logo_asset_public_id'] === $assetPublicId, 'Storefront revision did not expose the public asset ID.');
    $result['storefront_ready'] = true;

    $pdo->prepare(
        'UPDATE catalog_builder_drafts SET payload_json=?,lock_version=lock_version+1,updated_at=DATE_ADD(NOW(),INTERVAL 2 SECOND) WHERE product_id=?'
    )->execute([json_encode($draftPayload, JSON_UNESCAPED_SLASHES), $productId]);
    $liveCheck = $pdo->prepare('SELECT status,current_version_id FROM catalog_products WHERE id=?');
    $liveCheck->execute([$productId]);
    $live = $liveCheck->fetch(PDO::FETCH_ASSOC) ?: [];
    mg_spm_assert((string)($live['status'] ?? '') === 'published', 'Saving a replacement draft took the live product offline.');
    mg_spm_assert((int)($live['current_version_id'] ?? 0) === $versionOneId, 'Saving a replacement draft changed the live immutable version.');
    mg_spm_assert(count(mg_storefront_available_products($pdo, $userId)) === 1, 'Draft editing removed the live product from storefront availability.');
    $result['draft_preserves_live_status'] = true;

    $versionTwoPublicId = mg_catalog_uuid();
    $versionTwoPayload = $draftPayload;
    $versionTwoPayload['title'] = 'Published Phoenix Gift v2';
    $pdo->prepare(
        "INSERT INTO catalog_product_versions
         (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,
          fulfillment_json,metadata_json,checksum,created_by_user_id,published_at,created_at)
         VALUES (?,?,2,'published','Published Phoenix Gift v2','Replacement version',3000,'USD',?,?,?, ?,NOW(),NOW())"
    )->execute([
        $versionTwoPublicId,
        $productId,
        json_encode(['builder_type' => 'greeting_card', 'media_roles' => ['cover']], JSON_UNESCAPED_SLASHES),
        json_encode($versionTwoPayload, JSON_UNESCAPED_SLASHES),
        mg_catalog_version_checksum($versionTwoPayload),
        $userId,
    ]);
    $versionTwoId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE catalog_product_versions SET version_status='retired' WHERE id=?")->execute([$versionOneId]);
    $pdo->prepare("UPDATE catalog_products SET current_version_id=?,status='published',published_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([$versionTwoId, $productId]);
    $current = $pdo->prepare('SELECT current_version_id,status FROM catalog_products WHERE id=?');
    $current->execute([$productId]);
    $currentRow = $current->fetch(PDO::FETCH_ASSOC) ?: [];
    mg_spm_assert((int)($currentRow['current_version_id'] ?? 0) === $versionTwoId && (string)($currentRow['status'] ?? '') === 'published', 'Replacement version was not published atomically.');
    $result['replacement_version_atomic'] = true;

    $history = $pdo->prepare('SELECT version_number,version_status FROM catalog_product_versions WHERE product_id=? ORDER BY version_number');
    $history->execute([$productId]);
    $historyRows = $history->fetchAll(PDO::FETCH_ASSOC);
    mg_spm_assert(count($historyRows) === 2, 'Product version history was not preserved.');
    mg_spm_assert((string)$historyRows[0]['version_status'] === 'retired' && (string)$historyRows[1]['version_status'] === 'published', 'Immutable version states were incorrect.');
    $result['version_history_immutable'] = true;

    $storeProducts = mg_storefront_revision_products($pdo, $publishedRevisionId);
    mg_spm_assert(count($storeProducts) === 1 && (string)$storeProducts[0]['title'] === 'Published Phoenix Gift v2', 'Storefront did not resolve the product current version after replacement publish.');
    $result['storefront_tracks_current_version'] = true;

    $pdo->rollBack();
    $result['rollback_clean'] = true;
    echo json_encode($result + ['suite' => 'storefront_product_management_ui_foundation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
