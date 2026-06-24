<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('GET');
$pdo = mg_db();
try {
    $stmt = $pdo->query("SELECT public_id,account_user_id,actor_user_id,actor_type,entry_type,delta,balance_after,source_type,source_id,reference,reason_code,note,metadata_json,created_at FROM stamp_ledger_entries WHERE entry_type='void' AND source_type IN ('delivery_failure_void','failed_send_void') ORDER BY created_at DESC,id DESC LIMIT 100");
    $rows = array_map('mg_stamp_format_entry', $stmt->fetchAll());
    mg_ok(['failures'=>$rows,'count'=>count($rows)]);
} catch (Throwable $error) {
    mg_security_log('warning','stamps.delivery_failure_report_unavailable','Delivery failure report unavailable.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['failures'=>[], 'count'=>0, 'schema_ready'=>false], 'Delivery failure report unavailable until migration is installed.');
}
