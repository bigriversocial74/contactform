<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';
require_once dirname(__DIR__) . '/merchant/_storefront.php';
require_once dirname(__DIR__) . '/feed/_feed.php';
require_once dirname(__DIR__) . '/microgifts/_engine.php';

function mg_catalog_product_type_from_payload(string $builderType, array $payload): string
{
    $category = strtolower(trim((string)($payload['product_category'] ?? '')));
    $category = preg_replace('/\s+/u', ' ', $category) ?? '';
    $mapped = match ($category) {
        'prepaid gift', 'gift' => 'gift',
        'voucher' => 'voucher',
        'local reward', 'workplace perk', 'reward' => 'reward',
        'digital product' => 'digital_product',
        'prize' => 'prize',
        'credit' => 'credit',
        default => null,
    };
    if ($mapped !== null) return $mapped;

    return match ($builderType) {
        'greeting_card', 'multimedia_greeting_card' => 'gift',
        'simple_collab' => 'reward',
        default => 'voucher',
    };
}

function mg_catalog_pppm_item_type(string $productType): string
{
    return in_array($productType, ['gift','prize','reward','voucher','entitlement','reservation','credit'], true)
        ? $productType
        : 'other';
}

function mg_catalog_feed_post_type_from_builder(string $builderType): string
{
    return match ($builderType) {
        'greeting_card' => 'greeting_card',
        'multimedia_greeting_card' => 'multimedia_card',
        'simple_collab' => 'collab',
        default => 'simple',
    };
}

function mg_catalog_merchant_identity(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare(
        "SELECT u.display_name AS user_display_name,u.full_name,
                mw.display_name AS workspace_name,mw.status AS workspace_status,
                pp.public_id AS profile_id,pp.slug AS profile_slug,pp.display_name AS profile_name,
                pp.headline AS profile_headline,pp.visibility AS profile_visibility,pp.status AS profile_status
         FROM users u
         LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=u.id
         LEFT JOIN public_profiles pp ON pp.user_id=u.id
         WHERE u.id=? LIMIT 1"
    );
    $stmt->execute([$merchantUserId]);
    $identity = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$identity) throw new RuntimeException('Merchant identity is unavailable.');
    return $identity;
}

function mg_catalog_require_public_merchant_profile(PDO $pdo, int $merchantUserId): array
{
    $identity = mg_catalog_merchant_identity($pdo, $merchantUserId);
    if ((string)($identity['profile_status'] ?? '') !== 'active'
        || !in_array((string)($identity['profile_visibility'] ?? ''), ['public','unlisted'], true)) {
        throw new RuntimeException('Complete and publish the merchant profile before publishing a public voucher.');
    }
    return $identity;
}

function mg_catalog_merchant_locations(PDO $pdo, int $merchantUserId): array
{
    $stmt = $pdo->prepare(
        "SELECT ml.id,ml.public_id,ml.name,ml.address_line1,ml.city,ml.region,ml.postal_code,
                ml.country_code,ml.is_primary
         FROM merchant_locations ml
         INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id
         WHERE mw.merchant_user_id=? AND ml.status='active'
         ORDER BY ml.is_primary DESC,ml.name ASC,ml.id ASC"
    );
    $stmt->execute([$merchantUserId]);
    return array_map(static fn(array $row): array => [
        'id'=>(int)$row['id'],
        'public_id'=>(string)$row['public_id'],
        'name'=>(string)$row['name'],
        'address_line1'=>$row['address_line1'] !== null ? (string)$row['address_line1'] : null,
        'city'=>$row['city'] !== null ? (string)$row['city'] : null,
        'region'=>$row['region'] !== null ? (string)$row['region'] : null,
        'postal_code'=>$row['postal_code'] !== null ? (string)$row['postal_code'] : null,
        'country_code'=>(string)$row['country_code'],
        'is_primary'=>(bool)$row['is_primary'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_catalog_selected_locations(PDO $pdo, int $merchantUserId, array $payload): array
{
    $available = mg_catalog_merchant_locations($pdo, $merchantUserId);
    if ($available === []) {
        throw new RuntimeException('Add an active merchant location before publishing a public voucher.');
    }

    if (!empty($payload['all_locations'])) return $available;

    $requested = $payload['location_ids'] ?? [];
    if (!is_array($requested)) throw new InvalidArgumentException('Invalid merchant locations.');
    $requested = array_values(array_unique(array_filter(array_map(
        static fn(mixed $value): string => trim((string)$value),
        $requested
    ), static fn(string $value): bool => $value !== '')));

    if ($requested === []) {
        $primary = array_values(array_filter($available, static fn(array $row): bool => $row['is_primary']));
        return $primary !== [] ? [$primary[0]] : [$available[0]];
    }

    $byPublic = [];
    foreach ($available as $location) $byPublic[$location['public_id']] = $location;
    $selected = [];
    foreach ($requested as $publicId) {
        if (!isset($byPublic[$publicId])) throw new RuntimeException('One or more selected merchant locations are unavailable.');
        $selected[] = $byPublic[$publicId];
    }
    return $selected;
}

function mg_catalog_unique_storefront_slug(PDO $pdo, string $base, int $merchantUserId): string
{
    $base = mg_catalog_slug($base !== '' ? $base : 'merchant-store');
    $candidate = $base;
    $suffix = 1;
    $stmt = $pdo->prepare('SELECT merchant_user_id FROM merchant_storefronts WHERE slug=? LIMIT 1');
    while (true) {
        $stmt->execute([$candidate]);
        $owner = $stmt->fetchColumn();
        if (!$owner || (int)$owner === $merchantUserId) return $candidate;
        $suffix++;
        $candidate = substr($base, 0, 145) . '-' . $suffix;
    }
}

function mg_catalog_publish_to_storefront(PDO $pdo, int $merchantUserId, int $productDbId, array $identity): array
{
    $storeStmt = $pdo->prepare('SELECT * FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1 FOR UPDATE');
    $storeStmt->execute([$merchantUserId]);
    $store = $storeStmt->fetch(PDO::FETCH_ASSOC);

    $displayName = trim((string)($identity['workspace_name'] ?? $identity['profile_name'] ?? $identity['user_display_name'] ?? $identity['full_name'] ?? 'Merchant'));
    if ($displayName === '') $displayName = 'Merchant';
    $headline = trim((string)($identity['profile_headline'] ?? '')) ?: 'Local vouchers and gifts';

    if (!$store) {
        $publicId = mg_catalog_uuid();
        $slug = mg_catalog_unique_storefront_slug($pdo, (string)($identity['profile_slug'] ?? $displayName), $merchantUserId);
        $pdo->prepare(
            "INSERT INTO merchant_storefronts
             (public_id,merchant_user_id,slug,display_name,headline,description,status,published_at,created_at,updated_at)
             VALUES (?,?,?,?,?,'Published Microgifter vouchers.','published',NOW(),NOW(),NOW())"
        )->execute([$publicId,$merchantUserId,$slug,$displayName,$headline]);
        $store = [
            'id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId,'slug'=>$slug,
            'display_name'=>$displayName,'headline'=>$headline,'description'=>'Published Microgifter vouchers.',
            'logo_asset_id'=>null,'cover_asset_id'=>null,'status'=>'published',
        ];
    }

    if ((string)($store['status'] ?? '') === 'suspended') {
        throw new RuntimeException('Suspended storefronts cannot receive published products.');
    }

    $publishedStmt = $pdo->prepare(
        'SELECT r.* FROM merchant_storefront_states s
         INNER JOIN merchant_storefront_revisions r ON r.id=s.published_revision_id
         WHERE s.storefront_id=? LIMIT 1 FOR UPDATE'
    );
    $publishedStmt->execute([(int)$store['id']]);
    $published = $publishedStmt->fetch(PDO::FETCH_ASSOC);

    if ($published) {
        $existsStmt = $pdo->prepare('SELECT 1 FROM merchant_storefront_revision_products WHERE storefront_revision_id=? AND catalog_product_id=? AND visibility=\'visible\' LIMIT 1');
        $existsStmt->execute([(int)$published['id'],$productDbId]);
        if ($existsStmt->fetchColumn()) {
            return [
                'storefront_id'=>(string)$store['public_id'],
                'revision_id'=>(string)$published['public_id'],
                'url'=>'/store.php?s=' . rawurlencode((string)$store['slug']),
                'duplicate'=>true,
            ];
        }
    }

    $products = [];
    if ($published) {
        $productStmt = $pdo->prepare('SELECT catalog_product_id,sort_order,is_featured,visibility FROM merchant_storefront_revision_products WHERE storefront_revision_id=? ORDER BY sort_order,id');
        $productStmt->execute([(int)$published['id']]);
        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $maxOrder = -1;
    foreach ($products as $product) $maxOrder = max($maxOrder, (int)$product['sort_order']);
    $products[] = ['catalog_product_id'=>$productDbId,'sort_order'=>$maxOrder + 1,'is_featured'=>0,'visibility'=>'visible'];

    $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM merchant_storefront_revisions WHERE storefront_id=?');
    $versionStmt->execute([(int)$store['id']]);
    $versionNumber = (int)$versionStmt->fetchColumn();
    $revisionPublicId = mg_catalog_uuid();
    $revisionData = $published ?: $store;
    $checksum = mg_storefront_checksum([
        $revisionData['display_name'] ?? $displayName,
        $revisionData['headline'] ?? $headline,
        $revisionData['description'] ?? null,
        $revisionData['logo_asset_id'] ?? null,
        $revisionData['cover_asset_id'] ?? null,
        $revisionData['contact_json'] ?? null,
        $revisionData['theme_json'] ?? null,
        $products,
    ]);

    $pdo->prepare(
        "INSERT INTO merchant_storefront_revisions
         (public_id,storefront_id,version_number,revision_status,display_name,headline,description,
          logo_asset_id,cover_asset_id,contact_json,theme_json,checksum,published_at,created_by_user_id,created_at,updated_at)
         VALUES (?,?,?,'published',?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())"
    )->execute([
        $revisionPublicId,(int)$store['id'],$versionNumber,
        (string)($revisionData['display_name'] ?? $displayName),
        ($revisionData['headline'] ?? $headline) ?: null,
        ($revisionData['description'] ?? null) ?: null,
        $revisionData['logo_asset_id'] ?? null,
        $revisionData['cover_asset_id'] ?? null,
        $revisionData['contact_json'] ?? null,
        $revisionData['theme_json'] ?? null,
        $checksum,$merchantUserId,
    ]);
    $revisionId = (int)$pdo->lastInsertId();

    $insertProduct = $pdo->prepare(
        'INSERT INTO merchant_storefront_revision_products
         (storefront_revision_id,catalog_product_id,sort_order,is_featured,visibility,created_at,updated_at)
         VALUES (?,?,?,?,?,NOW(),NOW())'
    );
    foreach ($products as $product) {
        $insertProduct->execute([
            $revisionId,(int)$product['catalog_product_id'],(int)$product['sort_order'],
            !empty($product['is_featured']) ? 1 : 0,
            (string)($product['visibility'] ?? 'visible') === 'hidden' ? 'hidden' : 'visible',
        ]);
    }

    if ($published) {
        $pdo->prepare("UPDATE merchant_storefront_revisions SET revision_status='retired',updated_at=NOW() WHERE id=?")
            ->execute([(int)$published['id']]);
    }
    $pdo->prepare(
        'INSERT INTO merchant_storefront_states (storefront_id,published_revision_id,updated_at)
         VALUES (?,?,NOW())
         ON DUPLICATE KEY UPDATE published_revision_id=VALUES(published_revision_id),updated_at=NOW()'
    )->execute([(int)$store['id'],$revisionId]);
    $pdo->prepare(
        "UPDATE merchant_storefronts SET display_name=?,headline=?,description=?,logo_asset_id=?,cover_asset_id=?,
         status='published',published_at=COALESCE(published_at,NOW()),updated_at=NOW() WHERE id=?"
    )->execute([
        (string)($revisionData['display_name'] ?? $displayName),
        ($revisionData['headline'] ?? $headline) ?: null,
        ($revisionData['description'] ?? null) ?: null,
        $revisionData['logo_asset_id'] ?? null,
        $revisionData['cover_asset_id'] ?? null,
        (int)$store['id'],
    ]);

    return [
        'storefront_id'=>(string)$store['public_id'],
        'revision_id'=>$revisionPublicId,
        'url'=>'/store.php?s=' . rawurlencode((string)$store['slug']),
        'duplicate'=>false,
    ];
}

function mg_catalog_publish_feed_post(PDO $pdo, int $merchantUserId, array $product, array $version, string $builderType, array $payload): array
{
    mg_catalog_require_public_merchant_profile($pdo, $merchantUserId);

    $postStmt = $pdo->prepare(
        "SELECT * FROM feed_posts
         WHERE merchant_user_id=? AND catalog_product_id=? AND status NOT IN ('retired')
         ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    $postStmt->execute([$merchantUserId,(int)$product['id']]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);

    if ($post && !empty($post['current_version_id'])) {
        $sameStmt = $pdo->prepare('SELECT 1 FROM feed_post_versions WHERE id=? AND catalog_product_version_id=? AND version_status=\'published\' LIMIT 1');
        $sameStmt->execute([(int)$post['current_version_id'],(int)$version['id']]);
        if ($sameStmt->fetchColumn()) {
            return [
                'post_id'=>(string)$post['public_id'],
                'url'=>'/feed.php?post=' . rawurlencode((string)$post['public_id']),
                'duplicate'=>true,
            ];
        }
    }

    $postType = mg_catalog_feed_post_type_from_builder($builderType);
    $headline = trim((string)($payload['headline'] ?? $version['title'] ?? '')) ?: (string)$version['title'];
    $body = trim((string)($payload['message'] ?? $version['description'] ?? '')) ?: null;
    $mediaJson = json_encode([], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    if (!$post || in_array((string)($post['status'] ?? ''), ['retired'], true)) {
        $postPublicId = mg_feed_uuid();
        $pdo->prepare(
            "INSERT INTO feed_posts
             (public_id,merchant_user_id,catalog_product_id,current_version_id,post_type,headline,body,media_json,
              visibility,status,moderation_status,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,NULL,?,?,?,?,'public','draft','clear',?,NOW(),NOW())"
        )->execute([$postPublicId,$merchantUserId,(int)$product['id'],$postType,$headline,$body,$mediaJson,$merchantUserId]);
        $post = ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$postPublicId];
    } else {
        $pdo->prepare(
            "UPDATE feed_posts SET post_type=?,headline=?,body=?,media_json=?,visibility='public',
             status='draft',moderation_status='clear',archived_at=NULL,updated_at=NOW() WHERE id=?"
        )->execute([$postType,$headline,$body,$mediaJson,(int)$post['id']]);
    }

    $nextStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM feed_post_versions WHERE feed_post_id=?');
    $nextStmt->execute([(int)$post['id']]);
    $versionNumber = (int)$nextStmt->fetchColumn();
    $feedVersionPublicId = mg_feed_uuid();
    $offer = json_encode([
        'value_cents'=>(int)$version['unit_value_cents'],
        'currency'=>(string)$version['currency'],
        'offer'=>$payload['offer'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $presentation = json_encode([
        'builder_type'=>$builderType,
        'catalog_product_version_id'=>(string)$version['public_id'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $ctaUrl = '/product.php?p=' . rawurlencode((string)$product['slug']);
    $checksum = hash('sha256', json_encode([
        'product_version'=>(string)$version['public_id'],
        'post_type'=>$postType,'headline'=>$headline,'body'=>$body,
        'cta_url'=>$ctaUrl,'offer'=>$offer,'presentation'=>$presentation,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    $pdo->prepare(
        "INSERT INTO feed_post_versions
         (public_id,feed_post_id,catalog_product_version_id,version_number,version_status,headline,caption,
          cta_label,cta_url,offer_snapshot_json,presentation_json,checksum,immutable_at,published_at,
          created_by_user_id,created_at)
         VALUES (?,?,?,?,'published',?,?, 'View voucher',?,?,?,?,NOW(),NOW(),?,NOW())"
    )->execute([
        $feedVersionPublicId,(int)$post['id'],(int)$version['id'],$versionNumber,
        $headline,$body,$ctaUrl,$offer,$presentation,$checksum,$merchantUserId,
    ]);
    $feedVersionId = (int)$pdo->lastInsertId();

    $assetStmt = $pdo->prepare(
        "SELECT a.id,a.public_id,a.asset_type,pva.role
         FROM catalog_product_version_assets pva
         INNER JOIN catalog_assets a ON a.id=pva.asset_id AND a.status='ready'
         WHERE pva.product_version_id=? ORDER BY pva.sort_order,pva.id"
    );
    $assetStmt->execute([(int)$version['id']]);
    $elementInsert = $pdo->prepare(
        'INSERT INTO feed_post_elements
         (public_id,feed_post_version_id,element_type,asset_id,sort_order,content_json,checksum,created_at)
         VALUES (?,?,?,?,?,?,?,NOW())'
    );
    foreach ($assetStmt->fetchAll(PDO::FETCH_ASSOC) as $index => $asset) {
        $elementType = match ((string)$asset['asset_type']) {
            'image' => 'image', 'audio' => 'audio', 'video' => 'video', default => 'other',
        };
        $content = json_encode(['role'=>(string)$asset['role']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $elementInsert->execute([
            mg_feed_uuid(),$feedVersionId,$elementType,(int)$asset['id'],$index,$content,
            hash('sha256',$feedVersionPublicId . '|' . $asset['public_id'] . '|' . $index),
        ]);
    }

    $pdo->prepare("UPDATE feed_post_versions SET version_status='retired' WHERE feed_post_id=? AND id<>? AND version_status='published'")
        ->execute([(int)$post['id'],$feedVersionId]);
    $pdo->prepare(
        "UPDATE feed_posts SET current_version_id=?,status='published',visibility='public',headline=?,body=?,updated_at=NOW() WHERE id=?"
    )->execute([$feedVersionId,$headline,$body,(int)$post['id']]);

    return [
        'post_id'=>(string)$post['public_id'],
        'version_id'=>$feedVersionPublicId,
        'url'=>'/feed.php?post=' . rawurlencode((string)$post['public_id']),
        'duplicate'=>false,
    ];
}

function mg_catalog_publish_locations(PDO $pdo, int $productVersionId, array $locations): array
{
    $pdo->prepare('DELETE FROM catalog_product_version_locations WHERE product_version_id=?')->execute([$productVersionId]);
    $insert = $pdo->prepare(
        "INSERT INTO catalog_product_version_locations
         (product_version_id,merchant_location_id,availability_status,is_primary,created_at,updated_at)
         VALUES (?,?,'available',?,NOW(),NOW())"
    );
    foreach ($locations as $location) {
        $insert->execute([$productVersionId,(int)$location['id'],!empty($location['is_primary']) ? 1 : 0]);
    }
    return [
        'count'=>count($locations),
        'locations'=>array_map(static fn(array $location): array => [
            'id'=>$location['public_id'],'name'=>$location['name'],'city'=>$location['city'],
            'region'=>$location['region'],'is_primary'=>$location['is_primary'],
        ], $locations),
    ];
}

function mg_catalog_publish_microgift_definition(
    PDO $pdo,
    int $merchantUserId,
    array $product,
    array $version,
    string $builderType,
    array $payload,
    array $locations
): array {
    $existingStmt = $pdo->prepare(
        "SELECT cpt.*,mv.public_id AS microgift_version_public_id,mt.public_id AS microgift_template_public_id,
                mv.status AS microgift_version_status,mt.status AS microgift_template_status,mt.owner_user_id
         FROM catalog_pppm_templates cpt
         LEFT JOIN microgift_template_versions mv ON mv.id=cpt.microgift_template_version_id
         LEFT JOIN microgift_templates mt ON mt.id=mv.template_id
         WHERE cpt.product_version_id=? LIMIT 1 FOR UPDATE"
    );
    $existingStmt->execute([(int)$version['id']]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing
        && (int)($existing['owner_user_id'] ?? 0) === $merchantUserId
        && (string)($existing['microgift_version_status'] ?? '') === 'published'
        && (string)($existing['microgift_template_status'] ?? '') === 'active') {
        return [
            'pppm_template_id'=>(string)$existing['public_id'],
            'microgift_template_id'=>(string)$existing['microgift_template_public_id'],
            'microgift_template_version_id'=>(string)$existing['microgift_version_public_id'],
            'duplicate'=>true,
        ];
    }

    $canonicalStmt = $pdo->prepare(
        "SELECT mv.id,mv.public_id,mt.public_id AS template_public_id
         FROM microgift_template_versions mv
         INNER JOIN microgift_templates mt ON mt.id=mv.template_id
         WHERE mv.product_version_id=? AND mv.status='published' AND mt.owner_user_id=? AND mt.status='active'
         ORDER BY mv.id DESC LIMIT 1 FOR UPDATE"
    );
    $canonicalStmt->execute([(int)$version['id'],$merchantUserId]);
    $canonical = $canonicalStmt->fetch(PDO::FETCH_ASSOC);

    if (!$canonical) {
        $template = mg_microgift_create_template($pdo,$merchantUserId,[
            'owner_type'=>'merchant','name'=>(string)$version['title'],'gift_type'=>'product',
            'visibility'=>'public','default_currency'=>(string)$version['currency'],
            'slug'=>(string)$product['slug'],'description'=>(string)($version['description'] ?? ''),
        ]);
        $created = mg_microgift_create_version($pdo,$merchantUserId,(string)$template['template_id'],[
            'title'=>(string)$version['title'],'description'=>(string)($version['description'] ?? ''),
            'currency'=>(string)$version['currency'],'face_value_cents'=>(int)$version['unit_value_cents'],
            'product_id'=>(int)$product['id'],'product_version_id'=>(int)$version['id'],
            'recipient_policy'=>'purchaser','claim_policy'=>['mode'=>'purchaser_owned'],
            'redemption_policy'=>['mode'=>'merchant_location'],
            'location_policy'=>[
                'mode'=>'selected_locations',
                'location_ids'=>array_column($locations,'public_id'),
            ],
            'expiration_policy'=>$version['expiration_policy_json'] ? (json_decode((string)$version['expiration_policy_json'],true) ?: []) : [],
            'terms_snapshot'=>$version['terms_json'] ? (json_decode((string)$version['terms_json'],true) ?: []) : [],
            'future_demand_metadata'=>[
                'source'=>'catalog_publish','catalog_product_id'=>(string)$product['public_id'],
                'builder_type'=>$builderType,
            ],
        ]);
        $published = mg_microgift_publish_version($pdo,$merchantUserId,(string)$created['version_id']);
        $idStmt = $pdo->prepare('SELECT id FROM microgift_template_versions WHERE public_id=? LIMIT 1');
        $idStmt->execute([(string)$published['version_id']]);
        $canonical = [
            'id'=>(int)$idStmt->fetchColumn(),
            'public_id'=>(string)$published['version_id'],
            'template_public_id'=>(string)$published['template_id'],
        ];
    }

    $itemType = mg_catalog_pppm_item_type((string)$product['product_type']);
    $defaults = json_encode([
        'title'=>(string)$version['title'],'description'=>$version['description'],
        'value_cents'=>(int)$version['unit_value_cents'],'currency'=>(string)$version['currency'],
        'builder_type'=>$builderType,'location_ids'=>array_column($locations,'public_id'),
        'demo'=>!empty($payload['demo']),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    if ($existing) {
        $pdo->prepare(
            "UPDATE catalog_pppm_templates SET microgift_template_version_id=?,item_type=?,
             default_funding_type='customer_purchase',issuance_defaults_json=?,status='active',updated_at=NOW() WHERE id=?"
        )->execute([(int)$canonical['id'],$itemType,$defaults,(int)$existing['id']]);
        $pppmPublicId = (string)$existing['public_id'];
    } else {
        $pppmPublicId = mg_catalog_uuid();
        $pdo->prepare(
            "INSERT INTO catalog_pppm_templates
             (public_id,product_version_id,microgift_template_version_id,item_type,default_funding_type,
              issuance_defaults_json,status,created_at,updated_at)
             VALUES (?,?,?,?, 'customer_purchase',?,'active',NOW(),NOW())"
        )->execute([$pppmPublicId,(int)$version['id'],(int)$canonical['id'],$itemType,$defaults]);
    }

    return [
        'pppm_template_id'=>$pppmPublicId,
        'microgift_template_id'=>(string)$canonical['template_public_id'],
        'microgift_template_version_id'=>(string)$canonical['public_id'],
        'duplicate'=>false,
    ];
}

function mg_catalog_publish_distribution(
    PDO $pdo,
    int $merchantUserId,
    array $product,
    array $version,
    string $builderType,
    array $payload
): array {
    $identity = mg_catalog_require_public_merchant_profile($pdo,$merchantUserId);
    $locations = mg_catalog_selected_locations($pdo,$merchantUserId,$payload);
    $locationResult = mg_catalog_publish_locations($pdo,(int)$version['id'],$locations);
    $definition = mg_catalog_publish_microgift_definition(
        $pdo,$merchantUserId,$product,$version,$builderType,$payload,$locations
    );
    $store = mg_catalog_publish_to_storefront($pdo,$merchantUserId,(int)$product['id'],$identity);
    $feed = mg_catalog_publish_feed_post($pdo,$merchantUserId,$product,$version,$builderType,$payload);

    return [
        'definition'=>$definition,
        'storefront'=>$store,
        'feed'=>$feed,
        'locations'=>$locationResult,
        'product_url'=>'/product.php?p=' . rawurlencode((string)$product['slug']),
        'store_url'=>$store['url'],
        'feed_url'=>$feed['url'],
        'discovery_url'=>'/discover.php?category=' . rawurlencode((string)$product['product_type']),
    ];
}
