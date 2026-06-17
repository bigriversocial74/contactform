<?php
declare(strict_types=1);
require_once __DIR__ . '/_tips.php';
mg_require_method('GET');
$user=mg_require_permission('tips.view_own');
$direction=trim((string)($_GET['direction']??'received'));
if(!in_array($direction,['received','sent'],true))mg_fail('Invalid tip direction.',422);
$limit=max(1,min((int)($_GET['limit']??50),100));
$field=$direction==='received'?'recipient_user_id':'sender_user_id';
$stmt=mg_db()->prepare("SELECT public_id,target_type,target_reference,amount_cents,fee_cents,net_cents,currency,funding_type,status,created_at,posted_at,reversed_at FROM tips WHERE {$field}=? ORDER BY id DESC LIMIT {$limit}");
$stmt->execute([(int)$user['id']]);
mg_ok(['direction'=>$direction,'tips'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
