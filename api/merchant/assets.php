<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('GET');
$user=mg_require_permission('catalog.assets.manage');
$type=trim((string)($_GET['type']??'all'));$status=trim((string)($_GET['status']??'all'));$q=trim((string)($_GET['q']??''));
$sql='SELECT a.public_id,a.asset_type,a.storage_provider,a.original_filename,a.mime_type,a.byte_size,a.width_px,a.height_px,a.duration_ms,a.status,a.created_at,a.updated_at,COUNT(DISTINCT pva.product_version_id) usage_count FROM catalog_assets a LEFT JOIN catalog_product_version_assets pva ON pva.asset_id=a.id WHERE a.owner_user_id=?';$params=[(int)$user['id']];
if(in_array($type,['image','audio','video','document','download','qr_template','other'],true)){$sql.=' AND a.asset_type=?';$params[]=$type;}
if(in_array($status,['pending','processing','ready','failed','rejected','retired'],true)){$sql.=' AND a.status=?';$params[]=$status;}
if($q!==''){$sql.=' AND a.original_filename LIKE ?';$params[]='%'.$q.'%';}
$sql.=' GROUP BY a.id ORDER BY a.created_at DESC,a.id DESC';$stmt=mg_db()->prepare($sql);$stmt->execute($params);mg_ok(['assets'=>$stmt->fetchAll()]);
