<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

function mg_entitlement_uuid(): string { return mg_public_uuid(); }

function mg_entitlement_event(PDO $pdo, int $entitlementId, string $eventType, ?string $fromStatus, ?string $toStatus, ?int $actorUserId, ?string $reasonCode = null, array $payload = []): void
{
    $pdo->prepare('INSERT INTO entitlement_events (public_id,entitlement_id,event_type,from_status,to_status,actor_user_id,reason_code,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_entitlement_uuid(),$entitlementId,$eventType,$fromStatus,$toStatus,$actorUserId,$reasonCode,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
}

function mg_entitlement_record_access(PDO $pdo, ?int $entitlementId, ?int $assetId, ?int $pppmItemId, ?int $userId, string $eventType, string $reason, array $context = []): void
{
    $pdo->prepare('INSERT INTO entitlement_access_events (public_id,entitlement_id,asset_id,pppm_item_id,user_id,event_type,decision_reason,request_context_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_entitlement_uuid(),$entitlementId,$assetId,$pppmItemId,$userId,$eventType,$reason,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
}

function mg_entitlement_create_review(PDO $pdo, string $type, string $reason, array $refs, array $payload = []): void
{
    $pdo->prepare("INSERT INTO entitlement_review_items (public_id,review_type,status,user_id,merchant_user_id,commerce_order_id,pppm_item_id,entitlement_id,reason,payload_json,created_at,updated_at) VALUES (?,?,'open',?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([mg_entitlement_uuid(),$type,$refs['user_id']??null,$refs['merchant_user_id']??null,$refs['commerce_order_id']??null,$refs['pppm_item_id']??null,$refs['entitlement_id']??null,$reason,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
}

function mg_entitlement_load_pppm_item(PDO $pdo, string $publicId): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM pppm_items WHERE public_id=? LIMIT 1');
    $stmt->execute([$publicId]);
    $row=$stmt->fetch();
    return $row ?: null;
}

function mg_entitlement_assets_for_pppm(PDO $pdo, array $pppmItem): array
{
    $lineRef=(string)($pppmItem['source_line_reference']??'');
    if($lineRef==='') return [];
    $stmt=$pdo->prepare('SELECT oi.id order_item_id, oi.product_version_id, a.*, pva.role FROM commerce_order_items oi INNER JOIN catalog_product_version_assets pva ON pva.product_version_id=oi.product_version_id INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE oi.public_id=? AND pva.role IN (\'download\',\'audio\',\'video\',\'document\') AND a.status=\'ready\' ORDER BY pva.sort_order,a.id');
    $stmt->execute([$lineRef]);
    return $stmt->fetchAll();
}

function mg_entitlement_grant_for_pppm_item(PDO $pdo, array $pppmItem, ?int $actorUserId = null): array
{
    $ownerId=(int)($pppmItem['owner_user_id']??0);
    if($ownerId<1) return ['created'=>0,'skipped'=>'no_owner'];
    $assets=mg_entitlement_assets_for_pppm($pdo,$pppmItem);
    $created=0;$existing=0;
    foreach($assets as $asset){
        $key='pppm:'.$pppmItem['public_id'].':asset:'.$asset['public_id'].':download';
        $find=$pdo->prepare('SELECT * FROM entitlements WHERE idempotency_key=? LIMIT 1');
        $find->execute([$key]);
        if($find->fetch()){ $existing++; continue; }
        $public=mg_entitlement_uuid();
        try{
            $pdo->prepare("INSERT INTO entitlements (public_id,entitlement_type,status,pppm_item_id,commerce_order_item_id,product_version_id,asset_id,entitled_user_id,merchant_user_id,source_type,source_reference,idempotency_key,starts_at,policy_json,metadata_json,created_at,updated_at) VALUES (?,'download','active',?,?,?,?,?,?, 'pppm_item',?,?,NOW(),?,?,NOW(),NOW())")
                ->execute([$public,(int)$pppmItem['id'],(int)$asset['order_item_id'],(int)$asset['product_version_id'],(int)$asset['id'],$ownerId,(int)$pppmItem['merchant_user_id'],(string)$pppmItem['public_id'],$key,json_encode(['grant_source'=>'paid_pppm_issuance'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),json_encode(['asset_role'=>$asset['role']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
            $entitlementId=(int)$pdo->lastInsertId();
            mg_entitlement_event($pdo,$entitlementId,'entitlement.granted',null,'active',$actorUserId,'paid_pppm_issuance',['pppm_item_id'=>$pppmItem['public_id'],'asset_id'=>$asset['public_id']]);
            mg_event('entitlement.granted',['entitlement_id'=>$public,'pppm_item_id'=>$pppmItem['public_id'],'asset_id'=>$asset['public_id']],$actorUserId);
            $created++;
        }catch(Throwable $e){
            if(str_contains($e->getMessage(),'Duplicate')){$existing++;continue;}
            throw $e;
        }
    }
    return ['created'=>$created,'existing'=>$existing,'asset_count'=>count($assets)];
}

function mg_entitlement_grant_for_order(PDO $pdo, int $orderDbId, ?int $actorUserId = null): array
{
    $stmt=$pdo->prepare('SELECT pi.* FROM pppm_items pi INNER JOIN pppm_issuance_requests ir ON ir.id=pi.issuance_request_id INNER JOIN commerce_order_items oi ON oi.pppm_issuance_request_id=ir.id WHERE oi.order_id=? ORDER BY pi.id');
    $stmt->execute([$orderDbId]);
    $created=0;$existing=0;$items=0;
    foreach($stmt->fetchAll() as $item){$items++;$result=mg_entitlement_grant_for_pppm_item($pdo,$item,$actorUserId);$created+=(int)($result['created']??0);$existing+=(int)($result['existing']??0);}
    return ['items'=>$items,'created'=>$created,'existing'=>$existing];
}

function mg_entitlement_resolve_active(PDO $pdo, int $userId, string $assetPublicId): ?array
{
    $stmt=$pdo->prepare("SELECT e.*,a.public_id asset_public_id,a.storage_provider,a.storage_key,a.original_filename,a.mime_type,a.byte_size,pi.public_id pppm_public_id FROM entitlements e INNER JOIN catalog_assets a ON a.id=e.asset_id INNER JOIN pppm_items pi ON pi.id=e.pppm_item_id WHERE a.public_id=? AND e.entitled_user_id=? AND e.status='active' AND a.status='ready' AND (e.starts_at IS NULL OR e.starts_at<=NOW()) AND (e.expires_at IS NULL OR e.expires_at>NOW()) ORDER BY e.id DESC LIMIT 1");
    $stmt->execute([$assetPublicId,$userId]);
    $row=$stmt->fetch();
    return $row ?: null;
}

function mg_entitlement_authorize_asset(PDO $pdo, int $userId, string $assetPublicId, array $context = []): array
{
    $entitlement=mg_entitlement_resolve_active($pdo,$userId,$assetPublicId);
    if(!$entitlement){
        $asset=$pdo->prepare('SELECT id FROM catalog_assets WHERE public_id=? LIMIT 1');$asset->execute([$assetPublicId]);$assetId=$asset->fetchColumn();
        mg_entitlement_record_access($pdo,null,$assetId?(int)$assetId:null,null,$userId,'denied','no_active_entitlement',$context);
        mg_fail('No active entitlement for this asset.',403);
    }
    mg_entitlement_record_access($pdo,(int)$entitlement['id'],(int)$entitlement['asset_id'],(int)$entitlement['pppm_item_id'],$userId,'authorized','active_entitlement',$context);
    return $entitlement;
}

function mg_entitlement_create_delivery_grant(PDO $pdo, array $entitlement, int $userId): array
{
    $token=bin2hex(random_bytes(24));
    $hash=hash('sha256',$token);
    $public=mg_entitlement_uuid();
    $expires=(new DateTimeImmutable('+10 minutes'))->format('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO asset_delivery_grants (public_id,entitlement_id,asset_id,user_id,token_hash,delivery_mode,expires_at,created_at) VALUES (?,?,?,?,?,'metadata_only',?,NOW())")
        ->execute([$public,(int)$entitlement['id'],(int)$entitlement['asset_id'],$userId,$hash,$expires]);
    mg_entitlement_record_access($pdo,(int)$entitlement['id'],(int)$entitlement['asset_id'],(int)$entitlement['pppm_item_id'],$userId,'download_started','delivery_grant_created',['grant_id'=>$public]);
    return ['grant_id'=>$public,'token'=>$token,'expires_at'=>$expires];
}

function mg_entitlements_revoke_for_order(PDO $pdo, int $orderDbId, string $reason, ?int $actorUserId = null): int
{
    $stmt=$pdo->prepare("SELECT e.* FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='active' FOR UPDATE");
    $stmt->execute([$orderDbId]);
    $count=0;
    foreach($stmt->fetchAll() as $entitlement){
        $pdo->prepare("UPDATE entitlements SET status='revoked',revoked_at=NOW(),revocation_reason=?,updated_at=NOW() WHERE id=? AND status='active'")->execute([$reason,(int)$entitlement['id']]);
        mg_entitlement_event($pdo,(int)$entitlement['id'],'entitlement.revoked','active','revoked',$actorUserId,$reason,[]);
        $count++;
    }
    return $count;
}

function mg_entitlements_suspend_for_order(PDO $pdo, int $orderDbId, string $reason, ?int $actorUserId = null): int
{
    $stmt=$pdo->prepare("SELECT e.* FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='active' FOR UPDATE");
    $stmt->execute([$orderDbId]);
    $count=0;
    foreach($stmt->fetchAll() as $entitlement){
        $pdo->prepare("UPDATE entitlements SET status='suspended',suspended_at=NOW(),updated_at=NOW() WHERE id=? AND status='active'")->execute([(int)$entitlement['id']]);
        mg_entitlement_event($pdo,(int)$entitlement['id'],'entitlement.suspended','active','suspended',$actorUserId,$reason,[]);
        $count++;
    }
    return $count;
}

function mg_entitlements_apply_refund_policy(PDO $pdo, array $order, int $refundAmountCents, int $totalRefundedCents, ?int $actorUserId = null): array
{
    if($totalRefundedCents >= (int)$order['total_cents']){
        $revoked=mg_entitlements_revoke_for_order($pdo,(int)$order['order_db_id'],'full_refund',$actorUserId);
        return ['policy'=>'full_refund','revoked'=>$revoked];
    }
    mg_entitlement_create_review($pdo,'partial_refund','Partial refund requires entitlement review.',['user_id'=>(int)$order['buyer_user_id'],'merchant_user_id'=>(int)$order['merchant_user_id'],'commerce_order_id'=>(int)$order['order_db_id']],['refund_amount_cents'=>$refundAmountCents,'total_refunded_cents'=>$totalRefundedCents]);
    return ['policy'=>'partial_refund_review','revoked'=>0];
}
