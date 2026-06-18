<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('GET');
$user=mg_require_permission('merchant.entitlements.view');
$pdo=mg_db();
$status=trim((string)($_GET['status']??''));
$sql="SELECT e.status,e.entitlement_type,a.public_id asset_id,a.original_filename,cpv.public_id product_version_id,cpv.title product_title,COUNT(*) entitlement_count,COUNT(DISTINCT e.entitled_user_id) entitled_user_count,MAX(e.updated_at) updated_at FROM entitlements e INNER JOIN catalog_assets a ON a.id=e.asset_id LEFT JOIN catalog_product_versions cpv ON cpv.id=e.product_version_id WHERE e.merchant_user_id=?";
$params=[(int)$user['id']];
if($status!==''){$sql.=' AND e.status=?';$params[]=$status;}
$sql.=' GROUP BY e.status,e.entitlement_type,a.id,cpv.id ORDER BY updated_at DESC LIMIT 200';
$stmt=$pdo->prepare($sql);$stmt->execute($params);
$totals=$pdo->prepare("SELECT COUNT(*) total_count,SUM(status='active') active_count,SUM(status='suspended') suspended_count,SUM(status='revoked') revoked_count FROM entitlements WHERE merchant_user_id=?");
$totals->execute([(int)$user['id']]);
mg_ok(['summary'=>$totals->fetch(),'items'=>$stmt->fetchAll()]);
