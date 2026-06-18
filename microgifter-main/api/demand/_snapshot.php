<?php
declare(strict_types=1);

require_once __DIR__ . '/_demand.php';
require_once __DIR__ . '/_window.php';

function mg_demand_snapshot_scopes(PDO $pdo,int $horizonDays,DateTimeImmutable $asOf): array
{
    [$from,$to]=mg_demand_snapshot_window($asOf,$horizonDays);
    $stmt=$pdo->prepare('SELECT DISTINCT merchant_user_id,location_id,product_id FROM purchase_signal_records WHERE '.mg_demand_window_predicate());
    $stmt->execute([$to->format('Y-m-d H:i:s'),$from->format('Y-m-d H:i:s'),$from->format('Y-m-d H:i:s')]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mg_demand_build_windowed_snapshot(PDO $pdo,int $merchantUserId,?int $locationId,?int $productId,int $horizonDays,DateTimeImmutable $asOf): array
{
    [$from,$to,$horizonDays]=mg_demand_snapshot_window($asOf,$horizonDays);
    $where=['merchant_user_id=?',mg_demand_window_predicate()];
    $params=[$merchantUserId,$to->format('Y-m-d H:i:s'),$from->format('Y-m-d H:i:s'),$from->format('Y-m-d H:i:s')];
    if($locationId!==null){$where[]='location_id=?';$params[]=$locationId;}
    if($productId!==null){$where[]='product_id=?';$params[]=$productId;}

    $sql="SELECT COUNT(*) total_count,COUNT(DISTINCT user_id) unique_users,
        SUM(CASE WHEN status='outstanding' THEN 1 ELSE 0 END) outstanding_count,
        SUM(CASE WHEN status='outstanding' THEN quantity ELSE 0 END) outstanding_quantity,
        SUM(CASE WHEN status='outstanding' THEN estimated_value_cents ELSE 0 END) outstanding_value,
        SUM(CASE WHEN status='outstanding' AND signal_type='committed_demand' THEN 1 ELSE 0 END) committed_count,
        SUM(CASE WHEN status='outstanding' AND signal_type='committed_demand' THEN estimated_value_cents ELSE 0 END) committed_value,
        SUM(CASE WHEN status='outstanding' AND signal_type IN ('future_visit','repeat_visit') THEN 1 ELSE 0 END) future_visits,
        SUM(CASE WHEN status='redeemed' THEN 1 ELSE 0 END) redeemed_count,
        SUM(CASE WHEN status='redeemed' THEN estimated_value_cents ELSE 0 END) redeemed_value,
        SUM(CASE WHEN status='outstanding' THEN estimated_value_cents*confidence_score ELSE 0 END) weighted_score
        FROM purchase_signal_records WHERE ".implode(' AND ',$where);
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC)?:[];

    $velocity=static function(int $days)use($pdo,$merchantUserId,$locationId,$productId,$from):float{
        $where=["status IN ('outstanding','redeemed')",'merchant_user_id=?','created_at>=?','created_at<?'];
        $params=[$merchantUserId,$from->modify('-'.$days.' day')->format('Y-m-d H:i:s'),$from->format('Y-m-d H:i:s')];
        if($locationId!==null){$where[]='location_id=?';$params[]=$locationId;}
        if($productId!==null){$where[]='product_id=?';$params[]=$productId;}
        $stmt=$pdo->prepare('SELECT COALESCE(SUM(estimated_value_cents*confidence_score),0)/? FROM purchase_signal_records WHERE '.implode(' AND ',$where));
        $stmt->execute(array_merge([$days],$params));
        return (float)$stmt->fetchColumn();
    };

    $outstanding=(int)($row['outstanding_count']??0);$redeemed=(int)($row['redeemed_count']??0);
    $conversion=($outstanding+$redeemed)>0?$redeemed/($outstanding+$redeemed):null;
    $features=['window_start'=>$from->format('Y-m-d'),'window_end'=>$to->format('Y-m-d'),'window_semantics'=>'utc_half_open_overlap','total_signals'=>(int)($row['total_count']??0),'confidence_weighted_value_cents'=>(float)($row['weighted_score']??0)];
    $scopeKey=mg_demand_scope_key($locationId,$productId);

    $pdo->prepare("INSERT INTO demand_scope_snapshots
        (public_id,snapshot_date,horizon_days,merchant_user_id,location_id,product_id,scope_key,outstanding_signal_count,outstanding_quantity,outstanding_value_cents,committed_signal_count,committed_value_cents,future_visit_count,redeemed_signal_count,redeemed_value_cents,unique_users,weighted_demand_score,velocity_7d,velocity_30d,conversion_rate,feature_version,features_json,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'psr_v1',?,NOW())
        ON DUPLICATE KEY UPDATE outstanding_signal_count=VALUES(outstanding_signal_count),outstanding_quantity=VALUES(outstanding_quantity),outstanding_value_cents=VALUES(outstanding_value_cents),committed_signal_count=VALUES(committed_signal_count),committed_value_cents=VALUES(committed_value_cents),future_visit_count=VALUES(future_visit_count),redeemed_signal_count=VALUES(redeemed_signal_count),redeemed_value_cents=VALUES(redeemed_value_cents),unique_users=VALUES(unique_users),weighted_demand_score=VALUES(weighted_demand_score),velocity_7d=VALUES(velocity_7d),velocity_30d=VALUES(velocity_30d),conversion_rate=VALUES(conversion_rate),features_json=VALUES(features_json)")
        ->execute([mg_public_uuid(),$from->format('Y-m-d'),$horizonDays,$merchantUserId,$locationId,$productId,$scopeKey,$outstanding,(float)($row['outstanding_quantity']??0),(int)($row['outstanding_value']??0),(int)($row['committed_count']??0),(int)($row['committed_value']??0),(int)($row['future_visits']??0),$redeemed,(int)($row['redeemed_value']??0),(int)($row['unique_users']??0),(float)($row['weighted_score']??0),$velocity(7),$velocity(30),$conversion,json_encode($features,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);

    $stmt=$pdo->prepare("SELECT * FROM demand_scope_snapshots WHERE snapshot_date=? AND horizon_days=? AND merchant_user_id=? AND scope_key=? AND feature_version='psr_v1' LIMIT 1");
    $stmt->execute([$from->format('Y-m-d'),$horizonDays,$merchantUserId,$scopeKey]);
    $snapshot=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$snapshot)throw new RuntimeException('Demand snapshot was not persisted.');
    return $snapshot;
}
