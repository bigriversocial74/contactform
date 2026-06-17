<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/microgifts/_operations.php';
mg_require_method('GET');
$user=mg_require_api_user();
$scope=trim((string)($_GET['scope']??'owned'));
$limit=max(1,min((int)($_GET['limit']??50),100));
mg_ok(['scope'=>$scope,'items'=>mg_microgift_account_items(mg_db(),(int)$user['id'],$scope,$limit)]);
