<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
require_once dirname(__DIR__).'/api/db.php';
$pdo=mg_db();
$date=$argv[1]??gmdate('Y-m-d');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){fwrite(STDERR,"Invalid date.\n");exit(1);}
$sql="INSERT INTO microgift_daily_metrics
(metric_date,merchant_user_id,template_id,issued_count,claimed_count,redeemed_count,expired_count,cancelled_count,revoked_count,face_value_cents,redeemed_value_cents,unique_recipients,unique_locations,created_at,updated_at)
SELECT ?,t.owner_user_id,i.template_id,
COUNT(*),SUM(i.status IN ('claimed','redeemable','redeemed')),SUM(i.status='redeemed'),SUM(i.status='expired'),SUM(i.status='cancelled'),SUM(i.status='revoked'),
COALESCE(SUM(i.face_value_cents),0),COALESCE(SUM(CASE WHEN r.status='completed' THEN r.amount_cents ELSE 0 END),0),
COUNT(DISTINCT COALESCE(i.recipient_user_id,c.claimant_user_id)),COUNT(DISTINCT CASE WHEN r.status='completed' THEN r.location_reference END),NOW(),NOW()
FROM microgift_instances i
INNER JOIN microgift_templates t ON t.id=i.template_id
LEFT JOIN microgift_claims c ON c.instance_id=i.id AND c.status='completed'
LEFT JOIN microgift_redemptions r ON r.instance_id=i.id AND r.status='completed'
WHERE DATE(i.created_at)=?
GROUP BY t.owner_user_id,i.template_id
ON DUPLICATE KEY UPDATE issued_count=VALUES(issued_count),claimed_count=VALUES(claimed_count),redeemed_count=VALUES(redeemed_count),expired_count=VALUES(expired_count),cancelled_count=VALUES(cancelled_count),revoked_count=VALUES(revoked_count),face_value_cents=VALUES(face_value_cents),redeemed_value_cents=VALUES(redeemed_value_cents),unique_recipients=VALUES(unique_recipients),unique_locations=VALUES(unique_locations),updated_at=NOW()";
$stmt=$pdo->prepare($sql);$stmt->execute([$date,$date]);
echo "Stage 9D Microgift metrics aggregated for {$date}.\n";
