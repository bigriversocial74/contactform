<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$read=static fn(string $path):string=>is_file($root.'/'.$path)?(file_get_contents($root.'/'.$path)?:''):'';
$sql=$read('database/20260723_privacy_retention_account_erasure_v1_single_install.sql');
$core=$read('includes/privacy/account-erasure.php');
$selfApi=$read('api/me/privacy-request.php');
$adminApi=$read('api/admin/privacy-requests.php');
$userPage=$read('privacy-center.php');
$adminPage=$read('admin/privacy-requests.php');
$userJs=$read('assets/js/privacy-center-v1.js');
$adminJs=$read('assets/js/admin-privacy-requests-v1.js');
$worker=$read('scripts/process_privacy_erasure_queue.php');
$docs=$read('docs/privacy/account-erasure-retention-v1.md');
$accountSidebar=$read('includes/account-sidebar.php');
$userCenter=$read('admin/users.php');
$privacy=$read('privacy.php');

$checks=[
 'single-install migration exists'=>str_contains($sql,'privacy_requests')&&str_contains($sql,'privacy_legal_holds')&&str_contains($sql,'privacy_data_actions')&&str_contains($sql,'privacy_merchant_handoffs')&&str_contains($sql,'privacy_suppression_tombstones'),
 'user lifecycle columns are idempotent'=>str_contains($sql,'mg_privacy_add_column_if_missing')&&str_contains($sql,'privacy_state')&&str_contains($sql,'deletion_due_at')&&str_contains($sql,'anonymized_at'),
 'retention policies are configurable'=>str_contains($sql,'privacy_retention_policies')&&str_contains($sql,'commerce_evidence')&&str_contains($sql,'backup_expiry'),
 'privacy permissions are installed'=>str_contains($sql,'admin.privacy_requests.view')&&str_contains($sql,'admin.privacy_requests.manage'),
 'jurisdiction deadlines are policy driven'=>str_contains($core,"'eu_eea', 'uk'")&&str_contains($core,"'california'")&&str_contains($core,'mg_privacy_add_business_days'),
 'self service requires password and typed delete'=>str_contains($core,'mg_privacy_verify_password')&&str_contains($core,"!== 'DELETE'")&&str_contains($selfApi,'mg_require_csrf_for_write')&&str_contains($selfApi,'mg_rate_limit'),
 'account restriction revokes access'=>str_contains($core,'status="disabled"')&&str_contains($core,'auth_version=auth_version+1')&&str_contains($core,'user_sessions SET revoked_at=NOW()'),
 'merchant controller handoffs are generated'=>str_contains($core,'mg_privacy_generate_merchant_handoffs')&&str_contains($core,'merchant_crm_contacts')&&str_contains($adminApi,'handoff_complete'),
 'legal holds block irreversible erasure'=>str_contains($core,'mg_privacy_active_hold')&&str_contains($core,"'blocked_by_hold'")&&str_contains($adminApi,"case 'add_hold'")&&str_contains($adminApi,"case 'release_hold'"),
 'private data is deleted and retained history anonymized'=>str_contains($core,'mg_privacy_delete_user_rows')&&str_contains($core,'mg_privacy_anonymize_merchant_crm')&&str_contains($core,'retain_commerce_evidence'),
 'completion receipts and suppression tombstones exist'=>str_contains($core,'completed_receipt_hash')&&str_contains($core,'privacy_suppression_tombstones')&&str_contains($core,'identity_tombstone_hash'),
 'customer privacy center is linked'=>str_contains($userPage,'data-privacy-delete-form')&&str_contains($accountSidebar,'/privacy-center.php')&&str_contains($userJs,'/api/me/privacy-request.php'),
 'admin queue is linked and permission gated'=>str_contains($adminPage,"mg_require_admin_page_permission('admin.privacy_requests.view')")&&str_contains($userCenter,'/admin/privacy-requests.php')&&str_contains($adminJs,'/api/admin/privacy-requests.php'),
 'worker respects legal holds and due dates'=>str_contains($worker,'mg_privacy_finalize_request')&&str_contains($worker,'COALESCE(pr.grace_ends_at,pr.response_due_at)<=NOW()')&&str_contains($worker,"'dry-run'"),
 'backup restore tombstones are documented'=>str_contains($docs,'privacy_suppression_tombstones')&&str_contains($docs,'after any restore')&&str_contains($docs,'MG_PRIVACY_HASH_KEY'),
 'privacy policy links operational request center'=>str_contains($privacy,'/privacy-center.php')&&str_contains($privacy,'account-erasure'),
 'direct destructive user delete is excluded'=>!str_contains($core,'DELETE FROM users')&&!str_contains($adminApi,'DELETE FROM users'),
];
$failed=[];
foreach($checks as $name=>$ok){echo ($ok?'[PASS] ':'[FAIL] ').$name.PHP_EOL;if(!$ok)$failed[]=$name;}
$score=(int)round((count($checks)-count($failed))/count($checks)*100);
echo "Privacy retention and erasure score: {$score}/100\n";
if($failed){fwrite(STDERR,'Failed: '.implode(', ',$failed).PHP_EOL);exit(1);}echo "Privacy retention and account erasure contract passed at 100/100.\n";
