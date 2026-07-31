<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/app.php';

$environment=(string)mg_env('MG_APP_ENV','');
$browserAuthEnabled=(string)mg_env('MG_TEST_SKIP_AUTHENTICATED','')==='1';
$remoteAddress=(string)($_SERVER['REMOTE_ADDR']??'');
$isLoopback=in_array($remoteAddress,['127.0.0.1','::1'],true);

if($environment!=='testing'||!$browserAuthEnabled||!$isLoopback){
    http_response_code(404);
    exit('Not found.');
}

$targets=[
    'product'=>'/product.php?id=11111111-1111-4111-8111-111111111111&p=release-smoke',
    'cart'=>'/cart.php',
];
$target=strtolower(trim((string)($_GET['target']??'product')));
$destination=$targets[$target]??$targets['product'];

if($target==='product'){
    $pdo=mg_db();
    $merchantEmail='v1-browser-merchant@example.test';
    $pdo->prepare("INSERT INTO users
        (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at)
        VALUES (?,?,'Phoenix Coffee','Phoenix Coffee','active',NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE full_name=VALUES(full_name),display_name=VALUES(display_name),status='active',email_verified_at=COALESCE(email_verified_at,NOW()),updated_at=NOW()")
        ->execute([$merchantEmail,password_hash('BrowserFixturePassword123!',PASSWORD_DEFAULT)]);
    $merchantStmt=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $merchantStmt->execute([$merchantEmail]);
    $merchantId=(int)$merchantStmt->fetchColumn();
    if($merchantId<1)throw new RuntimeException('Unable to seed browser merchant.');

    $productPublic='11111111-1111-4111-8111-111111111111';
    $versionPublic='22222222-2222-4222-8222-222222222222';
    $slug='release-smoke';
    $pdo->prepare("INSERT INTO catalog_products
        (public_id,merchant_user_id,product_type,slug,status,created_by_user_id,published_at,created_at,updated_at)
        VALUES (?,?,'voucher',?,'published',?,NOW(),NOW(),NOW())
        ON DUPLICATE KEY UPDATE merchant_user_id=VALUES(merchant_user_id),product_type='voucher',slug=VALUES(slug),status='published',created_by_user_id=VALUES(created_by_user_id),published_at=COALESCE(published_at,NOW()),updated_at=NOW()")
        ->execute([$productPublic,$merchantId,$slug,$merchantId]);
    $productStmt=$pdo->prepare('SELECT id FROM catalog_products WHERE public_id=? LIMIT 1');
    $productStmt->execute([$productPublic]);
    $productId=(int)$productStmt->fetchColumn();
    if($productId<1)throw new RuntimeException('Unable to seed browser product.');

    $description='<img src=x onerror=window.__unsafe=true>';
    $fulfillment=json_encode(['builder_type'=>'simple_product'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $metadata=json_encode([
        'merchant_name'=>'Phoenix Coffee',
        'headline'=>'A local coffee gift',
        'message'=>$description,
        'offer'=>'Coffee and pastry',
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $expiration=json_encode(['label'=>'No expiration'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $terms=json_encode(['note'=>'Redeem at the issuing merchant.'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $checksum=hash('sha256','production-launch-browser-release-smoke-v1');
    $pdo->prepare("INSERT INTO catalog_product_versions
        (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,
         expiration_policy_json,terms_json,fulfillment_json,metadata_json,checksum,created_by_user_id,published_at,created_at)
        VALUES (?,?,1,'published','Release Smoke Coffee Gift',?,2500,'USD',?,?,?,?,?,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE product_id=VALUES(product_id),version_status='published',title=VALUES(title),description=VALUES(description),unit_value_cents=2500,currency='USD',expiration_policy_json=VALUES(expiration_policy_json),terms_json=VALUES(terms_json),fulfillment_json=VALUES(fulfillment_json),metadata_json=VALUES(metadata_json),checksum=VALUES(checksum),created_by_user_id=VALUES(created_by_user_id),published_at=COALESCE(published_at,NOW())")
        ->execute([$versionPublic,$productId,$description,$expiration,$terms,$fulfillment,$metadata,$checksum,$merchantId]);
    $versionStmt=$pdo->prepare('SELECT id FROM catalog_product_versions WHERE public_id=? LIMIT 1');
    $versionStmt->execute([$versionPublic]);
    $versionId=(int)$versionStmt->fetchColumn();
    if($versionId<1)throw new RuntimeException('Unable to seed browser product version.');
    $pdo->prepare("UPDATE catalog_products SET current_version_id=?,status='published',published_at=COALESCE(published_at,NOW()),updated_at=NOW() WHERE id=?")
        ->execute([$versionId,$productId]);
}

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
session_regenerate_id(true);
setcookie(session_name(),session_id(),[
    'expires'=>0,
    'path'=>'/',
    'secure'=>false,
    'httponly'=>true,
    'samesite'=>'Lax',
]);
$_SESSION['mg_user']=[
    'id'=>999998,
    'public_id'=>'99999999-9999-4999-8999-999999999998',
    'display_name'=>'V1 Browser Customer',
    'email'=>'v1-browser@example.test',
    'roles'=>['customer'],
    'permissions'=>[],
];

header('Cache-Control: private, no-store, max-age=0');
header('Location: '.$destination,true,302);
exit;
