<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission($method === 'GET' ? 'merchant.workspace.view' : 'merchant.workspace.manage');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
if ($method === 'GET') mg_ok(['workspace'=>$workspace]);
if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input(); mg_require_csrf_for_write($input);
$displayName = trim((string)($input['display_name']??''));
$legalName = trim((string)($input['legal_name']??''))?:null;
$businessType = trim((string)($input['business_type']??''))?:null;
$website = trim((string)($input['website_url']??''))?:null;
$supportEmail = strtolower(trim((string)($input['support_email']??'')))?:null;
$supportPhone = trim((string)($input['support_phone']??''))?:null;
$currency = strtoupper(trim((string)($input['default_currency']??'USD')));
$timezone = trim((string)($input['timezone']??'UTC'));
if ($displayName === '' || mb_strlen($displayName)>180) mg_fail('Invalid merchant display name.',422);
if ($website && !filter_var($website,FILTER_VALIDATE_URL)) mg_fail('Invalid website URL.',422);
if ($supportEmail && !filter_var($supportEmail,FILTER_VALIDATE_EMAIL)) mg_fail('Invalid support email.',422);
if (!preg_match('/^[A-Z]{3}$/',$currency)) mg_fail('Invalid currency.',422);
if (!in_array($timezone,timezone_identifiers_list(),true)) mg_fail('Invalid timezone.',422);
$pdo->beginTransaction();
try {
  $pdo->prepare('UPDATE merchant_workspaces SET display_name=?,legal_name=?,business_type=?,website_url=?,support_email=?,support_phone=?,default_currency=?,timezone=?,eligibility_status=CASE WHEN eligibility_status=\'not_started\' THEN \'pending\' ELSE eligibility_status END,updated_at=NOW() WHERE id=?')
    ->execute([$displayName,$legalName,$businessType,$website,$supportEmail,$supportPhone,$currency,$timezone,(int)$workspace['id']]);
  $pdo->prepare("UPDATE merchant_onboarding_steps SET status='completed',completed_at=NOW(),completed_by_user_id=?,updated_at=NOW() WHERE workspace_id=? AND step_key='business_profile'")->execute([(int)$user['id'],(int)$workspace['id']]);
  $pdo->prepare("UPDATE merchant_onboarding_steps SET status='available',updated_at=NOW() WHERE workspace_id=? AND step_key='eligibility' AND status='locked'")->execute([(int)$workspace['id']]);
  $percent = mg_merchant_recalculate_onboarding($pdo,(int)$workspace['id']);
  $pdo->commit();
  mg_audit('merchant.workspace_updated','merchant_workspace',['workspace_id'=>$workspace['public_id']],(int)$user['id']);
  mg_ok(['workspace_id'=>$workspace['public_id'],'onboarding_percent'=>$percent],'Merchant settings saved.');
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); mg_fail('Unable to save merchant settings.',500); }
