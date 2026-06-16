<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('GET');
$user=mg_require_permission('merchant.pppm.view');
$pdo=mg_db();
$status=trim((string)($_GET['status']??'all'));
$source=trim((string)($_GET['source']??'all'));
$q=trim((string)($_GET['q']??''));
$sql="SELECT i.public_id,i.item_type,i.funding_type,i.source_reference,i.source_line_reference,i.title_snapshot,i.value_cents_snapshot,i.currency_snapshot,i.status,i.issued_at,i.sent_at,i.delivered_at,i.viewed_at,i.claimed_at,i.redeemed_at,i.expires_at,i.updated_at,i.recipient_external_id,s.source_type,s.provider,r.public_id request_id,r.title request_title,COUNT(DISTINCT e.id) event_count,COUNT(DISTINCT d.id) delivery_count,COUNT(DISTINCT c.id) case_count FROM pppm_items i INNER JOIN pppm_sources s ON s.id=i.source_id INNER JOIN pppm_issuance_requests r ON r.id=i.issuance_request_id LEFT JOIN pppm_item_events e ON e.pppm_item_id=i.id LEFT JOIN pppm_deliveries d ON d.pppm_item_id=i.id LEFT JOIN merchant_pppm_cases c ON c.pppm_item_id=i.id AND c.status NOT IN ('resolved','closed') WHERE i.merchant_user_id=?";
$params=[(int)$user['id']];
if($status!=='all'){$sql.=' AND i.status=?';$params[]=$status;}
if($source!=='all'){$sql.=' AND s.source_type=?';$params[]=$source;}
if($q!==''){$sql.=' AND (i.public_id LIKE ? OR i.source_reference LIKE ? OR i.title_snapshot LIKE ? OR i.recipient_external_id LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like,$like);}
$sql.=' GROUP BY i.id,s.id,r.id ORDER BY i.updated_at DESC,i.id DESC';
$stmt=$pdo->prepare($sql);$stmt->execute($params);
$counts=$pdo->prepare("SELECT COUNT(*) total,SUM(status IN ('created','available','assigned','scheduled')) pending,SUM(status IN ('sent','delivered','viewed')) delivery_flow,SUM(status IN ('claim_pending','verified')) claims,SUM(status='redeemed') redeemed,SUM(status IN ('expired','cancelled','refunded','voided')) exceptions FROM pppm_items WHERE merchant_user_id=?");
$counts->execute([(int)$user['id']]);
$sources=$pdo->prepare('SELECT DISTINCT s.source_type FROM pppm_items i INNER JOIN pppm_sources s ON s.id=i.source_id WHERE i.merchant_user_id=? ORDER BY s.source_type');
$sources->execute([(int)$user['id']]);
mg_ok(['items'=>$stmt->fetchAll(),'counts'=>$counts->fetch()?:[],'sources'=>array_column($sources->fetchAll(),'source_type')]);
