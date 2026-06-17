<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('catalog.products.manage');
$pdo = mg_db();

function mg_builder_type(string $value): string
{
    $allowed = ['simple_product','greeting_card','multimedia_greeting_card','simple_collab'];
    if (!in_array($value,$allowed,true)) mg_fail('Invalid builder type.',422);
    return $value;
}

function mg_builder_payload(mixed $value): array
{
    if (!is_array($value)) mg_fail('Invalid builder payload.',422);
    $json = json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > 524288) mg_fail('Builder payload is too large.',422);
    return $value;
}

function mg_builder_asset_map(mixed $value): array
{
    if ($value === null || $value === '') return [];
    if (!is_array($value)) mg_fail('Invalid asset map.',422);
    $allowed = ['cover','inside_cover','audio','video'];
    $clean = [];
    foreach ($value as $role => $assetId) {
        if (!in_array((string)$role,$allowed,true)) continue;
        $id = trim((string)$assetId);
        if ($id !== '') $clean[(string)$role] = $id;
    }
    return $clean;
}

if ($method === 'GET') {
    $productId = trim((string)($_GET['id'] ?? ''));
    if ($productId === '') mg_ok(['draft'=>null]);
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
    ]]);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string)($input['action'] ?? 'save'));
$builderType = mg_builder_type(trim((string)($input['builder_type'] ?? 'simple_product')));
$payload = mg_builder_payload($input['payload'] ?? []);
$assetMap = mg_builder_asset_map($input['assets'] ?? []);
$productId = trim((string)($input['product_id'] ?? ''));
$lockVersion = max(0,(int)($input['lock_version'] ?? 0));
$title = trim((string)($payload['title'] ?? 'Untitled product'));
if ($title === '' || mb_strlen($title) > 160) mg_fail('Invalid product title.',422);
$slug = mg_catalog_slug((string)($payload['slug'] ?? $title));
$productTypeMap = [
    'simple_product'=>'other',
    'greeting_card'=>'gift',
    'multimedia_greeting_card'=>'gift',
    'simple_collab'=>'reward',
];
$productType = $productTypeMap[$builderType];

try {
    $pdo->beginTransaction();
    $productStatus = 'draft';

    if ($productId === '') {
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
        if ($productStatus === 'draft') {
            $pdo->prepare("UPDATE catalog_products SET product_type=?,slug=?,updated_at=NOW() WHERE id=?")
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
        ],(int)$user['id']);
        mg_ok([
            'product_id'=>$productId,
            'draft_id'=>$draftId,
            'lock_version'=>$nextLock,
            'status'=>$productStatus,
            'has_draft_changes'=>true,
        ],'Product draft saved.');
    }

    if ($action !== 'publish') mg_fail('Invalid builder action.',422);
    mg_require_permission('catalog.products.publish');

    $nextVersionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM catalog_product_versions WHERE product_id=?');
    $nextVersionStmt->execute([$productDbId]);
    $versionNumber = (int)$nextVersionStmt->fetchColumn();
    $versionId = mg_catalog_uuid();
    $valueCents = max(0,(int)($payload['value_cents'] ?? 0));
    $currency = strtoupper(substr((string)($payload['currency'] ?? 'USD'),0,3));
    $description = trim((string)($payload['description'] ?? $payload['message'] ?? '')) ?: null;
    $terms = mg_catalog_json($payload['terms'] ?? null);
    $expiration = mg_catalog_json($payload['expiration_policy'] ?? null);
    $fulfillment = mg_catalog_json([
        'builder_type'=>$builderType,
        'claim_code_label'=>$payload['claim_code_label'] ?? null,
        'media_roles'=>array_keys($assetMap),
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

    $pdo->prepare("UPDATE catalog_product_versions SET version_status='retired' WHERE product_id=? AND id<>? AND version_status='published'")
        ->execute([$productDbId,$versionDbId]);

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

    $templateId = mg_catalog_uuid();
    $itemType = $builderType === 'simple_collab' ? 'reward' : ($builderType === 'simple_product' ? 'other' : 'gift');
    $pdo->prepare(
        "INSERT INTO catalog_pppm_templates
         (public_id,product_version_id,item_type,default_funding_type,issuance_defaults_json,status,created_at,updated_at)
         VALUES (?,?,?,'other',?,'active',NOW(),NOW())"
    )->execute([
        $templateId,$versionDbId,$itemType,
        json_encode([
            'title'=>$title,'description'=>$description,'value_cents'=>$valueCents,
            'currency'=>$currency,'builder_type'=>$builderType,
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->prepare("UPDATE catalog_products SET product_type=?,slug=?,current_version_id=?,status='published',published_at=NOW(),archived_at=NULL,updated_at=NOW() WHERE id=?")
        ->execute([$productType,$slug,$versionDbId,$productDbId]);

    $pdo->commit();
    mg_audit('catalog.builder_published','catalog_product',[
        'product_id'=>$productId,'version_id'=>$versionId,'template_id'=>$templateId,
    ],(int)$user['id']);
    mg_ok([
        'product_id'=>$productId,
        'draft_id'=>$draftId,
        'version_id'=>$versionId,
        'version_number'=>$versionNumber,
        'pppm_template_id'=>$templateId,
        'lock_version'=>$nextLock,
        'status'=>'published',
    ],'Product published.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof RuntimeException) throw $e;
    mg_security_log('error','catalog.builder_action_failed','Builder action failed.',[
        'action'=>$action,'exception_type'=>get_class($e),
    ],(int)$user['id']);
    mg_fail('Unable to save the builder draft.',500);
}
