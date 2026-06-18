<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/commerce/_foundation.php';
mg_require_method('GET');
$user=mg_require_api_user();
if(!in_array('super_admin',$user['roles']??[],true))mg_fail('Permission denied.',403);
$pdo=mg_db();$status=trim((string)($_GET['status']??''));$q=trim((string)($_GET['q']??''));
$sql='SELECT o.id,o.public_id,o.buyer_user_id,o.merchant_user_id,o.currency,o.total_cents,o.payment_status,o.fulfillment_status,o.source_type,o.source_reference,o.created_at FROM commerce_orders o WHERE 1=1';$params=[];
if($status!==''){$sql.=' AND (o.payment_status=? OR o.fulfillment_status=?)';$params[]=$status;$params[]=$status;}
if($q!==''){$sql.=' AND (o.public_id LIKE ? OR o.source_reference LIKE ?)';$like='%'.$q.'%';$params[]=$like;$params[]=$like;}
$sql.=' ORDER BY o.created_at DESC,o.id DESC LIMIT 500';$stmt=$pdo->prepare($sql);$stmt->execute($params);$orders=[];foreach($stmt->fetchAll() as $order)$orders[]=mg_order_payload($pdo,$order);mg_ok(['orders'=>$orders]);
