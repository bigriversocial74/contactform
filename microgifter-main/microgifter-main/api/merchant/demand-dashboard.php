<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/demand/_snapshot.php';

mg_require_method('GET');
$user=mg_require_permission('demand.dashboard.view');
$pdo=mg_db();
$horizon=max(1,min((int)($_GET['horizon_days']??30),365));
$locationRef=trim((string)($_GET['location_id']??''));
$productRef=trim((string)($_GET['product_id']??''));
$locationId=null;$productId=null;
if($locationRef!==''){
    $stmt=$pdo->prepare('SELECT ml.id FROM merchant_locations ml INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE ml.public_id=? AND mw.merchant_user_id=? LIMIT 1');
    $stmt->execute([$locationRef,(int)$user['id']]);$locationId=(int)($stmt->fetchColumn()?:0);
    if($locationId<1)mg_fail('Location not found.',404);
}
if($productRef!==''){
    $stmt=$pdo->prepare('SELECT id FROM catalog_products WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $stmt->execute([$productRef,(int)$user['id']]);$productId=(int)($stmt->fetchColumn()?:0);
    if($productId<1)mg_fail('Product not found.',404);
}
$scopeKey=mg_demand_scope_key($locationId,$productId);
$stmt=$pdo->prepare('SELECT * FROM demand_scope_snapshots WHERE merchant_user_id=? AND scope_key=? AND horizon_days=? ORDER BY snapshot_date DESC,id DESC LIMIT 1');
$stmt->execute([(int)$user['id'],$scopeKey,$horizon]);$snapshot=$stmt->fetch(PDO::FETCH_ASSOC);
$signals=$pdo->prepare("SELECT public_id,signal_key,signal_level,status,observed_value,baseline_value,confidence_score,summary,recommendation_json,triggered_at,expires_at FROM demand_agent_signals WHERE merchant_user_id=? AND location_id <=> ? AND product_id <=> ? AND status IN ('open','acknowledged') ORDER BY FIELD(signal_level,'critical','warning','opportunity','info'),triggered_at DESC LIMIT 50");
$signals->execute([(int)$user['id'],$locationId,$productId]);
[$windowStart,$windowEnd]=mg_demand_snapshot_window(new DateTimeImmutable('now',new DateTimeZone('UTC')),$horizon);
$where=['merchant_user_id=?','location_id <=> ?','product_id <=> ?',"status='outstanding'",mg_demand_window_predicate()];
$params=[(int)$user['id'],$locationId,$productId,$windowEnd->format('Y-m-d H:i:s'),$windowStart->format('Y-m-d H:i:s'),$windowStart->format('Y-m-d H:i:s')];
$upcoming=$pdo->prepare('SELECT signal_type,COUNT(*) signal_count,COALESCE(SUM(estimated_value_cents),0) value_cents,COALESCE(SUM(quantity),0) quantity FROM purchase_signal_records WHERE '.implode(' AND ',$where).' GROUP BY signal_type ORDER BY value_cents DESC');
$upcoming->execute($params);
mg_ok(['horizon_days'=>$horizon,'window_start'=>$windowStart->format('Y-m-d'),'window_end'=>$windowEnd->format('Y-m-d'),'snapshot'=>$snapshot,'signals'=>$signals->fetchAll(PDO::FETCH_ASSOC),'upcoming'=>$upcoming->fetchAll(PDO::FETCH_ASSOC)]);
