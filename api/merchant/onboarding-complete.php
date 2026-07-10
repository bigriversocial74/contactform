<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('POST');
$user=mg_merchant_require_permission('merchant.workspace.manage');
$input=mg_input();mg_require_csrf_for_write($input);
$pdo=mg_db();$workspace=mg_merchant_ensure_workspace($pdo,$user);$merchantId=(int)$user['id'];$workspaceId=(int)$workspace['id'];
$checks=[
 'business_profile'=>trim((string)($workspace['display_name']??''))!==''&&trim((string)($workspace['support_email']??''))!=='',
 'first_location'=>(int)$pdo->query('SELECT 0')->fetchColumn()===0,
];
$stmt=$pdo->prepare("SELECT COUNT(*) FROM merchant_locations WHERE merchant_user_id=? AND workspace_id=? AND status='active'");$stmt->execute([$merchantId,$workspaceId]);$checks['first_location']=(int)$stmt->fetchColumn()>0;
$stmt=$pdo->prepare("SELECT COUNT(*) FROM reward_templates WHERE merchant_user_id=? AND status='active'");$stmt->execute([$merchantId]);$checks['reward_template']=(int)$stmt->fetchColumn()>0;
$stmt=$pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND campaign_type='loyalty_quest' AND status='active'");$stmt->execute([$merchantId]);$checks['active_loyalty_quest']=(int)$stmt->fetchColumn()>0;
$missing=array_keys(array_filter($checks,static fn(bool $ok):bool=>!$ok));
if($missing!==[])mg_fail('Merchant onboarding is incomplete.',422,['missing'=>$missing]);
$pdo->beginTransaction();
try{
 $pdo->prepare("UPDATE merchant_workspaces SET status='active',eligibility_status='approved',onboarding_percent=100,updated_at=NOW() WHERE id=? AND merchant_user_id=?")->execute([$workspaceId,$merchantId]);
 $pdo->prepare("UPDATE merchant_onboarding_steps SET status='completed',completed_at=COALESCE(completed_at,NOW()),completed_by_user_id=COALESCE(completed_by_user_id,?),updated_at=NOW() WHERE workspace_id=?")->execute([$merchantId,$workspaceId]);
 $pdo->commit();mg_audit('merchant.onboarding_completed','merchant_workspace',['workspace_id'=>(string)$workspace['public_id']],$merchantId);
 mg_ok(['workspace_id'=>(string)$workspace['public_id'],'onboarding_percent'=>100,'status'=>'active'],'Merchant onboarding completed.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to complete merchant onboarding.',500);}
