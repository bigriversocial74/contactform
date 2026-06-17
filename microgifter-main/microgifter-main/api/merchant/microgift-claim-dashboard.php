<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/microgifts/_stage10e_operations.php';
mg_require_method('GET');
$user=mg_require_permission('merchant.claim_dashboard.view');
$days=max(1,min((int)($_GET['days']??30),365));
mg_ok(mg_claim_dashboard(mg_db(),(int)$user['id'],$days));
