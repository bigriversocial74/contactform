<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $user = mg_require_permission('catalog.products.view');
    $stmt = mg_db()->prepare(
        'SELECT p.public_id, p.product_type, p.slug, p.status, p.published_at, p.archived_at,
                v.public_id AS version_id, v.version_number, v.title, v.description,
                v.unit_value_cents, v.currency, v.version_status, p.created_at, p.updated_at
         FROM catalog_products p
         LEFT JOIN catalog_product_versions v ON v.id = p.current_version_id
         WHERE p.merchant_user_id = ?
         ORDER BY p.updated_at DESC, p.id DESC'
    );
    $stmt->execute([(int) $user['id']]);
    mg_ok(['products' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    mg_fail('Method not allowed.', 405);
}

$input = mg_input();
$action = trim((string) ($input['action'] ?? 'save_draft'));

if ($action === 'publish') {
    $user = mg_require_permission('catalog.products.publish');
} else {
    $user = mg_require_permission('catalog.products.manage');
}
mg_require_csrf_for_write($input);

$pdo = mg_db();

try {
    $pdo->beginTransaction();

    if ($action === 'save_draft') {
        $productId = trim((string) ($input['id'] ?? ''));
        $productType = trim((string) ($input['product_type'] ?? 'gift'));
        $allowedTypes = ['gift','prize','reward','voucher','entitlement','reservation','credit','digital_product','other'];
        if (!in_array($productType, $allowedTypes, true)) {
            mg_fail('Invalid product type.', 422);
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) {
            mg_fail('Invalid product title.', 422);
        }
        $description = trim((string) ($input['description'] ?? '')) ?: null;
        $slug = mg_catalog_slug((string) ($input['slug'] ?? $title));
        $unitValue = max(0, (int) ($input['unit_value_cents'] ?? 0));
        $currency = strtoupper(substr(trim((string) ($input['currency'] ?? 'USD')), 0, 3));
        $expiration = mg_catalog_json($input['expiration_policy'] ?? null);
        $terms = mg_catalog_json($input['terms'] ?? null);
        $fulfillment = mg_catalog_json($input['fulfillment'] ?? null);
        $metadata = mg_catalog_json($input['metadata'] ?? null);

        if ($productId === '') {
            $activeProducts = $pdo->prepare("SELECT COUNT(*) FROM catalog_products WHERE merchant_user_id=? AND status<>'archived'");
            $activeProducts->execute([(int) $user['id']]);
            mg_package_require_limit_available($pdo, $user, 'max_microgifts', (int) $activeProducts->fetchColumn(), 'Product limit reached.');

            $productId = mg_catalog_uuid();
            $pdo->prepare(
                "INSERT INTO catalog_products
                 (public_id, merchant_user_id, product_type, slug, status, created_by_user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'draft', ?, NOW(), NOW())"
            )->execute([$productId, (int) $user['id'], $productType, $slug, (int) $user['id']]);
            $productDbId = (int) $pdo->lastInsertId();
            $versionNumber = 1;
        } else {
            $product = mg_catalog_product_for_update($pdo, (int) $user['id'], $productId);
            if ((string) $product['status'] === 'archived') {
                mg_fail('Archived products cannot be edited.', 409);
            }
            $productDbId = (int) $product['id'];
            $nextStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM catalog_product_versions WHERE product_id = ?');
            $nextStmt->execute([$productDbId]);
            $versionNumber = (int) $nextStmt->fetchColumn();
            $pdo->prepare('UPDATE catalog_products SET product_type = ?, slug = ?, status = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$productType, $slug, 'draft', $productDbId]);
        }

        $versionId = mg_catalog_uuid();
        $checksum = mg_catalog_version_checksum([
            'title' => $title,
            'description' => $description,
            'unit_value_cents' => $unitValue,
            'currency' => $currency,
            'expiration_policy_json' => $expiration,
            'terms_json' => $terms,
            'fulfillment_json' => $fulfillment,
            'metadata_json' => $metadata,
        ]);

        $pdo->prepare(
            "INSERT INTO catalog_product_versions
             (public_id, product_id, version_number, version_status, title, description, unit_value_cents,
              currency, expiration_policy_json, terms_json, fulfillment_json, metadata_json, checksum,
              created_by_user_id, created_at)
             VALUES (?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([
            $versionId, $productDbId, $versionNumber, $title, $description, $unitValue, $currency,
            $expiration, $terms, $fulfillment, $metadata, $checksum, (int) $user['id'],
        ]);
        $versionDbId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE catalog_products SET current_version_id = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$versionDbId, $productDbId]);

        $pdo->commit();
        mg_audit('catalog.product_draft_saved', 'catalog_product', ['product_id' => $productId, 'version_id' => $versionId], (int) $user['id']);
        mg_ok(['product_id' => $productId, 'version_id' => $versionId, 'version_number' => $versionNumber], 'Product draft saved.', 201);
    }

    if ($action === 'publish') {
        $productId = trim((string) ($input['id'] ?? ''));
        $product = mg_catalog_product_for_update($pdo, (int) $user['id'], $productId);
        if (empty($product['current_version_id'])) {
            mg_fail('Product has no version to publish.', 409);
        }

        $versionStmt = $pdo->prepare('SELECT * FROM catalog_product_versions WHERE id = ? AND product_id = ? LIMIT 1 FOR UPDATE');
        $versionStmt->execute([(int) $product['current_version_id'], (int) $product['id']]);
        $version = $versionStmt->fetch();
        if (!$version || (string) $version['version_status'] !== 'draft') {
            mg_fail('Only a draft version can be published.', 409);
        }

        $pdo->prepare("UPDATE catalog_product_versions SET version_status = 'published', published_at = NOW() WHERE id = ?")
            ->execute([(int) $version['id']]);
        $pdo->prepare("UPDATE catalog_product_versions SET version_status = 'retired' WHERE product_id = ? AND id <> ? AND version_status = 'published'")
            ->execute([(int) $product['id'], (int) $version['id']]);
        $pdo->prepare("UPDATE catalog_products SET status = 'published', published_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([(int) $product['id']]);

        $templateId = mg_catalog_uuid();
        $itemType = (string) $product['product_type'] === 'digital_product' ? 'entitlement' : (string) $product['product_type'];
        if (!in_array($itemType, ['gift','prize','reward','voucher','entitlement','reservation','credit','other'], true)) {
            $itemType = 'other';
        }
        $pdo->prepare(
            "INSERT INTO catalog_pppm_templates
             (public_id, product_version_id, item_type, default_funding_type, issuance_defaults_json, status, created_at, updated_at)
             VALUES (?, ?, ?, 'other', ?, 'active', NOW(), NOW())"
        )->execute([
            $templateId,
            (int) $version['id'],
            $itemType,
            json_encode([
                'title' => $version['title'],
                'description' => $version['description'],
                'unit_value_cents' => (int) $version['unit_value_cents'],
                'currency' => $version['currency'],
                'terms' => $version['terms_json'] ? json_decode((string) $version['terms_json'], true) : null,
                'expiration_policy' => $version['expiration_policy_json'] ? json_decode((string) $version['expiration_policy_json'], true) : null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $pdo->commit();
        mg_audit('catalog.product_published', 'catalog_product', ['product_id' => $productId, 'version_id' => $version['public_id']], (int) $user['id']);
        mg_ok(['product_id' => $productId, 'version_id' => $version['public_id'], 'pppm_template_id' => $templateId, 'status' => 'published'], 'Product published.');
    }

    if ($action === 'archive') {
        $productId = trim((string) ($input['id'] ?? ''));
        $product = mg_catalog_product_for_update($pdo, (int) $user['id'], $productId);
        $pdo->prepare("UPDATE catalog_products SET status = 'archived', archived_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([(int) $product['id']]);
        $pdo->prepare("UPDATE catalog_pppm_templates t INNER JOIN catalog_product_versions v ON v.id = t.product_version_id SET t.status = 'retired', t.updated_at = NOW() WHERE v.product_id = ?")
            ->execute([(int) $product['id']]);
        $pdo->commit();
        mg_audit('catalog.product_archived', 'catalog_product', ['product_id' => $productId], (int) $user['id']);
        mg_ok(['product_id' => $productId, 'status' => 'archived'], 'Product archived.');
    }

    mg_fail('Invalid catalog action.', 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'catalog.product_action_failed', 'Catalog product action failed.', ['action' => $action], (int) $user['id']);
    mg_fail('Unable to process the catalog product.', 500);
}
