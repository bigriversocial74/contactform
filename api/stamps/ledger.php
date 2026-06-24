<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('GET');
$pdo = mg_db();
$accountUserId = (int)$user['id'];
if (isset($_GET['account_user_id']) && $_GET['account_user_id'] !== '') {
    if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) {
        mg_fail('Permission denied.', 403);
    }
    $accountUserId = max(1, (int)$_GET['account_user_id']);
}
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
mg_ok(mg_stamp_ledger_payload($pdo, $accountUserId, $limit));
