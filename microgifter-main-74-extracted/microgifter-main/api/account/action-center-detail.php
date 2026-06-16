<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

mg_require_method('GET');
$user=mg_require_api_user();
$publicId=trim((string)($_GET['id']??''));
if($publicId==='')mg_fail('Action Center item id is required.',422);
$item=mg_action_center_detail(mg_db(),(int)$user['id'],$publicId);
if($item===null)mg_fail('Action Center item not found.',404);
mg_ok(['item'=>$item]);
