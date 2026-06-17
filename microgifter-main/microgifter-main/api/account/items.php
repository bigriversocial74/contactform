<?php
declare(strict_types=1);
require_once __DIR__ . '/_commerce.php';
mg_require_method('GET');
$user = mg_require_api_user();
$scope = mg_account_scope(trim((string)($_GET['scope'] ?? 'owned')), ['owned','purchased','sent','received','redeemed'], 'owned');
$limit = mg_account_limit($_GET['limit'] ?? 50);
$pdo = mg_db();
mg_ok(['scope'=>$scope,'items'=>mg_account_items($pdo,(int)$user['id'],$scope,$limit)]);
