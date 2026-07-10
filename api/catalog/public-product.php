<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';
require_once dirname(__DIR__) . '/store/_canvas.php';
mg_require_method('GET');

$identifier=trim((string)($_GET['id']??$_GET['slug']??''));
if($identifier==='')mg_fail('Product not found.',404);
$pdo=mg_db();
$stmt=$pdo->prepare("SELECT p.public_id product_id,p.slug,p.product_type,p.status,p.merchant_user_id,p.published_at,
        v.public_id version_id,v.version_number,v.title,v.description,v.unit_value_cents,v.currency,
        v.expiration_policy_json,v.terms_json,v.fulfillment_json,v.metadata_json,v.published_at version_published_at,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v.metadata_json,'$.merchant_name')),'Microgifter merchant') merchant_name,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v.metadata_json,'$.headline')),v.description,'A local gift ready to send.') headline,
        JSON_UNQUOTE(JSON_EXTRACT(v.metadata_json,'$.message')) message
    FROM catalog_products p
    INNER JOIN catalog_product_versions v ON v.id=p.current_version_id AND v.version_status='published'
    WHERE p.status='published' AND (p.public_id=? OR p.slug=?)
    LIMIT 1");
$stmt->execute([$identifier,$identifier]);
$product=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$product)mg_fail('Product not found.',404);

try {
    $viewer=function_exists('mg_current_user')?mg_current_user():null;
    $viewerId=(int)($viewer['id']??0);
    $merchantId=(int)($product['merchant_user_id']??0);
    if($viewerId>0&&$merchantId>0&&$viewerId!==$merchantId&&mg_store_canvas_schema_ready($pdo)){
        $session=mg_store_active_session_for_customer($pdo,$viewerId);
        if($session&&(int)$session['merchant_user_id']===$merchantId){
            $duplicate=$pdo->prepare("SELECT 1 FROM mg_store_session_events WHERE store_session_id=? AND event_type='viewed_product' AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND JSON_UNQUOTE(JSON_EXTRACT(event_data_json,'$.product_id'))=? LIMIT 1");
            $duplicate->execute([(int)$session['id'],(string)$product['product_id']]);
            if(!$duplicate->fetchColumn()){
                mg_store_log_event($pdo,$session,'viewed_product','Viewed product',[
                    'product_id'=>(string)$product['product_id'],
                    'product_slug'=>(string)$product['slug'],
                    'product_version_id'=>(string)$product['version_id'],
                    'source_system'=>'catalog_public_product',
                    'server_authoritative'=>true,
                    'browser_overlap_used'=>false,
                ]);
            }
        }
    }
}catch(Throwable $error){
    mg_security_log('warning','catalog.product_view_event_failed','Unable to record Store Canvas product-view event.',[
        'product_id'=>(string)($product['product_id']??''),'exception_class'=>$error::class,
    ],isset($viewerId)&&$viewerId>0?$viewerId:null);
}

$assets=$pdo->prepare('SELECT a.public_id,a.asset_type,a.original_filename,a.mime_type,a.width_px,a.height_px,a.duration_ms,pva.role,pva.sort_order FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=(SELECT id FROM catalog_product_versions WHERE public_id=? LIMIT 1) AND a.status=\'ready\' ORDER BY pva.sort_order,pva.id');
$assets->execute([(string)$product['version_id']]);

$product['metadata']=$product['metadata_json']?json_decode((string)$product['metadata_json'],true):[];
$product['terms']=$product['terms_json']?json_decode((string)$product['terms_json'],true):null;
$product['expiration_policy']=$product['expiration_policy_json']?json_decode((string)$product['expiration_policy_json'],true):null;
unset($product['metadata_json'],$product['terms_json'],$product['expiration_policy_json'],$product['fulfillment_json']);

mg_ok(['product'=>$product,'assets'=>$assets->fetchAll(PDO::FETCH_ASSOC)]);
