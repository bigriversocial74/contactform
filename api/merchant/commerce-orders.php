<?php
declare(strict_types=1);

require_once __DIR__ . '/_orders.php';

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.payments.view');
$pdo = mg_db();

mg_ok(mg_merchant_orders_list($pdo, (int) $user['id'], $_GET));
