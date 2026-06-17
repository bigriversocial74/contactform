<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/entitlements/_entitlements.php';
mg_require_method('GET');
$user=mg_require_api_user();
$status=trim((string)($_GET['status']??''));
$pdo=mg_db();
$sql="SELECT e.public_id entitlement_id,e.entitlement_type,e.status,e.starts_at,e.expires_at,e.revocation_reason,pi.public_id pppm_item_id,pi.title_snapshot,pi.status pppm_status,a.public_id asset_id,a.asset_type,a.original_filename,a.mime_type,a.byte_size,cpv.public_id product_version_id,cpv.title product_title FROM entitlements e INNER JOIN pppm_items pi ON pi.id=e.pppm_item_id INNER JOIN catalog_assets a ON a.id=e.asset_id LEFT JOIN catalog_product_versions cpv ON cpv.id=e.product_version_id WHERE e.entitled_user_id=?";
$params=[(int)$user['id']];
if($status!==''){$sql.=' AND e.status=?';$params[]=$status;}
$sql.=' ORDER BY e.updated_at DESC,e.id DESC LIMIT 200';
$stmt=$pdo->prepare($sql);$stmt->execute($params);
mg_ok(['entitlements'=>$stmt->fetchAll()]);
