<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function mg_admin_commerce_microgift_detail(PDO $pdo,string $reference): array
{
    $e=mg_admin_commerce_one($pdo,<<<'SQL'
SELECT m.*,COALESCE(iu.display_name,iu.full_name,iu.email) merchant_name,iu.email merchant_email,
COALESCE(cu.display_name,cu.full_name,cu.email) customer_name,cu.email customer_email
FROM microgift_instances m INNER JOIN users iu ON iu.id=m.issuer_user_id
LEFT JOIN users cu ON cu.id=COALESCE(m.recipient_user_id,m.owner_user_id)
WHERE m.public_id=? LIMIT 1
SQL,[$reference]);
    if(!$e)throw new MgAdminCommerceException('Microgift not found.',404);
    $id=(int)$e['id'];
    $events=mg_admin_commerce_all($pdo,'SELECT event_type,actor_user_id,source_type,source_reference,created_at FROM microgift_events WHERE instance_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $claims=mg_admin_commerce_all($pdo,'SELECT public_id,claimant_user_id,status,source_reference,verified_at,completed_at,created_at FROM microgift_claims WHERE instance_id=? ORDER BY created_at DESC,id DESC LIMIT 50',[$id]);
    $redemptions=mg_admin_commerce_all($pdo,'SELECT public_id,claimant_user_id,merchant_user_id,location_reference,amount_cents,currency,status,source_reference,redeemed_at,created_at FROM microgift_redemptions WHERE instance_id=? ORDER BY created_at DESC,id DESC LIMIT 50',[$id]);
    $attempts=mg_admin_commerce_all($pdo,'SELECT public_id,merchant_user_id,location_id,actor_user_id,result,reason_code,correlation_id,attempted_at FROM microgift_claim_attempts WHERE instance_id=? ORDER BY attempted_at DESC,id DESC LIMIT 100',[$id]);
    $actions=mg_admin_commerce_all($pdo,'SELECT public_id,action_type,from_status,to_status,source_type,source_reference,actor_user_id,reason,created_at FROM microgift_lifecycle_actions WHERE instance_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $timeline=[mg_admin_commerce_timeline_item((string)$e['issued_at'],'microgift.issued','Microgift issued','issued',(string)$e['source_type'],'microgift')];
    foreach($events as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['created_at'],'microgift.'.(string)$r['event_type'],str_replace(['.','_'],' ',(string)$r['event_type']),null,$r['source_reference']!==null?(string)$r['source_reference']:null,(string)($r['source_type']??'microgift'));
    foreach($claims as $r)$timeline[]=mg_admin_commerce_timeline_item((string)($r['completed_at']??$r['verified_at']),'microgift.claim.'.(string)$r['status'],'Claim '.(string)$r['status'],(string)$r['status'],null,'claim');
    foreach($redemptions as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['redeemed_at'],'microgift.redemption.'.(string)$r['status'],'Redemption '.(string)$r['status'],(string)$r['status'],(string)($r['location_reference']??''),'redemption');
    foreach($attempts as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['attempted_at'],'microgift.claim_attempt','Claim attempt',(string)$r['result'],(string)$r['reason_code'],'claim');
    foreach($actions as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['created_at'],'microgift.lifecycle.'.(string)$r['action_type'],'Lifecycle '.str_replace('_',' ',(string)$r['action_type']),(string)$r['to_status'],$r['reason']!==null?(string)$r['reason']:null,'lifecycle');
    mg_admin_commerce_timeline_sort($timeline);
    return [
        'entity'=>['type'=>'microgift','public_id'=>(string)$e['public_id'],'status'=>(string)$e['status'],'secondary_status'=>(string)$e['source_type'],'title'=>(string)$e['title_snapshot'],'amount_cents'=>$e['face_value_cents']!==null?(int)$e['face_value_cents']:null,'currency'=>(string)$e['currency'],'merchant'=>['id'=>(int)$e['issuer_user_id'],'display_name'=>(string)$e['merchant_name'],'email'=>(string)$e['merchant_email']],'customer'=>($e['recipient_user_id']!==null||$e['owner_user_id']!==null)?['id'=>(int)($e['recipient_user_id']??$e['owner_user_id']),'display_name'=>(string)$e['customer_name'],'email'=>(string)$e['customer_email']]:null,'created_at'=>(string)$e['created_at'],'updated_at'=>(string)$e['updated_at']],
        'facts'=>[mg_admin_commerce_fact('Source',(string)$e['source_type']),mg_admin_commerce_fact('Source reference',(string)$e['source_reference']),mg_admin_commerce_fact('Recipient policy',(string)$e['recipient_policy']),mg_admin_commerce_fact('Issued at',$e['issued_at'],'date'),mg_admin_commerce_fact('Delivered at',$e['delivered_at'],'date'),mg_admin_commerce_fact('Claimed at',$e['claimed_at'],'date'),mg_admin_commerce_fact('Redeemed at',$e['redeemed_at'],'date'),mg_admin_commerce_fact('Expires at',$e['expires_at'],'date')],
        'related'=>compact('claims','redemptions','attempts','actions'),'timeline'=>$timeline,
    ];
}
