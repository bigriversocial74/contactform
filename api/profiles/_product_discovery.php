<?php
declare(strict_types=1);

require_once __DIR__ . '/_discovery.php';

const MG_PRODUCT_DISCOVERY_DEFAULT_LIMIT = 18;
const MG_PRODUCT_DISCOVERY_MAX_LIMIT = 36;

function mg_product_discovery_search(PDO $pdo, array $input, ?int $viewerId): array
{
    $filters = mg_profile_discovery_filters($input);
    $limit = max(1,min((int)($input['product_limit'] ?? MG_PRODUCT_DISCOVERY_DEFAULT_LIMIT),MG_PRODUCT_DISCOVERY_MAX_LIMIT));
    $params = [];
    $where = [
        "u.status='active'",
        "pp.status='active'",
        "pp.visibility IN ('public','unlisted')",
        "cp.status='published'",
        "cpv.version_status='published'",
        "cpvl.availability_status='available'",
        "ml.status='active'",
        "(cpv.metadata_json IS NULL OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cpv.metadata_json,'$.demo')),'false') NOT IN ('true','1'))",
    ];

    if ($filters['query'] !== '') {
        $contains = '%' . mg_profile_discovery_like($filters['query']) . '%';
        $where[] = "(LOWER(cpv.title) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(cpv.description,'')) LIKE ? ESCAPE '!'
          OR LOWER(pp.display_name) LIKE ? ESCAPE '!'
          OR LOWER(ml.name) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(ml.city,'')) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(ml.region,'')) LIKE ? ESCAPE '!')";
        array_push($params,$contains,$contains,$contains,$contains,$contains,$contains);
    }
    if ($filters['type'] !== '' && $filters['type'] !== 'merchant') {
        return ['items'=>[],'limit'=>$limit,'filters'=>$filters];
    }
    if ($filters['location'] !== '') {
        $contains = '%' . mg_profile_discovery_like($filters['location']) . '%';
        $where[] = "(LOWER(ml.name) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(ml.city,'')) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(ml.region,'')) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(ml.postal_code,'')) LIKE ? ESCAPE '!')";
        array_push($params,$contains,$contains,$contains,$contains);
    }
    if ($filters['category'] !== '') {
        $contains = '%' . mg_profile_discovery_like($filters['category']) . '%';
        $where[] = "(LOWER(cp.product_type)=? OR LOWER(cpv.title) LIKE ? ESCAPE '!' OR LOWER(COALESCE(cpv.description,'')) LIKE ? ESCAPE '!')";
        array_push($params,$filters['category'],$contains,$contains);
    }
    if ($viewerId !== null) {
        $where[] = 'NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=cp.merchant_user_id) OR (sb.blocking_user_id=cp.merchant_user_id AND sb.blocked_user_id=?))';
        array_push($params,$viewerId,$viewerId);
    }

    $sql = "SELECT cp.public_id,cp.slug,cp.product_type,cp.published_at,
        cpv.public_id AS version_id,cpv.title,cpv.description,cpv.unit_value_cents,cpv.currency,
        pp.public_id AS merchant_profile_id,pp.slug AS merchant_profile_slug,pp.display_name AS merchant_name,
        ms.slug AS storefront_slug,cover.public_id AS cover_asset_id,
        MAX(cpvl.is_primary) AS primary_location_score,
        GROUP_CONCAT(DISTINCT CONCAT_WS('~',ml.public_id,ml.name,COALESCE(ml.city,''),COALESCE(ml.region,''),COALESCE(ml.postal_code,''))
          ORDER BY cpvl.is_primary DESC,ml.name ASC SEPARATOR '||') AS location_rows
      FROM catalog_products cp
      INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
      INNER JOIN catalog_product_version_locations cpvl ON cpvl.product_version_id=cpv.id
      INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id
      INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id AND mw.merchant_user_id=cp.merchant_user_id
      INNER JOIN users u ON u.id=cp.merchant_user_id
      INNER JOIN public_profiles pp ON pp.user_id=cp.merchant_user_id
      LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=cp.merchant_user_id AND ms.status='published'
      LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=cpv.id AND pva.role='cover'
      LEFT JOIN catalog_assets cover ON cover.id=pva.asset_id AND cover.status='ready'
      WHERE " . implode(' AND ',$where) . "
      GROUP BY cp.id,cp.public_id,cp.slug,cp.product_type,cp.published_at,
        cpv.public_id,cpv.title,cpv.description,cpv.unit_value_cents,cpv.currency,
        pp.public_id,pp.slug,pp.display_name,ms.slug,cover.public_id
      ORDER BY primary_location_score DESC,cp.published_at DESC,cp.public_id ASC
      LIMIT " . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $locations = [];
        foreach (array_filter(explode('||',(string)($row['location_rows'] ?? ''))) as $encoded) {
            $parts = array_pad(explode('~',$encoded,5),5,'');
            $locations[] = [
                'id'=>$parts[0],'name'=>$parts[1],
                'city'=>$parts[2] !== '' ? $parts[2] : null,
                'region'=>$parts[3] !== '' ? $parts[3] : null,
                'postal_code'=>$parts[4] !== '' ? $parts[4] : null,
            ];
        }
        $items[] = [
            'id'=>(string)$row['public_id'],
            'version_id'=>(string)$row['version_id'],
            'title'=>(string)$row['title'],
            'description'=>$row['description'] !== null ? (string)$row['description'] : null,
            'product_type'=>(string)$row['product_type'],
            'value_cents'=>(int)$row['unit_value_cents'],
            'currency'=>(string)$row['currency'],
            'url'=>'/product.php?p=' . rawurlencode((string)$row['slug']),
            'cover_url'=>$row['cover_asset_id'] ? '/api/public/media.php?asset=' . rawurlencode((string)$row['cover_asset_id']) : null,
            'merchant'=>[
                'id'=>(string)$row['merchant_profile_id'],
                'name'=>(string)$row['merchant_name'],
                'url'=>'/profile.php?slug=' . rawurlencode((string)$row['merchant_profile_slug']),
                'store_url'=>$row['storefront_slug'] ? '/store.php?s=' . rawurlencode((string)$row['storefront_slug']) : null,
            ],
            'locations'=>$locations,
            'purchase_available'=>true,
            'result_kind'=>'product',
        ];
    }

    return ['items'=>$items,'limit'=>$limit,'filters'=>$filters];
}
