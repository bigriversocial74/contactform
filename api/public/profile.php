<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/profiles/_public_profile.php';
require_once dirname(__DIR__) . '/social/_engagement.php';

/**
 * Posts may be linked to a published catalog product without having their own
 * media_json attachment. Resolve that product's current published cover once
 * and expose it as post media so the Posts tab renders the same image customers
 * already see in Products and Stories.
 */
function mg_public_profile_attach_post_product_images(PDO $pdo, array &$data): void
{
    $posts = $data['posts']['items'] ?? null;
    if (!is_array($posts) || $posts === []) {
        return;
    }

    $productIds = [];
    foreach ($posts as $post) {
        $productId = strtolower(trim((string)($post['product_id'] ?? '')));
        if ($productId !== '' && preg_match('/^[a-f0-9-]{36}$/', $productId) === 1) {
            $productIds[$productId] = true;
        }
    }
    $productIds = array_keys($productIds);
    if ($productIds === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $sql = "SELECT cp.public_id,cp.slug,cpv.title,cover.public_id AS cover_asset_public_id
            FROM catalog_products cp
            INNER JOIN catalog_product_versions cpv
              ON cpv.id=cp.current_version_id AND cpv.version_status='published'
            LEFT JOIN catalog_product_version_assets pva
              ON pva.product_version_id=cpv.id AND pva.role='cover'
             AND pva.id=(
               SELECT MIN(pva2.id)
               FROM catalog_product_version_assets pva2
               WHERE pva2.product_version_id=cpv.id AND pva2.role='cover'
             )
            LEFT JOIN catalog_assets cover
              ON cover.id=pva.asset_id AND cover.status='ready'
            WHERE cp.public_id IN ({$placeholders}) AND cp.status='published'";

    $rows = mg_public_profile_query($pdo, $sql, $productIds)->fetchAll(PDO::FETCH_ASSOC);
    $products = [];
    foreach ($rows as $row) {
        $publicId = strtolower((string)$row['public_id']);
        $coverUrl = mg_public_profile_media_url($row['cover_asset_public_id'] ?? null);
        $products[$publicId] = [
            'id' => (string)$row['public_id'],
            'slug' => (string)$row['slug'],
            'title' => (string)$row['title'],
            'cover_url' => $coverUrl,
            'url' => '/product.php?p=' . rawurlencode((string)$row['slug']),
        ];
    }

    foreach ($data['posts']['items'] as &$post) {
        $productId = strtolower(trim((string)($post['product_id'] ?? '')));
        if ($productId === '' || !isset($products[$productId])) {
            continue;
        }

        $product = $products[$productId];
        $post['product'] = $product;
        $media = $post['media'] ?? [];
        if ((!is_array($media) || $media === []) && $product['cover_url'] !== null) {
            $post['media'] = [[
                'url' => $product['cover_url'],
                'type' => 'image',
                'alt' => $product['title'],
                'caption' => null,
                'source' => 'product_cover',
            ]];
        }
    }
    unset($post);
}

mg_require_method('GET');
$pdo=mg_db();
$slug=(string)($_GET['slug']??'');
$preview=(string)($_GET['preview']??'')==='1';
$viewer=mg_public_profile_session_viewer($pdo);

try{
    $data=mg_public_profile_read($pdo,$slug,[
        'viewer_id'=>$viewer['id']??null,
        'preview'=>$preview,
        'product_cursor'=>isset($_GET['product_cursor'])?(string)$_GET['product_cursor']:null,
        'post_cursor'=>isset($_GET['post_cursor'])?(string)$_GET['post_cursor']:null,
        'plan_cursor'=>isset($_GET['plan_cursor'])?(string)$_GET['plan_cursor']:null,
        'product_limit'=>$_GET['product_limit']??MG_PUBLIC_PROFILE_DEFAULT_LIMIT,
        'post_limit'=>$_GET['post_limit']??MG_PUBLIC_PROFILE_DEFAULT_LIMIT,
        'plan_limit'=>$_GET['plan_limit']??MG_PUBLIC_PROFILE_DEFAULT_LIMIT,
    ]);

    mg_public_profile_attach_post_product_images($pdo, $data);

    $viewerId=isset($viewer['id'])?(int)$viewer['id']:null;
    $isOwner=!empty($data['profile']['availability']['is_owner']);
    $relationship=[
        'authenticated'=>$viewerId!==null,
        'can_follow'=>$viewerId!==null&&!$isOwner,
        'following'=>false,
        'muted'=>false,
        'blocking'=>false,
        'followers'=>(int)($data['social_counts']['followers']??0),
    ];
    if($viewerId!==null&&!$isOwner){
        $target=mg_engagement_profile_target($pdo,(string)$data['profile']['id']);
        $relationship=array_merge($relationship,mg_engagement_relationship_state($pdo,$viewerId,(int)$target['user_id']));
    }
    $data['relationship']=$relationship;
}catch(InvalidArgumentException $error){
    if($error->getMessage()==='Invalid pagination cursor.')mg_fail('Invalid pagination cursor.',422);
    mg_fail('Profile not found.',404);
}catch(RuntimeException){
    mg_fail('Profile not found.',404);
}catch(Throwable $error){
    error_log('Public profile read failed: '.$error::class);
    mg_fail('Unable to load profile.',500);
}

$isAnonymous=$viewer===null;
$isPublic=(string)($data['profile']['visibility']??'')==='public';
$isPreview=!empty($data['profile']['availability']['is_preview']);
if($isAnonymous&&$isPublic&&!$isPreview){
    header_remove('Set-Cookie');
    header('Cache-Control: public, max-age=60, stale-while-revalidate=30');
}else{
    header('Cache-Control: private, no-store, max-age=0');
}
header('Vary: Cookie, Authorization');
if((string)($data['profile']['visibility']??'')==='unlisted')header('X-Robots-Tag: noindex, nofollow');
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'message'=>'OK','data'=>$data],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);