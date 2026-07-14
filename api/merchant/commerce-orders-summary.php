<?php
declare(strict_types=1);

require_once __DIR__ . '/_orders.php';

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.payments.view');
$pdo = mg_db();

mg_ok([
    'summary' => mg_merchant_orders_summary($pdo, (int) $user['id']),
]);
