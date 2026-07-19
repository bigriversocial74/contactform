<?php
declare(strict_types=1);
require_once __DIR__.'/_merchant.php';
require_once dirname(__DIR__).'/bundles/_bundles.php';

$user=mg_require_permission($_SERVER['REQUEST_METHOD']==='GET'?'catalog.products.view':'catalog.products.manage');
$merchantId=(int)$user['id'];
$pdo=mg_db();
mg_bundle_require_schema($pdo);
$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$input=in_array($method,['POST','PUT','PATCH'],true)?mg_json_body():[];
$action=strtolower(trim((string)($input['action']??$_GET['action']??'list')));

try {
    if($method==='GET' && $action==='list'){
        $stmt=$pdo->prepare("SELECT b.public_id,b.title,b.short_statement,b.cover_asset_url,b.bundle_type,b.commission_mode,b.status,b.terms_version,b.inventory_limit,b.updated_at,
            (SELECT COUNT(*) FROM gift_bundle_components c WHERE c.bundle_id=b.id AND c.status<>'inactive') component_count,
            (SELECT COUNT(DISTINCT c.merchant_user_id) FROM gift_bundle_components c WHERE c.bundle_id=b.id AND c.status<>'inactive') merchant_count,
            (SELECT COUNT(*) FROM gift_bundle_participants p WHERE p.bundle_id=b.id AND p.invitation_status='pending') pending_invitations
            FROM gift_bundles b WHERE b.owner_merchant_user_id=? OR EXISTS(SELECT 1 FROM gift_bundle_participants p WHERE p.bundle_id=b.id AND p.merchant_user_id=?) ORDER BY b.updated_at DESC,b.id DESC");
        $stmt->execute([$merchantId,$merchantId]);
        mg_ok(['bundles'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if($method==='GET' && $action==='detail'){
        $bundle=mg_bundle_owned($pdo,(string)($_GET['id']??''),$merchantId);
        $c=$pdo->prepare('SELECT c.*,p.public_id product_public_id,v.public_id product_version_public_id FROM gift_bundle_components c INNER JOIN catalog_products p ON p.id=c.product_id INNER JOIN catalog_product_versions v ON v.id=c.product_version_id WHERE c.bundle_id=? ORDER BY c.display_order,c.id');
        $c->execute([(int)$bundle['id']]);
        $i=$pdo->prepare('SELECT p.public_id,p.merchant_user_id,p.invitation_status,p.proposed_terms,p.accepted_terms,p.terms_version,p.message,p.invited_at,p.responded_at,COALESCE(ms.display_name,u.display_name,u.email) merchant_name FROM gift_bundle_participants p INNER JOIN users u ON u.id=p.merchant_user_id LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>\'archived\' WHERE p.bundle_id=? ORDER BY p.id');
        $i->execute([(int)$bundle['id']]);
        mg_ok(['bundle'=>$bundle,'components'=>$c->fetchAll(PDO::FETCH_ASSOC),'participants'=>$i->fetchAll(PDO::FETCH_ASSOC),'publish_errors'=>mg_bundle_publish_validation($pdo,$bundle)]);
    }
    if($method==='GET' && $action==='products'){
        $stmt=$pdo->prepare("SELECT p.id,p.public_id,v.id version_internal_id,v.public_id version_id,v.title,v.description,v.unit_value_cents,v.currency,
            (SELECT a.storage_url FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=v.id AND a.asset_type='image' ORDER BY pva.display_order,pva.id LIMIT 1) image_url
            FROM catalog_products p INNER JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE p.merchant_user_id=? AND p.status='published' AND v.version_status='published' ORDER BY v.title");
        $stmt->execute([$merchantId]);
        mg_ok(['products'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if($method==='GET' && $action==='invitations'){
        $stmt=$pdo->prepare("SELECT p.public_id,p.invitation_status,p.proposed_terms,p.accepted_terms,p.terms_version,p.message,p.invited_at,p.responded_at,b.public_id bundle_public_id,b.title,b.short_statement,COALESCE(ms.display_name,u.display_name,u.email) lead_merchant FROM gift_bundle_participants p INNER JOIN gift_bundles b ON b.id=p.bundle_id INNER JOIN users u ON u.id=b.owner_merchant_user_id LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=u.id AND ms.status<>'archived' WHERE p.merchant_user_id=? ORDER BY p.updated_at DESC");
        $stmt->execute([$merchantId]);
        mg_ok(['invitations'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if($method==='POST' && $action==='create'){
        $title=mg_bundle_text($input['title']??'',190); if($title==='') throw new InvalidArgumentException('Bundle title is required.');
        $publicId=mg_public_uuid(); $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO gift_bundles (public_id,owner_merchant_user_id,title,slug,short_statement,description,cover_asset_url,category,occasion,primary_location,service_area,estimated_duration,bundle_type,commission_mode,starting_commission_bps,status,currency,sales_start_at,sales_end_at,redemption_expires_at,inventory_limit,visibility,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
          ->execute([$publicId,$merchantId,$title,mg_bundle_slug($input['slug']??$title),mg_bundle_text($input['short_statement']??'',255),trim((string)($input['description']??'')),mg_bundle_text($input['cover_asset_url']??'',500),mg_bundle_text($input['category']??'',100),mg_bundle_text($input['occasion']??'',100),mg_bundle_text($input['primary_location']??'',255),mg_bundle_text($input['service_area']??'',255),mg_bundle_text($input['estimated_duration']??'',100),$input['bundle_type']??'fixed_single_merchant',$input['commission_mode']??'merchant_default',($input['starting_commission_bps']??'')===''?null:mg_commission_normalize_bps($input['starting_commission_bps']),'draft',strtoupper((string)($input['currency']??'USD')),$input['sales_start_at']?:null,$input['sales_end_at']?:null,$input['redemption_expires_at']?:null,($input['inventory_limit']??'')===''?null:max(0,(int)$input['inventory_limit']),$input['visibility']??'private',$merchantId,$merchantId]);
        $bundleId=(int)$pdo->lastInsertId(); mg_bundle_audit($pdo,$bundleId,$merchantId,'bundle.created','bundle',$publicId,['title'=>$title]); $pdo->commit();
        mg_ok(['bundle_id'=>$publicId],201);
    }
    if($method==='POST' && $action==='add_component'){
        $bundle=mg_bundle_owned($pdo,(string)($input['bundle_id']??''),$merchantId,true);
        $productPublic=trim((string)($input['product_id']??''));
        $stmt=$pdo->prepare("SELECT p.id product_id,p.merchant_user_id,v.id version_id,v.title,v.description,v.unit_value_cents,v.public_id version_public_id,(SELECT a.storage_url FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=v.id AND a.asset_type='image' ORDER BY pva.display_order,pva.id LIMIT 1) image_url FROM catalog_products p INNER JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE p.public_id=? AND p.status='published' AND v.version_status='published' LIMIT 1 FOR UPDATE");
        $stmt->execute([$productPublic]); $product=$stmt->fetch(PDO::FETCH_ASSOC); if(!$product) throw new MgBundleException('Published product not found.',404);
        if((int)$product['merchant_user_id']!==$merchantId) throw new MgBundleException('Use the invitation workflow for another merchant’s product.',403);
        $amount=max(1,(int)($input['customer_amount_cents']??$product['unit_value_cents'])); $quote=mg_bundle_commission_quote($pdo,$merchantId,$amount,$bundle);
        $componentId=mg_public_uuid(); $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO gift_bundle_components (public_id,bundle_id,merchant_user_id,product_id,product_version_id,product_title_snapshot,product_description_snapshot,image_snapshot,quantity,display_order,is_required,customer_amount_cents,commissionable_amount_cents,commission_rate_bps,commission_amount_cents,merchant_net_amount_cents,commission_source,commission_rule_version,inventory_commitment,settlement_policy,claim_policy,expiration_rule,reservation_requirement,customer_instructions,merchant_instructions,terms_version,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'accepted\',NOW(),NOW())')
          ->execute([$componentId,(int)$bundle['id'],$merchantId,(int)$product['product_id'],(int)$product['version_id'],$product['title'],$product['description'],$product['image_url'],max(1,(int)($input['quantity']??1)),max(0,(int)($input['display_order']??0)),!empty($input['is_required'])?1:0,$amount,$amount,$quote['commission_rate_bps'],$quote['commission_amount_cents'],$quote['merchant_net_amount_cents'],$quote['commission_source'],$quote['commission_rule_version'],($input['inventory_commitment']??'')===''?null:max(0,(int)$input['inventory_commitment']),$input['settlement_policy']??'after_payment',mg_bundle_text($input['claim_policy']??'standard',100),mg_bundle_text($input['expiration_rule']??'',255),mg_bundle_text($input['reservation_requirement']??'',255),trim((string)($input['customer_instructions']??'')),trim((string)($input['merchant_instructions']??'')),(int)$bundle['terms_version']]);
        mg_bundle_audit($pdo,(int)$bundle['id'],$merchantId,'component.added','component',$componentId,['product_id'=>$productPublic,'quote'=>$quote]); $pdo->commit();
        mg_ok(['component_id'=>$componentId,'quote'=>$quote],201);
    }
    if($method==='POST' && $action==='invite'){
        $bundle=mg_bundle_owned($pdo,(string)($input['bundle_id']??''),$merchantId,true); $invitee=(int)($input['merchant_user_id']??0); if($invitee<1||$invitee===$merchantId) throw new InvalidArgumentException('Select another merchant.');
        $amount=max(1,(int)($input['customer_amount_cents']??0)); $terms=['product_public_id'=>trim((string)($input['product_id']??'')),'customer_amount_cents'=>$amount,'commission_rate_bps'=>mg_commission_normalize_bps($input['commission_rate_bps']??mg_commission_resolve_merchant_rate($pdo,$invitee,['initialize'=>true])['commission_rate_bps']),'inventory_commitment'=>max(0,(int)($input['inventory_commitment']??0)),'settlement_policy'=>$input['settlement_policy']??'after_payment','claim_policy'=>$input['claim_policy']??'standard','refund_terms'=>trim((string)($input['refund_terms']??''))];
        $publicId=mg_public_uuid(); $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO gift_bundle_participants (public_id,bundle_id,merchant_user_id,invitation_status,proposed_terms,terms_version,message,invited_by_user_id,invited_at,created_at,updated_at) VALUES (?,?,?,'pending',?,?,?,?,NOW(),NOW(),NOW())")
          ->execute([$publicId,(int)$bundle['id'],$invitee,mg_bundle_json($terms),(int)$bundle['terms_version'],trim((string)($input['message']??'')),$merchantId]);
        mg_bundle_audit($pdo,(int)$bundle['id'],$merchantId,'participant.invited','participant',$publicId,['merchant_user_id'=>$invitee,'terms'=>$terms]); $pdo->commit(); mg_ok(['invitation_id'=>$publicId],201);
    }
    if($method==='POST' && $action==='respond'){
        $status=strtolower(trim((string)($input['response']??''))); if(!in_array($status,['accepted','countered','declined','question'],true)) throw new InvalidArgumentException('Invalid invitation response.');
        $pdo->beginTransaction(); $stmt=$pdo->prepare('SELECT p.*,b.public_id bundle_public_id FROM gift_bundle_participants p INNER JOIN gift_bundles b ON b.id=p.bundle_id WHERE p.public_id=? AND p.merchant_user_id=? LIMIT 1 FOR UPDATE'); $stmt->execute([(string)($input['invitation_id']??''),$merchantId]); $invite=$stmt->fetch(PDO::FETCH_ASSOC); if(!$invite) throw new MgBundleException('Invitation not found.',404);
        $accepted=$status==='accepted'?$invite['proposed_terms']:null; $pdo->prepare('UPDATE gift_bundle_participants SET invitation_status=?,accepted_terms=?,message=?,accepted_by_user_id=?,responded_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$status,$accepted,trim((string)($input['message']??'')),$status==='accepted'?$merchantId:null,(int)$invite['id']]);
        mg_bundle_audit($pdo,(int)$invite['bundle_id'],$merchantId,'participant.'.$status,'participant',(string)$invite['public_id'],['message'=>$input['message']??'']); $pdo->commit(); mg_ok(['status'=>$status]);
    }
    if($method==='POST' && $action==='publish'){
        $pdo->beginTransaction(); $bundle=mg_bundle_owned($pdo,(string)($input['bundle_id']??''),$merchantId,true); $errors=mg_bundle_publish_validation($pdo,$bundle); if($errors) throw new MgBundleException(implode(' ',$errors),422);
        $snapshot=['bundle_public_id'=>$bundle['public_id'],'terms_version'=>(int)$bundle['terms_version'],'published_at'=>date(DATE_ATOM)]; $pdo->prepare("UPDATE gift_bundles SET status='published',visibility=CASE WHEN visibility='private' THEN 'public' ELSE visibility END,published_terms_snapshot=?,published_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=?")->execute([mg_bundle_json($snapshot),$merchantId,(int)$bundle['id']]); mg_bundle_audit($pdo,(int)$bundle['id'],$merchantId,'bundle.published','bundle',(string)$bundle['public_id'],$snapshot); $pdo->commit(); mg_ok(['status'=>'published']);
    }
    throw new MgBundleException('Unsupported bundle operation.',405);
} catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); $status=$e instanceof MgBundleException?$e->httpStatus:($e instanceof InvalidArgumentException?422:500); mg_fail($e->getMessage(),$status); }
