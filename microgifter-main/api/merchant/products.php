<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('catalog.products.view');
$userId = (int)$user['id'];
$pdo = mg_db();

$status = strtolower(trim((string)($_GET['status'] ?? 'all')));
$productType = strtolower(trim((string)($_GET['product_type'] ?? 'all')));
$builderType = strtolower(trim((string)($_GET['builder_type'] ?? 'all')));
$sort = strtolower(trim((string)($_GET['sort'] ?? 'updated_desc')));
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1,(int)($_GET['page'] ?? 1));
$limit = max(1,min(50,(int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$allowedStatuses = ['draft','published','archived'];
$allowedProductTypes = ['gift','prize','reward','voucher','entitlement','reservation','credit','digital_product','other'];
$allowedBuilderTypes = ['simple_product','greeting_card','multimedia_greeting_card','simple_collab'];
$allowedSorts = [
    'updated_desc'=>'p.updated_at DESC,p.id DESC',
    'updated_asc'=>'p.updated_at ASC,p.id ASC',
    'title_asc'=>'v.title ASC,p.id ASC',
    'title_desc'=>'v.title DESC,p.id DESC',
    'value_desc'=>'v.unit_value_cents DESC,p.id DESC',
    'value_asc'=>'v.unit_value_cents ASC,p.id ASC',
];
if (!isset($allowedSorts[$sort])) $sort = 'updated_desc';
if (mb_strlen($q) > 120) mg_fail('Search query is too long.',422);

$where = ['p.merchant_user_id=?'];
$params = [$userId];
if (in_array($status,$allowedStatuses,true)) { $where[] = 'p.status=?'; $params[] = $status; }
if (in_array($productType,$allowedProductTypes,true)) { $where[] = 'p.product_type=?'; $params[] = $productType; }
if (in_array($builderType,$allowedBuilderTypes,true)) { $where[] = 'd.builder_type=?'; $params[] = $builderType; }
if ($q !== '') {
    $escaped = str_replace(['=','%','_'],['==','=%','=_'],$q);
    $like = '%' . $escaped . '%';
    $where[] = "(v.title LIKE ? ESCAPE '=' OR p.slug LIKE ? ESCAPE '=' OR p.public_id=?)";
    array_push($params,$like,$like,$q);
}
$whereSql = ' WHERE ' . implode(' AND ',$where);

$countStmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT p.id)
     FROM catalog_products p
     LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
     LEFT JOIN catalog_builder_drafts d ON d.product_id=p.id' . $whereSql
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = "SELECT p.public_id,p.product_type,p.slug,p.status,p.published_at,p.archived_at,p.created_at,p.updated_at,
               v.public_id version_id,v.version_number,v.title,v.description,v.unit_value_cents,v.currency,v.version_status,v.published_at version_published_at,
               d.builder_type,d.lock_version,d.updated_at draft_updated_at,
               (SELECT COUNT(*) FROM catalog_product_versions pv WHERE pv.product_id=p.id) version_count,
               (SELECT COUNT(*) FROM catalog_product_versions pv WHERE pv.product_id=p.id AND pv.version_status='published') published_version_count,
               (SELECT COUNT(*) FROM catalog_product_version_assets pva WHERE pva.product_version_id=p.current_version_id) asset_count,
               (SELECT COUNT(*) FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=p.current_version_id AND a.asset_type='image') image_count,
               (SELECT COUNT(*) FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=p.current_version_id AND a.asset_type='audio') audio_count,
               (SELECT COUNT(*) FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=p.current_version_id AND a.asset_type='video') video_count,
               (SELECT COUNT(DISTINCT rp.storefront_revision_id)
                  FROM merchant_storefront_revision_products rp
                  INNER JOIN merchant_storefront_revisions sr ON sr.id=rp.storefront_revision_id
                  WHERE rp.catalog_product_id=p.id AND sr.revision_status IN ('draft','published') AND rp.visibility='visible') storefront_placement_count,
               CASE WHEN d.id IS NOT NULL AND (v.id IS NULL OR d.updated_at>COALESCE(v.published_at,v.created_at)) THEN 1 ELSE 0 END has_draft_changes
        FROM catalog_products p
        LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
        LEFT JOIN catalog_builder_drafts d ON d.product_id=p.id" . $whereSql .
       ' ORDER BY ' . $allowedSorts[$sort] . " LIMIT {$limit} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$counts = $pdo->prepare(
    "SELECT COUNT(*) total,
            SUM(status='draft') drafts,
            SUM(status='published') published,
            SUM(status='archived') archived,
            SUM(status='published' AND current_version_id IS NOT NULL) sellable
     FROM catalog_products WHERE merchant_user_id=?"
);
$counts->execute([$userId]);

$typeCounts = $pdo->prepare('SELECT product_type,COUNT(*) count FROM catalog_products WHERE merchant_user_id=? GROUP BY product_type ORDER BY count DESC,product_type');
$typeCounts->execute([$userId]);

$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$isSuper = in_array('super_admin',$roles,true);

mg_ok([
    'products'=>$products,
    'counts'=>$counts->fetch() ?: [],
    'product_type_counts'=>$typeCounts->fetchAll(),
    'pagination'=>[
        'page'=>$page,
        'limit'=>$limit,
        'total'=>$total,
        'pages'=>max(1,(int)ceil($total/$limit)),
    ],
    'filters'=>[
        'statuses'=>$allowedStatuses,
        'product_types'=>$allowedProductTypes,
        'builder_types'=>$allowedBuilderTypes,
        'sorts'=>array_keys($allowedSorts),
    ],
    'access'=>[
        'manage'=>$isSuper || in_array('catalog.products.manage',$permissions,true),
        'publish'=>$isSuper || in_array('catalog.products.publish',$permissions,true),
        'assets'=>$isSuper || in_array('catalog.assets.manage',$permissions,true),
    ],
]);
