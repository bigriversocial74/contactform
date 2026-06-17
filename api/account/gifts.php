<?php
declare(strict_types=1);
require_once __DIR__ . '/_commerce.php';
mg_require_method('GET');
$user = mg_require_api_user();
$scope = mg_account_scope(trim((string)($_GET['scope'] ?? 'received')), ['sent','received'], 'received');
$limit = mg_account_limit($_GET['limit'] ?? 50);
$pdo = mg_db();
mg_ok(['scope'=>$scope,'gifts'=>mg_account_gifts($pdo,(int)$user['id'],$scope,$limit)]);
