<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/microgifts/_operations.php';
mg_require_method('GET');
$user=mg_require_permission('microgift.operations.view');
$pdo=mg_db();
$days=max(1,min((int)($_GET['days']??30),365));
$status=trim((string)($_GET['status']??''));
$sql="SELECT i.public_id instance_id,i.status,i.title_snapshot,i.currency,i.face_value_cents,i.issued_at,i.claimed_at,i.redeemed_at,i.expires_at,
             t.public_id template_id,t.name template_name,v.version_number,
             c.public_id claim_id,c.claimant_user_id,c.completed_at claim_completed_at,
             r.public_id redemption_id,r.claimant_user_id,r.location_reference,r.amount_cents,r.redeemed_at redemption_completed_at
      FROM microgift_instances i
      INNER JOIN microgift_templates t ON t.id=i.template_id
      INNER JOIN microgift_template_versions v ON v.id=i.template_version_id
      LEFT JOIN microgift_claims c ON c.instance_id=i.id AND c.status='completed'
      LEFT JOIN microgift_redemptions r ON r.instance_id=i.id AND r.status='completed'
      WHERE t.owner_user_id=?";
$params=[(int)$user['id']];
if($status!==''){$sql.=' AND i.status=?';$params[]=$status;}
$sql.=' ORDER BY i.updated_at DESC,i.id DESC LIMIT 200';
$stmt=$pdo->prepare($sql);$stmt->execute($params);
mg_ok(['summary'=>mg_microgift_merchant_summary($pdo,(int)$user['id'],$days),'items'=>$stmt->fetchAll()]);
