<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';
mg_require_method('GET');
$user=mg_require_permission('merchant.claims.view');$identifier=trim((string)($_GET['id']??''));if($identifier==='')mg_fail('Claim identifier required.',422);$pdo=mg_db();$claim=mg_claim_lookup($pdo,(int)$user['id'],$identifier);
$eligibility=$pdo->prepare('SELECT e.location_id,ml.public_id location_id_public,ml.name location_name FROM gift_merchant_eligibility e LEFT JOIN merchant_locations ml ON ml.id=e.location_id WHERE e.gift_id=? AND e.merchant_user_id=? ORDER BY ml.name');$eligibility->execute([(int)$claim['gift_db_id'],(int)$user['id']]);
$attempts=[];$events=[];$exceptions=[];
if($claim['claim_db_id']){
 $q=$pdo->prepare('SELECT successful,actor_user_id,created_at FROM gift_claim_attempts WHERE claim_id=? ORDER BY created_at DESC,id DESC LIMIT 100');$q->execute([(int)$claim['claim_db_id']]);$attempts=$q->fetchAll();
 $q=$pdo->prepare('SELECT mce.public_id,mce.exception_type,mce.status,mce.priority,mce.summary,mce.resolution_notes,mce.created_at,mce.updated_at FROM merchant_claim_exceptions mce WHERE mce.claim_id=? AND mce.merchant_user_id=? ORDER BY mce.created_at DESC');$q->execute([(int)$claim['claim_db_id'],(int)$user['id']]);$exceptions=$q->fetchAll();
}
$q=$pdo->prepare('SELECT event_type,metadata_json,created_at FROM gift_events WHERE gift_id=? ORDER BY created_at DESC,id DESC');$q->execute([(int)$claim['gift_db_id']]);$events=$q->fetchAll();
$location=null;if($claim['location_id']){$q=$pdo->prepare('SELECT ml.public_id,ml.name,ml.location_code,ml.status FROM merchant_locations ml INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE ml.id=? AND mw.merchant_user_id=? LIMIT 1');$q->execute([(int)$claim['location_id'],(int)$user['id']]);$location=$q->fetch()?:null;}
mg_ok(['claim'=>$claim,'location'=>$location,'eligibility'=>$eligibility->fetchAll(),'attempts'=>$attempts,'events'=>$events,'exceptions'=>$exceptions]);
