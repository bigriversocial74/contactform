<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_claim_operations.php';

mg_require_method('GET');
$user = mg_require_permission('merchant.location_claim.history');
$pdo = mg_db();

mg_ok([
    'merchant_user_id'=>(int)$user['id'],
    'items'=>mg_claim_history($pdo,(int)$user['id'],[
        'result'=>$_GET['result'] ?? '',
        'location_id'=>$_GET['location_id'] ?? '',
        'limit'=>$_GET['limit'] ?? 100,
    ]),
]);
