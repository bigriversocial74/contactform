<?php
declare(strict_types=1);

require_once __DIR__.'/MicrogiftBehaviorFixture.php';

function mg_checkout_fixture_catalog(PDO $pdo,int $merchantId,string $runId): array
{
    $productPublic=mg_public_uuid();$versionPublic=mg_public_uuid();$assetPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO catalog_products (public_id,merchant_user_id,product_type,slug,status,created_by_user_id,published_at,created_at,updated_at) VALUES (?,?,'digital_product',?,'published',?,NOW(),NOW(),NOW())")
        ->execute([$productPublic,$merchantId,'checkout-'.$runId,$merchantId]);
    $productId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO catalog_product_versions (public_id,product_id,version_number,version_status,title,description,unit_value_cents,currency,checksum,created_by_user_id,published_at,created_at) VALUES (?, ?,1,'published','Checkout behavior product','Checkout validation',1250,'USD',?,?,NOW(),NOW())")
        ->execute([$versionPublic,$productId,hash('sha256',$runId),$merchantId]);
    $versionId=(int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE catalog_products SET current_version_id=? WHERE id=?')->execute([$versionId,$productId]);
    $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,status,created_at,updated_at) VALUES (?,?,'download','behavior',?,'checkout.txt','text/plain',32,'ready',NOW(),NOW())")
        ->execute([$assetPublic,$merchantId,'checkout/'.$runId.'/asset.txt']);
    $assetId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO catalog_product_version_assets (product_version_id,asset_id,role,sort_order,created_at) VALUES (?,?,'download',0,NOW())")
        ->execute([$versionId,$assetId]);
    return ['product_id'=>$productId,'product_public'=>$productPublic,'version_id'=>$versionId,'version_public'=>$versionPublic,'asset_id'=>$assetId,'asset_public'=>$assetPublic];
}

function mg_checkout_fixture_draft(PDO $pdo,array $fixture,string $suffix): array
{
    $cartPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO carts (public_id,user_id,status,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,expires_at,created_at,updated_at) VALUES (?,?,'active','USD',2500,0,0,0,2500,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW())")
        ->execute([$cartPublic,$fixture['buyer_id']]);
    $cartId=(int)$pdo->lastInsertId();
    $itemPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO cart_items (public_id,cart_id,product_id,product_version_id,merchant_user_id,title_snapshot,unit_amount_cents,currency,quantity,line_total_cents,created_at,updated_at) VALUES (?,?,?,?,?,'Checkout behavior product',1250,'USD',2,2500,NOW(),NOW())")
        ->execute([$itemPublic,$cartId,$fixture['product_id'],$fixture['version_id'],$fixture['merchant_id']]);
    $items=[[
        'item_id'=>$itemPublic,'product_id'=>$fixture['product_id'],'product_version_id'=>$fixture['version_id'],
        'merchant_user_id'=>$fixture['merchant_id'],'title_snapshot'=>'Checkout behavior product','quantity'=>2,
        'unit_amount_cents'=>1250,'line_total_cents'=>2500,'currency'=>'USD',
    ]];
    $draftPublic=mg_public_uuid();
    $pdo->prepare("INSERT INTO checkout_drafts (public_id,cart_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,items_json,status,idempotency_key,expires_at,created_at,updated_at) VALUES (?,?,?,?, 'USD',2500,0,0,0,2500,?,'open',?,DATE_ADD(NOW(),INTERVAL 30 MINUTE),NOW(),NOW())")
        ->execute([$draftPublic,$cartId,$fixture['buyer_id'],$fixture['merchant_id'],json_encode($items,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'fixture:draft:'.$fixture['run_id'].':'.$suffix]);
    return ['cart_id'=>$cartId,'cart_public'=>$cartPublic,'draft_id'=>(int)$pdo->lastInsertId(),'draft_public'=>$draftPublic];
}
