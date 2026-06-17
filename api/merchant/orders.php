<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('GET');
$user=mg_require_permission('merchant.pppm.view');
$pdo=mg_db();
$status=trim((string)($_GET['status']??'all'));
$q=trim((string)($_GET['q']??''));
$sql="SELECT COALESCE(NULLIF(r.source_reference,''),r.public_id) order_reference,r.public_id request_id,r.source_line_reference,r.funding_type,r.currency,r.status request_status,r.requested_at,r.completed_at,s.source_type,s.provider,s.name source_name,COUNT(i.id) item_count,SUM(i.value_cents_snapshot) total_value_cents,SUM(i.status='redeemed') redeemed_count,SUM(i.status IN ('sent','delivered','viewed','claim_pending','verified')) active_count,SUM(i.status IN ('cancelled','refunded','voided','expired')) exception_count FROM pppm_issuance_requests r INNER JOIN pppm_sources s ON s.id=r.source_id LEFT JOIN pppm_items i ON i.issuance_request_id=r.id WHERE r.merchant_user_id=?";
$params=[(int)$user['id']];
if($status!=='all'){$sql.=' AND r.status=?';$params[]=$status;}
if($q!==''){$sql.=' AND (r.source_reference LIKE ? OR r.public_id LIKE ? OR r.title LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
$sql.=' GROUP BY r.id,s.id ORDER BY r.requested_at DESC,r.id DESC';
$stmt=$pdo->prepare($sql);$stmt->execute($params);
$counts=$pdo->prepare("SELECT COUNT(*) total,SUM(status='pending') pending,SUM(status='issued') issued,SUM(status='failed') failed,SUM(status='cancelled') cancelled FROM pppm_issuance_requests WHERE merchant_user_id=?");
$counts->execute([(int)$user['id']]);
mg_ok(['orders'=>$stmt->fetchAll(),'counts'=>$counts->fetch()?:[]]);
