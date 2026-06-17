<?php
declare(strict_types=1);
require_once __DIR__ . '/_commerce.php';
mg_require_method('GET');
$user = mg_require_api_user();
$status = mg_account_scope(trim((string)($_GET['status'] ?? 'all')), ['all','pending','verified','redeemed','expired','cancelled','locked'], 'all');
$limit = mg_account_limit($_GET['limit'] ?? 50);
$pdo = mg_db();
mg_ok(['status'=>$status,'claims'=>mg_account_claims($pdo,(int)$user['id'],$status,$limit)]);
