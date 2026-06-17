<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/demand/_prepaid.php';
require_once dirname(__DIR__) . '/demand/_snapshot.php';

mg_require_method('GET');
$user=mg_require_permission('demand.dashboard.view');
$merchantId=(int)$user['id'];
$pdo=mg_db();
mg_rate_limit('demand.prepaid_dashboard.read','user:'.$merchantId,120,60);

$horizon=max(1,min((int)($_GET['horizon_days']??30),365));
$locationRef=trim((string)($_GET['location_id']??''));
$productRef=trim((string)($_GET['product_id']??''));
$cohort=max(5,min((int)($_GET['minimum_cohort_size']??5),100));
[$start,$end]=mg_demand_snapshot_window(new DateTimeImmutable('now',new DateTimeZone('UTC')),$horizon);

$locationId=null;$productId=null;
if($locationRef!==''){
    $stmt=$pdo->prepare("SELECT ml.id FROM merchant_locations ml INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE ml.public_id=? AND mw.merchant_user_id=? AND ml.status='active' LIMIT 1");
    $stmt->execute([$locationRef,$merchantId]);$locationId=(int)($stmt->fetchColumn()?:0);
    if($locationId<1)mg_fail('Location not found.',404);
}
if($productRef!==''){
    $stmt=$pdo->prepare("SELECT id FROM catalog_products WHERE public_id=? AND merchant_user_id=? AND status IN ('draft','published') LIMIT 1");
    $stmt->execute([$productRef,$merchantId]);$productId=(int)($stmt->fetchColumn()?:0);
    if($productId<1)mg_fail('Product not found.',404);
}

try{
    $pdo->beginTransaction();
    $reconciled=mg_prepaid_demand_reconcile_batch($pdo,['merchant_user_id'=>$merchantId],500);
    $pdo->commit();
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','demand.prepaid_reconcile_failed','Merchant prepaid demand reconciliation failed.',['exception_class'=>$error::class],$merchantId);
    mg_fail('Unable to reconcile prepaid demand.',500);
}

$where=["p.merchant_user_id=?","p.signal_type='committed_demand'",'p.expected_from<?','(p.expected_to IS NULL OR p.expected_to>=?)'];
$params=[$merchantId,$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')];
if($locationId!==null){$where[]='p.location_id=?';$params[]=$locationId;}
if($productId!==null){$where[]='p.product_id=?';$params[]=$productId;}
$predicate=implode(' AND ',$where);

$stmt=$pdo->prepare("SELECT COUNT(*) commitments,COUNT(DISTINCT p.user_id) purchasers,
 COALESCE(SUM(IF(p.status='outstanding',p.estimated_value_cents,0)),0) committed_value,
 COALESCE(SUM(IF(p.status='redeemed',p.estimated_value_cents,0)),0) realized_value,
 SUM(p.status='outstanding') outstanding,SUM(l.lifecycle_state='purchased') purchased,SUM(l.lifecycle_state='sent') sent,
 SUM(l.lifecycle_state='claimed') claimed,SUM(l.lifecycle_state='redeemed') redeemed,SUM(l.lifecycle_state='cancelled') cancelled,
 SUM(l.lifecycle_state='refunded') refunded,SUM(l.lifecycle_state='expired') expired,SUM(l.lifecycle_state='replaced') replaced
 FROM purchase_signal_records p INNER JOIN microgift_demand_commitment_links l ON l.purchase_signal_id=p.id WHERE {$predicate}");
$stmt->execute($params);$total=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
$scopeSuppressed=(int)($total['commitments']??0)>0&&(int)($total['purchasers']??0)<$cohort;

$stmt=$pdo->prepare("SELECT DATE(p.expected_from) demand_date,COUNT(*) commitments,COUNT(DISTINCT p.user_id) purchasers,
 COALESCE(SUM(IF(p.status='outstanding',p.estimated_value_cents,0)),0) committed_value,
 COALESCE(SUM(IF(p.status='redeemed',p.estimated_value_cents,0)),0) realized_value
 FROM purchase_signal_records p INNER JOIN microgift_demand_commitment_links l ON l.purchase_signal_id=p.id
 WHERE {$predicate} GROUP BY DATE(p.expected_from) ORDER BY demand_date");
$stmt->execute($params);
$trend=array_map(static function(array $row)use($cohort):array{$visible=(int)$row['purchasers']>=$cohort;return['date'=>(string)$row['demand_date'],'commitments'=>$visible?(int)$row['commitments']:null,'committed_value_cents'=>$visible?(int)$row['committed_value']:null,'realized_value_cents'=>$visible?(int)$row['realized_value']:null,'suppressed'=>!$visible];},$stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt=$pdo->prepare("SELECT cp.public_id,COALESCE(cpv.title,'Unassigned product') title,COUNT(*) commitments,COUNT(DISTINCT p.user_id) purchasers,
 COALESCE(SUM(IF(p.status='outstanding',p.estimated_value_cents,0)),0) committed_value,
 COALESCE(SUM(IF(p.status='redeemed',p.estimated_value_cents,0)),0) realized_value,
 SUM(l.lifecycle_state='claimed') claimed,SUM(l.lifecycle_state='redeemed') redeemed
 FROM purchase_signal_records p INNER JOIN microgift_demand_commitment_links l ON l.purchase_signal_id=p.id
 LEFT JOIN catalog_products cp ON cp.id=p.product_id LEFT JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
 WHERE {$predicate} GROUP BY p.product_id,cp.public_id,cpv.title HAVING COUNT(DISTINCT p.user_id)>=?
 ORDER BY committed_value DESC,commitments DESC LIMIT 25");
$stmt->execute(array_merge($params,[$cohort]));
$products=array_map(static fn(array $row):array=>['id'=>$row['public_id']!==null?(string)$row['public_id']:null,'title'=>(string)$row['title'],'commitments'=>(int)$row['commitments'],'committed_value_cents'=>(int)$row['committed_value'],'realized_value_cents'=>(int)$row['realized_value'],'claimed'=>(int)$row['claimed'],'redeemed'=>(int)$row['redeemed']],$stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt=$pdo->prepare("SELECT ml.public_id,COALESCE(ml.name,'All locations') name,COUNT(*) commitments,COUNT(DISTINCT p.user_id) purchasers,
 COALESCE(SUM(IF(p.status='outstanding',p.estimated_value_cents,0)),0) committed_value
 FROM purchase_signal_records p INNER JOIN microgift_demand_commitment_links l ON l.purchase_signal_id=p.id
 LEFT JOIN merchant_locations ml ON ml.id=p.location_id WHERE {$predicate}
 GROUP BY p.location_id,ml.public_id,ml.name HAVING COUNT(DISTINCT p.user_id)>=? ORDER BY committed_value DESC LIMIT 25");
$stmt->execute(array_merge($params,[$cohort]));
$locations=array_map(static fn(array $row):array=>['id'=>$row['public_id']!==null?(string)$row['public_id']:null,'name'=>(string)$row['name'],'commitments'=>(int)$row['commitments'],'committed_value_cents'=>(int)$row['committed_value']],$stmt->fetchAll(PDO::FETCH_ASSOC));

$scopeKey=mg_demand_scope_key($locationId,$productId);
$stmt=$pdo->prepare("SELECT public_id,snapshot_date,horizon_days,outstanding_signal_count,outstanding_value_cents,committed_signal_count,committed_value_cents,future_visit_count,redeemed_signal_count,redeemed_value_cents,unique_users,weighted_demand_score,velocity_7d,velocity_30d,conversion_rate,features_json FROM demand_scope_snapshots WHERE merchant_user_id=? AND scope_key=? AND horizon_days=? ORDER BY snapshot_date DESC,id DESC LIMIT 1");
$stmt->execute([$merchantId,$scopeKey,$horizon]);$snapshot=$scopeSuppressed?null:($stmt->fetch(PDO::FETCH_ASSOC)?:null);

$signals=[];
if(!$scopeSuppressed){
    $stmt=$pdo->prepare("SELECT s.public_id,s.signal_key,s.signal_level,s.status,s.observed_value,s.baseline_value,s.confidence_score,s.summary,s.recommendation_json,s.triggered_at,s.expires_at,
     o.public_id orchestration_id,o.status orchestration_status,o.updated_at orchestration_updated_at
     FROM demand_agent_signals s LEFT JOIN demand_signal_orchestrations o ON o.demand_signal_id=s.id
     WHERE s.merchant_user_id=? AND s.location_id <=> ? AND s.product_id <=> ? AND s.status IN ('open','acknowledged')
     ORDER BY FIELD(s.signal_level,'critical','warning','opportunity','info'),s.triggered_at DESC LIMIT 50");
    $stmt->execute([$merchantId,$locationId,$productId]);
    $signals=array_map(static function(array $row):array{return['id'=>(string)$row['public_id'],'key'=>(string)$row['signal_key'],'level'=>(string)$row['signal_level'],'status'=>(string)$row['status'],'summary'=>(string)$row['summary'],'confidence'=>(float)$row['confidence_score'],'observed_value'=>$row['observed_value']!==null?(float)$row['observed_value']:null,'baseline_value'=>$row['baseline_value']!==null?(float)$row['baseline_value']:null,'recommendation'=>mg_prepaid_demand_json($row['recommendation_json']??null),'source'=>'derived_demand_snapshot','recommendation_only'=>true,'triggered_at'=>(string)$row['triggered_at'],'expires_at'=>$row['expires_at']!==null?(string)$row['expires_at']:null,'orchestration'=>$row['orchestration_id']!==null?['id'=>(string)$row['orchestration_id'],'status'=>(string)$row['orchestration_status'],'requires_approval'=>(string)$row['orchestration_status']==='awaiting_approval','updated_at'=>(string)$row['orchestration_updated_at']]:null];},$stmt->fetchAll(PDO::FETCH_ASSOC));
}

$locationOptions=$pdo->prepare("SELECT ml.public_id,ml.name FROM merchant_locations ml INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE mw.merchant_user_id=? AND ml.status='active' ORDER BY ml.name LIMIT 200");$locationOptions->execute([$merchantId]);
$productOptions=$pdo->prepare("SELECT cp.public_id,COALESCE(cpv.title,cp.slug) title FROM catalog_products cp LEFT JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id WHERE cp.merchant_user_id=? AND cp.status IN ('draft','published') ORDER BY title LIMIT 200");$productOptions->execute([$merchantId]);

$visibleTotal=static fn(string $key):?int=>$scopeSuppressed?null:(int)($total[$key]??0);
mg_event('demand.prepaid_dashboard_read',['horizon_days'=>$horizon,'scope_suppressed'=>$scopeSuppressed,'reconciled'=>$reconciled['processed'],'minimum_cohort_size'=>$cohort],$merchantId);
header('Cache-Control: private, no-store, max-age=0');
mg_ok([
 'window'=>['start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'horizon_days'=>$horizon],
 'filters'=>['location_id'=>$locationRef?:null,'product_id'=>$productRef?:null,'minimum_cohort_size'=>$cohort],
 'totals'=>['commitments'=>$visibleTotal('commitments'),'purchasers'=>$visibleTotal('purchasers'),'committed_value_cents'=>$visibleTotal('committed_value'),'realized_value_cents'=>$visibleTotal('realized_value'),'outstanding'=>$visibleTotal('outstanding'),'purchased'=>$visibleTotal('purchased'),'sent'=>$visibleTotal('sent'),'claimed'=>$visibleTotal('claimed'),'redeemed'=>$visibleTotal('redeemed'),'cancelled'=>$visibleTotal('cancelled'),'refunded'=>$visibleTotal('refunded'),'expired'=>$visibleTotal('expired'),'replaced'=>$visibleTotal('replaced'),'currency'=>'USD'],
 'trend'=>$trend,'products'=>$products,'locations'=>$locations,'snapshot'=>$snapshot,'signals'=>$signals,
 'options'=>['locations'=>$locationOptions->fetchAll(PDO::FETCH_ASSOC),'products'=>$productOptions->fetchAll(PDO::FETCH_ASSOC)],
 'privacy'=>['minimum_cohort_size'=>$cohort,'scope_suppressed'=>$scopeSuppressed,'grouped_rows_suppressed_below_cohort'=>true,'customer_identity_exposed'=>false],
 'definitions'=>['committed'=>'Prepaid Microgifts that remain outstanding.','realized'=>'Prepaid Microgifts redeemed through the canonical redemption authority.','forecast'=>'Statistical projection from Stage 4F and Stage 15 snapshots; not prepaid.','recommendation'=>'Agent-generated suggestion; no action occurs without Stage 16 policy.'],
]);
