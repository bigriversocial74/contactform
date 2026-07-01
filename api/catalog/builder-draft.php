<?php
declare(strict_types=1);

require_once __DIR__ . '/_publish_distribution.php';
require_once __DIR__ . '/_builder_product_types.php';
require_once __DIR__ . '/_public_identity.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('catalog.products.manage');
$pdo = mg_db();

function mg_builder_payload(mixed $value): array
{
    if (!is_array($value)) mg_fail('Invalid builder payload.',422);
    $json = json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > 524288) mg_fail('Builder payload is too large.',422);
    return $value;
}

function mg_builder_visibility(mixed $value): string
{
    $visibility = strtolower(trim((string)$value));
    if ($visibility === '') $visibility = 'public';
    if (!in_array($visibility,['public','unlisted','private'],true)) {
        mg_fail('Choose a valid product visibility.',422);
    }
    return $visibility;
}

function mg_builder_asset_map(mixed $value): array
{
    if ($value === null || $value === '') return [];
    if (!is_array($value)) mg_fail('Invalid asset map.',422);
    $allowed = ['thumbnail','cover','inside_cover','audio','video'];
    $clean = [];
    foreach ($value as $role => $assetId) {
        if (!in_array((string)$role,$allowed,true)) continue;
        $id = trim((string)$assetId);
        if ($id !== '') $clean[(string)$role] = $id;
    }
    return $clean;
}

function mg_builder_safe_preview_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 600 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        $parts = parse_url($url);
        if ($parts !== false && !isset($parts['scheme']) && !isset($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])) return $url;
        return null;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return null;
    if (!in_array(strtolower((string)$parts['scheme']), ['http','https'], true)) return null;
    if (isset($parts['user']) || isset($parts['pass'])) return null;
    return $url;
}

function mg_builder_merchant_preview(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT u.display_name AS user_display_name,u.full_name,
                mw.display_name AS workspace_name,
                pp.display_name AS profile_name,pp.avatar_url AS profile_avatar_url
         FROM users u
         LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=u.id
         LEFT JOIN public_profiles pp ON pp.user_id=u.id
         WHERE u.id=? LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $displayName = trim((string)($row['workspace_name'] ?? ''));
    if ($displayName === '') $displayName = trim((string)($row['profile_name'] ?? ''));
    if ($displayName === '') $displayName = trim((string)($row['user_display_name'] ?? ''));
    if ($displayName === '') $displayName = trim((string)($row['full_name'] ?? ''));
    if ($displayName === '') $displayName = 'Your business';
    return [
        'display_name'=>$displayName,
        'avatar_url'=>mg_builder_safe_preview_url($row['profile_avatar_url'] ?? null),
        'initial'=>strtoupper(substr($displayName,0,1)),
    ];
}

function mg_builder_context(PDO $pdo, int $userId): array
{
    return [
        'locations'=>mg_catalog_merchant_locations($pdo,$userId),
        'merchant'=>mg_builder_merchant_preview($pdo,$userId),
        'publish_requires'=>[
            'public_profile'=>true,
            'active_merchant_location'=>true,
            'visibility'=>'public',
            'minimum_value_cents'=>1,
        ],
    ];
}

if ($method === 'GET') {
    $context = mg_builder_context($pdo,(int)$user['id']);
    $productId = trim((string)($_GET['id'] ?? ''));
    if ($productId === '') mg_ok(['draft'=>null] + $context);
    $stmt = $pdo->prepare(
        'SELECT p.public_id AS product_id,p.product_type,p.slug,p.status,
                d.public_id AS draft_id,d.builder_type,d.payload_json,d.asset_map_json,
                d.lock_version,d.updated_at
         FROM catalog_products p
         INNER JOIN catalog_builder_drafts d ON d.product_id=p.id
         WHERE p.public_id=? AND p.merchant_user_id=? LIMIT 1'
    );
    $stmt->execute([$productId,(int)$user['id']]);
    $row = $stmt->fetch();
    if (!$row) mg_fail('Builder draft not found.',404);
    mg_ok(['draft'=>[
        'product_id'=>$row['product_id'],
        'draft_id'=>$row['draft_id'],
        'builder_type'=>$row['builder_type'],
        'product_type'=>$row['product_type'],
        'slug'=>$row['slug'],
        'status'=>$row['status'],
        'payload'=>json_decode((string)$row['payload_json'],true) ?: [],
        'assets'=>$row['asset_map_json'] ? (json_decode((string)$row['asset_map_json'],true) ?: []) : [],
        'lock_version'=>(int)$row['lock_version'],
        'updated_at'=>$row['updated_at'],
    ]] + $context);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string)($input['action'] ?? 'save'));
try {
    $builderType = mg_builder_type(trim((string)($input['builder_type'] ?? 'simple_product')));
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(),422);
}
$payload = mg_builder_payload($input['payload'] ?? []);
$payload['visibility'] = mg_builder_visibility($payload['visibility'] ?? 'public');
$assetMap = mg_builder_asset_map($input['assets'] ?? []);
$productId = trim((string)($input['product_id'] ?? ''));
$lockVersion = max(0,(int)($input['lock_version'] ?? 0));
$title = trim((string)($payload['title'] ?? ''));
if ($title === '' || mb_strlen($title) > 160) mg_fail('Enter a product title before saving or publishing.',422);
$slug = mg_catalog_slug((string)($payload['slug'] ?? $title));
$productType = mg_catalog_product_type_from_payload($builderType,$payload);

try {
    $pdo->beginTransaction();
    $productStatus = 'draft';

    if ($productId === '') {
        mg_catalog_require_merchant_slug($pdo,(int)$user['id'],$slug);
        $productId = mg_catalog_uuid();
        $pdo->prepare(
            "INSERT INTO catalog_products
             (public_id,merchant_user_id,product_type,slug,status,created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,'draft',?,NOW(),NOW())"
        )->execute([$productId,(int)$user['id'],$productType,$slug,(int)$user['id']]);
        $productDbId = (int)$pdo->lastInsertId();
    } else {
        $product = mg_catalog_product_for_update($pdo,(int)$user['id'],$productId);
        $productStatus = (string)$product['status'];
        if ($productStatus === 'archived') mg_fail('Archived products cannot be edited.',409);
        $productDbId = (int)$product['id'];
        mg_catalog_require_merchant_slug($pdo,(int)$user['id'],$slug,$productDbId);
        if ($productStatus === 'draft') {
            $pdo->prepare('UPDATE catalog_products SET product_type=?,slug=?,updated_at=NOW() WHERE id=?')
                ->execute([$productType,$slug,$productDbId]);
        } else {
            $pdo->prepare('UPDATE catalog_products SET updated_at=NOW() WHERE id=?')->execute([$productDbId]);
        }
    }

    $existingStmt = $pdo->prepare('SELECT * FROM catalog_builder_drafts WHERE product_id=? LIMIT 1 FOR UPDATE');
    $existingStmt->execute([$productDbId]);
    $existing = $existingStmt->fetch();
    $payloadJson = json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $assetJson = $assetMap ? json_encode($assetMap,JSON_UNESCAPED_SLASHES) : null;

    if ($existing) {
        if ($lockVersion !== (int)$existing['lock_version']) mg_fail('This draft changed in another session. Reload before saving.',409);
        $nextLock = (int)$existing['lock_version'] + 1;
        $pdo->prepare(
            'UPDATE catalog_builder_drafts
             SET builder_type=?,payload_json=?,asset_map_json=?,lock_version=?,updated_by_user_id=?,updated_at=NOW()
             WHERE id=?'
        )->execute([$builderType,$payloadJson,$assetJson,$nextLock,(int)$user['id'],(int)$existing['id']]);
        $draftId = (string)$existing['public_id'];
    } else {
        $draftId = mg_catalog_uuid();
        $nextLock = 1;
        $pdo->prepare(
            'INSERT INTO catalog_builder_drafts
             (public_id,product_id,builder_type,payload_json,asset_map_json,lock_version,updated_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,1,?,NOW(),NOW())'
        )->execute([$draftId,$productDbId,$builderType,$payloadJson,$assetJson,(int)$user['id']]);
    }

    if ($action === 'save') {
        $pdo->commit();
        mg_audit('catalog.builder_saved','catalog_product',[
            'product_id'=>$productId,
            'draft_id'=>$draftId,
            'live_status_preserved'=>$productStatus === 'published',
            'visibility'=>$payload['visibility'],
        ],(int)$user['id']);
        mg_ok([
            'product_id'=>$productId,
            'draft_id'=>$draftId,
            'lock_version'=>$nextLock,
            'status'=>$productStatus,
            'visibility'=>$payload['visibility'],
            'has_draft_changes'=>true,
        ],'Product draft saved.');
    }

    if ($action !== 'publish') mg_fail('Invalid builder action.',422);
    mg_require_permission('catalog.products.publish');
    if ($payload['visibility'] !== 'public') {
        mg_fail('Set visibility to Public before publishing to your store, feed, and merchant locations.',422);
    }
    mg_catalog_require_global_published_slug($pdo,$slug,$productDbId);
    if (!empty($payload['demo'])) {
        mg_fail('Demo vouchers cannot be published as live merchant products.',422);
    }
    mg_builder_validate_publish_type($builderType,$payload,$assetMap);

    $nextVersionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM catalog_product_versions WHERE product_id=?');
    $nextVersionStmt->execute([$productDbId]);
    $versionNumber = (int)$nextVersionStmt->fetchColumn();
    $versionId = mg_catalog_uuid();
    $valueCents = max(0,(int)($payload['value_cents'] ?? 0));
    if ($valueCents < 1) mg_fail('Enter a voucher value before publishing.',422);
    $currency = strtoupper(substr((string)($payload['currency'] ?? 'USD'),0,3));
    if (!preg_match('/^[A-Z]{3}$/',$currency)) mg_fail('Invalid currency.',422);
    $description = trim((string)($payload['description'] ?? $payload['message'] ?? '')) ?: null;
    $terms = mg_catalog_json($payload['terms'] ?? null);
    $expiration = mg_catalog_json($payload['expiration_policy'] ?? null);
    $fulfillment = mg_catalog_json([
        'builder_type'=>$builderType,
        'claim_code_label'=>$payload['claim_code_label'] ?? null,
        'media_roles'=>array_keys($assetMap),
        'distribution'=>'store_feed_locations',
    ]);
    $metadata = mg_catalog_json($payload);
    $checksum = mg_catalog_version_checksum(['builder_type'=>$builderType,'payload'=>$payload,'assets'=>$assetMap]);

    $pdo->prepare(
        "INSERT INTO catalog_product_versions
         (public_id,product_id,version_number,version_status,title,description,unit_value_cents,
          currency,expiration_policy_json,terms_json,fulfillment_json,metadata_json,checksum,
          created_by_user_id,published_at,created_at)
         VALUES (?,?,?,'published',?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
    )->execute([
        $versionId,$productDbId,$versionNumber,$title,$description,$valueCents,$currency,
        $expiration,$terms,$fulfillment,$metadata,$checksum,(int)$user['id'],
    ]);
    $versionDbId = (int)$pdo->lastInsertId();

    $assetLookup = $pdo->prepare("SELECT id FROM catalog_assets WHERE public_id=? AND owner_user_id=? AND status='ready' LIMIT 1");
    $assetInsert = $pdo->prepare(
        'INSERT INTO catalog_product_version_assets (product_version_id,asset_id,role,sort_order,created_at) VALUES (?,?,?,?,NOW())'
    );
    $sort = 0;
    foreach ($assetMap as $role => $assetPublicId) {
        $assetLookup->execute([$assetPublicId,(int)$user['id']]);
        $assetDbId = $assetLookup->fetchColumn();
        if (!$assetDbId) mg_fail('One or more selected media assets are unavailable.',422);
        $assetInsert->execute([$versionDbId,(int)$assetDbId,$role,$sort++]);
    }

    $pdo->prepare("UPDATE catalog_product_versions SET version_status='retired' WHERE product_id=? AND id<>? AND version_status='published'")
        ->execute([$productDbId,$versionDbId]);
    $pdo->prepare("UPDATE catalog_products SET product_type=?,slug=?,current_version_id=?,status='published',published_at=NOW(),archived_at=NULL,updated_at=NOW() WHERE id=?")
        ->execute([$productType,$slug,$versionDbId,$productDbId]);

    $productRow = [
        'id'=>$productDbId,'public_id'=>$productId,'product_type'=>$productType,'slug'=>$slug,
    ];
    $versionRow = [
        'id'=>$versionDbId,'public_id'=>$versionId,'title'=>$title,'description'=>$description,
        'unit_value_cents'=>$valueCents,'currency'=>$currency,
        'expiration_policy_json'=>$expiration,'terms_json'=>$terms,
    ];
    $distribution = mg_catalog_publish_distribution(
        $pdo,(int)$user['id'],$productRow,$versionRow,$builderType,$payload
    );

    $pdo->commit();
    mg_audit('catalog.builder_published','catalog_product',[
        'product_id'=>$productId,
        'version_id'=>$versionId,
        'pppm_template_id'=>$distribution['definition']['pppm_template_id'],
        'microgift_template_version_id'=>$distribution['definition']['microgift_template_version_id'],
        'storefront_id'=>$distribution['storefront']['storefront_id'],
        'feed_post_id'=>$distribution['feed']['post_id'],
        'location_count'=>$distribution['locations']['count'],
        'visibility'=>$payload['visibility'],
    ],(int)$user['id']);
    mg_ok([
        'product_id'=>$productId,
        'draft_id'=>$draftId,
        'version_id'=>$versionId,
        'version_number'=>$versionNumber,
        'pppm_template_id'=>$distribution['definition']['pppm_template_id'],
        'microgift_template_id'=>$distribution['definition']['microgift_template_id'],
        'microgift_template_version_id'=>$distribution['definition']['microgift_template_version_id'],
        'storefront_id'=>$distribution['storefront']['storefront_id'],
        'feed_post_id'=>$distribution['feed']['post_id'],
        'locations'=>$distribution['locations']['locations'],
        'product_url'=>mg_catalog_public_product_url($productId,$slug),
        'store_url'=>$distribution['store_url'],
        'feed_url'=>$distribution['feed_url'],
        'discovery_url'=>$distribution['discovery_url'],
        'lock_version'=>$nextLock,
        'status'=>'published',
        'visibility'=>$payload['visibility'],
    ],'Product published to your store, feed, and merchant locations.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof InvalidArgumentException) mg_fail($e->getMessage(),422);
    if ($e instanceof RuntimeException) throw $e;
    mg_security_log('error','catalog.builder_action_failed','Builder action failed.',[
        'action'=>$action,'exception_type'=>get_class($e),
    ],(int)$user['id']);
    mg_fail('Unable to save the builder draft.',500);
}
