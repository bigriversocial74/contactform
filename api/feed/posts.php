<?php
declare(strict_types=1);

require_once __DIR__ . '/_feed.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission($method === 'GET' ? 'catalog.products.view' : 'feed.posts.manage');
$pdo = mg_db();

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT fp.public_id, fp.post_type, fp.visibility, fp.status, fp.promoted_at,
                fpv.public_id AS version_id, fpv.version_number, fpv.headline, fpv.caption,
                fpv.cta_label, fpv.cta_url, fpv.offer_snapshot_json, fpv.presentation_json,
                cp.public_id AS product_id, cp.slug AS product_slug, cpv.title, cpv.unit_value_cents, cpv.currency
         FROM feed_posts fp
         INNER JOIN catalog_products cp ON cp.id = fp.catalog_product_id
         LEFT JOIN feed_post_versions fpv ON fpv.id = fp.current_version_id
         LEFT JOIN catalog_product_versions cpv ON cpv.id = fpv.catalog_product_version_id
         WHERE fp.merchant_user_id = ?
         ORDER BY fp.updated_at DESC, fp.id DESC'
    );
    $stmt->execute([(int) $user['id']]);
    mg_ok(['posts' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    mg_fail('Method not allowed.', 405);
}

$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string) ($input['action'] ?? 'save_draft'));
if (in_array($action, ['publish','promote'], true)) {
    $user = mg_require_permission('feed.posts.publish');
}

$productPublicId = trim((string) ($input['product_id'] ?? ''));
$postPublicId = trim((string) ($input['post_id'] ?? ''));
$postType = mg_feed_post_type(trim((string) ($input['post_type'] ?? 'simple')));
$visibility = mg_feed_visibility(trim((string) ($input['visibility'] ?? 'recipient')));
$headline = trim((string) ($input['headline'] ?? '')) ?: null;
$caption = trim((string) ($input['caption'] ?? '')) ?: null;
$ctaLabel = trim((string) ($input['cta_label'] ?? '')) ?: null;
$ctaUrl = trim((string) ($input['cta_url'] ?? '')) ?: null;
$offer = mg_feed_json($input['offer'] ?? null);
$presentation = mg_feed_json($input['presentation'] ?? null);
$elements = is_array($input['elements'] ?? null) ? $input['elements'] : [];

try {
    $pdo->beginTransaction();

    $productStmt = $pdo->prepare(
        "SELECT cp.*, cpv.id AS version_db_id, cpv.public_id AS version_public_id
         FROM catalog_products cp
         INNER JOIN catalog_product_versions cpv ON cpv.id = cp.current_version_id
         WHERE cp.public_id = ? AND cp.merchant_user_id = ? AND cp.status = 'published'
         LIMIT 1 FOR UPDATE"
    );
    $productStmt->execute([$productPublicId, (int) $user['id']]);
    $product = $productStmt->fetch();
    if (!$product) {
        mg_fail('Published product not found.', 404);
    }

    if ($postPublicId === '') {
        $postPublicId = mg_feed_uuid();
        $pdo->prepare(
            "INSERT INTO feed_posts
             (public_id, merchant_user_id, catalog_product_id, post_type, visibility, status, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'draft', ?, NOW(), NOW())"
        )->execute([$postPublicId, (int) $user['id'], (int) $product['id'], $postType, $visibility, (int) $user['id']]);
        $post = ['id' => (int) $pdo->lastInsertId(), 'status' => 'draft'];
    } else {
        $post = mg_feed_post_for_update($pdo, (int) $user['id'], $postPublicId);
        if (in_array((string) $post['status'], ['promoted','retired','archived'], true)) {
            mg_fail('Promoted or retired feed content cannot be changed in place. Create a new version.', 409);
        }
        $pdo->prepare('UPDATE feed_posts SET post_type = ?, visibility = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$postType, $visibility, (int) $post['id']]);
    }

    $nextStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM feed_post_versions WHERE feed_post_id = ?');
    $nextStmt->execute([(int) $post['id']]);
    $versionNumber = (int) $nextStmt->fetchColumn();
    $versionId = mg_feed_uuid();
    $checksum = hash('sha256', json_encode([
        'product_version' => $product['version_public_id'],
        'post_type' => $postType,
        'headline' => $headline,
        'caption' => $caption,
        'cta_label' => $ctaLabel,
        'cta_url' => $ctaUrl,
        'offer' => $offer,
        'presentation' => $presentation,
        'elements' => $elements,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $versionStatus = in_array($action, ['publish','promote'], true) ? 'published' : 'draft';
    $immutableAt = $versionStatus === 'published' ? date('Y-m-d H:i:s') : null;
    $pdo->prepare(
        'INSERT INTO feed_post_versions
         (public_id, feed_post_id, catalog_product_version_id, version_number, version_status,
          headline, caption, cta_label, cta_url, offer_snapshot_json, presentation_json, checksum,
          immutable_at, published_at, created_by_user_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    )->execute([
        $versionId, (int) $post['id'], (int) $product['version_db_id'], $versionNumber, $versionStatus,
        $headline, $caption, $ctaLabel, $ctaUrl, $offer, $presentation, $checksum,
        $immutableAt, $immutableAt, (int) $user['id'],
    ]);
    $versionDbId = (int) $pdo->lastInsertId();

    $elementInsert = $pdo->prepare(
        'INSERT INTO feed_post_elements
         (public_id, feed_post_version_id, element_type, asset_id, sort_order, content_json, checksum, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $assetLookup = $pdo->prepare("SELECT id FROM catalog_assets WHERE public_id = ? AND owner_user_id = ? AND status = 'ready' LIMIT 1");
    $allowedElements = ['text','image','audio','video','carousel','offer','cta','claim_panel','other'];
    foreach ($elements as $index => $element) {
        if (!is_array($element)) continue;
        $type = (string) ($element['type'] ?? 'other');
        if (!in_array($type, $allowedElements, true)) mg_fail('Invalid feed element type.', 422);
        $assetDbId = null;
        $assetPublicId = trim((string) ($element['asset_id'] ?? ''));
        if ($assetPublicId !== '') {
            $assetLookup->execute([$assetPublicId, (int) $user['id']]);
            $assetDbId = $assetLookup->fetchColumn();
            if (!$assetDbId) mg_fail('Feed media asset is unavailable.', 422);
        }
        $content = mg_feed_json(is_array($element['content'] ?? null) ? $element['content'] : null, 65536);
        $elementChecksum = hash('sha256', $type . '|' . $assetPublicId . '|' . (string) $content . '|' . $index);
        $elementInsert->execute([mg_feed_uuid(), $versionDbId, $type, $assetDbId ?: null, $index, $content, $elementChecksum]);
    }

    $postStatus = $action === 'promote' ? 'promoted' : ($action === 'publish' ? 'published' : 'draft');
    $pdo->prepare(
        'UPDATE feed_posts SET current_version_id = ?, status = ?, promoted_at = ?, updated_at = NOW() WHERE id = ?'
    )->execute([$versionDbId, $postStatus, $action === 'promote' ? date('Y-m-d H:i:s') : null, (int) $post['id']]);
    if ($versionStatus === 'published') {
        $pdo->prepare("UPDATE feed_post_versions SET version_status = 'retired' WHERE feed_post_id = ? AND id <> ? AND version_status = 'published'")
            ->execute([(int) $post['id'], $versionDbId]);
    }

    $pdo->commit();
    mg_audit('feed.post_' . $action, 'feed_post', ['post_id' => $postPublicId, 'version_id' => $versionId], (int) $user['id']);
    mg_ok([
        'post_id' => $postPublicId,
        'version_id' => $versionId,
        'version_number' => $versionNumber,
        'status' => $postStatus,
        'immutable' => $versionStatus === 'published',
    ], 'Feed post saved.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'feed.post_action_failed', 'Feed post action failed.', ['action' => $action], (int) $user['id']);
    mg_fail('Unable to save the feed post.', 500);
}
