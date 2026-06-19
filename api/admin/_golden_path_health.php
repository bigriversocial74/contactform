<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_fulfillment.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

function mg_admin_golden_path_rows(PDO $pdo,string $sql,array $params=[]): array
{
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}

function mg_admin_golden_path_finding(string $key,string $severity,string $type,string $reference,?string $repair,array $evidence): array
{
    return [
        'finding_key'=>$key,'severity'=>$severity,'subject_type'=>$type,
        'subject_reference'=>$reference,'repair_action'=>$repair,'evidence'=>$evidence,
    ];
}

function mg_admin_golden_path_scan(PDO $pdo,int $limit=25): array
{
    $limit=max(1,min(100,$limit));
    $findings=[];

    $rows=mg_admin_golden_path_rows($pdo,"SELECT public_id,payment_status,fulfillment_status,updated_at FROM commerce_orders WHERE payment_status='paid' AND fulfillment_status<>'issued' ORDER BY updated_at,id LIMIT {$limit}");
    foreach($rows as $row)$findings[]=mg_admin_golden_path_finding('paid_order_fulfillment_incomplete','critical','order',(string)$row['public_id'],'retry_order_fulfillment',$row);

    $rows=mg_admin_golden_path_rows($pdo,"SELECT o.public_id,SUM(oi.quantity) expected_count,COUNT(mi.id) actual_count FROM commerce_orders o INNER JOIN commerce_order_items oi ON oi.order_id=o.id LEFT JOIN microgift_instances mi ON mi.commerce_order_item_id=oi.id WHERE o.payment_status='paid' GROUP BY o.id,o.public_id HAVING expected_count<>actual_count ORDER BY o.id LIMIT {$limit}");
    foreach($rows as $row)$findings[]=mg_admin_golden_path_finding('paid_order_microgift_count_mismatch','high','order',(string)$row['public_id'],'retry_order_fulfillment',$row);

    $rows=mg_admin_golden_path_rows($pdo,"SELECT mi.public_id,mi.status,mi.owner_user_id FROM microgift_instances mi LEFT JOIN microgift_claims c ON c.instance_id=mi.id AND c.status='completed' WHERE mi.status IN ('claimed','redeemable','redeemed') AND c.id IS NULL ORDER BY mi.updated_at,mi.id LIMIT {$limit}");
    foreach($rows as $row)$findings[]=mg_admin_golden_path_finding('microgift_claim_missing','high','microgift',(string)$row['public_id'],null,$row);

    $rows=mg_admin_golden_path_rows($pdo,"SELECT mi.public_id,mi.status,r.public_id redemption_id FROM microgift_instances mi LEFT JOIN microgift_redemptions r ON r.instance_id=mi.id AND r.status='completed' WHERE mi.status='redeemed' AND r.id IS NULL ORDER BY mi.updated_at,mi.id LIMIT {$limit}");
    foreach($rows as $row)$findings[]=mg_admin_golden_path_finding('microgift_redemption_missing','critical','microgift',(string)$row['public_id'],null,$row);

    $rows=mg_admin_golden_path_rows($pdo,"SELECT mi.public_id,mi.status microgift_status,p.status pppm_status,p.public_id pppm_id FROM microgift_instances mi INNER JOIN pppm_items p ON p.id=mi.pppm_item_id WHERE (mi.status='redeemed' AND p.status<>'redeemed') OR (mi.status IN ('issued','delivered','claim_pending','claimed','redeemable') AND p.status NOT IN ('available','assigned')) ORDER BY mi.updated_at,mi.id LIMIT {$limit}");
    foreach($rows as $row)$findings[]=mg_admin_golden_path_finding('microgift_pppm_status_mismatch','high','microgift',(string)$row['public_id'],null,$row);

    $rows=mg_admin_golden_path_rows($pdo,"SELECT mi.public_id,mi.status,mi.owner_user_id FROM microgift_instances mi WHERE mi.owner_user_id IS NOT NULL AND mi.status IN ('issued','delivered','claim_pending','claimed','redeemable','redeemed') AND NOT EXISTS (SELECT 1 FROM microgift_inbox_items ac WHERE ac.instance_id=mi.id AND ac.user_id=mi.owner_user_id AND ac.archived_at IS NULL) ORDER BY mi.updated_at,mi.id LIMIT {$limit}");
    foreach($rows as $row)$findings[]=mg_admin_golden_path_finding('action_center_projection_missing','warning','microgift',(string)$row['public_id'],'reproject_microgift',$row);

    $rows=mg_admin_golden_path_rows($pdo,"SELECT g.public_id,SUM(CASE WHEN e.entry_type='debit' THEN e.amount_cents ELSE 0 END) debit_cents,SUM(CASE WHEN e.entry_type='credit' THEN e.amount_cents ELSE 0 END) credit_cents FROM ledger_transaction_groups g INNER JOIN ledger_entries e ON e.transaction_group_id=g.id GROUP BY g.id,g.public_id HAVING debit_cents<>credit_cents ORDER BY g.id DESC LIMIT {$limit}");
    foreach($rows as $row)$findings[]=mg_admin_golden_path_finding('ledger_group_unbalanced','critical','ledger',(string)$row['public_id'],null,$row);

    $counts=['info'=>0,'warning'=>0,'high'=>0,'critical'=>0,'repairable'=>0];
    foreach($findings as $finding){$counts[$finding['severity']]++;if($finding['repair_action']!==null)$counts['repairable']++;}
    $status=$counts['critical']>0?'critical':(($counts['high']+$counts['warning'])>0?'warning':'healthy');
    return ['status'=>$status,'finding_count'=>count($findings),'counts'=>$counts,'findings'=>$findings,'limit'=>$limit,'generated_at'=>gmdate('c')];
}

function mg_admin_golden_path_retry_order(PDO $pdo,string $orderPublicId,int $actorUserId): array
{
    $stmt=$pdo->prepare("SELECT id,payment_status,fulfillment_status FROM commerce_orders WHERE public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$orderPublicId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new RuntimeException('Commerce order not found.');
    if((string)$order['payment_status']!=='paid')throw new RuntimeException('Only paid orders can be repaired.');
    $pppm=mg_payment_issue_order_pppm($pdo,(int)$order['id'],$actorUserId);
    $microgifts=mg_payment_issue_order_microgifts($pdo,(int)$order['id'],$actorUserId);
    $updated=$pdo->prepare('SELECT payment_status,fulfillment_status FROM commerce_orders WHERE id=?');
    $updated->execute([(int)$order['id']]);
    return ['order_id'=>$orderPublicId,'pppm'=>$pppm,'microgifts'=>$microgifts,'order'=>$updated->fetch(PDO::FETCH_ASSOC)];
}

function mg_admin_golden_path_reproject(PDO $pdo,string $instancePublicId): array
{
    $stmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$instancePublicId]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Microgift instance not found.');
    return ['instance_id'=>$instancePublicId,'projection'=>mg_action_center_project_lifecycle($pdo,$instance)];
}
