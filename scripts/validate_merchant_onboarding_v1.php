<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
    'merchant-onboarding.php','includes/merchant-onboarding-view.php','assets/js/merchant-onboarding.js',
    'api/merchant/onboarding-status.php','api/merchant/onboarding-complete.php','signup.php','api/auth/register.php',
    'includes/merchant-provisioning.php','api/subscriptions/_package_billing.php',
    '.github/workflows/merchant-onboarding-validation.yml',
];
$read=static fn(string $path):string=>is_file($root.'/'.$path)?(string)file_get_contents($root.'/'.$path):'';
$checks=[];
foreach($files as $file)$checks[]=['name'=>'file:'.$file,'ok'=>is_file($root.'/'.$file)];

$page=$read('merchant-onboarding.php');
$view=$read('includes/merchant-onboarding-view.php');
$js=$read('assets/js/merchant-onboarding.js');
$status=$read('api/merchant/onboarding-status.php');
$complete=$read('api/merchant/onboarding-complete.php');
$signup=$read('signup.php');
$register=$read('api/auth/register.php');
$provisioning=$read('includes/merchant-provisioning.php');
$billing=$read('api/subscriptions/_package_billing.php');

$checks[]=['name'=>'authenticated merchant shell','ok'=>str_contains($page,"\$merchantView='onboarding'")&&str_contains($page,'includes/merchant-workspace.php')&&str_contains($page,'merchant-onboarding.js')];
$checks[]=['name'=>'guided merchant UX','ok'=>str_contains($view,'Launch your first Loyalty Quest')&&str_contains($view,'data-onboarding-checklist')&&str_contains($js,'Continue:')];
$checks[]=['name'=>'merchant signup preserves package intent','ok'=>str_contains($signup,"['customer', 'merchant']")&&str_contains($signup,'business_name')&&str_contains($signup,'name="selected_plan"')&&str_contains($register,'mg_pending_subscription_plan')];
$checks[]=['name'=>'registration begins as Free Wallet','ok'=>str_contains($register,'Every registration starts as the same Free Wallet identity')&&!str_contains($register,"WHERE slug='merchant'")&&!str_contains($register,'INSERT INTO merchant_workspaces')];
$checks[]=['name'=>'merchant role and workspace activate from package authority','ok'=>str_contains($billing,'mg_platform_account_subscription_grant_merchant_role')&&str_contains($billing,'mg_subscription_provision_merchant_workspace')&&str_contains($provisioning,'merchant_workspaces')&&str_contains($provisioning,'merchant_team_members')];
$checks[]=['name'=>'onboarding steps remain canonically provisioned','ok'=>str_contains($provisioning,'business_profile')&&str_contains($provisioning,'first_location')&&str_contains($provisioning,'payment_readiness')&&str_contains($provisioning,'beta_readiness')];
$checks[]=['name'=>'merchant-scoped readiness','ok'=>substr_count($status,'merchant_user_id')>=4&&str_contains($status,"campaign_type='loyalty_quest'")];
$checks[]=['name'=>'required onboarding gates','ok'=>str_contains($status,'business_profile')&&str_contains($status,'first_location')&&str_contains($status,'reward_template')&&str_contains($status,'loyalty_quest')&&str_contains($status,'launch')];
$checks[]=['name'=>'completion enforcement','ok'=>str_contains($complete,'Merchant onboarding is incomplete.')&&str_contains($complete,"onboarding_percent=100")&&str_contains($complete,"eligibility_status='approved'")];
$checks[]=['name'=>'csrf and permissions','ok'=>str_contains($complete,'mg_require_csrf_for_write')&&str_contains($complete,'merchant.workspace.manage')&&str_contains($status,'merchant.workspace.view')];
$checks[]=['name'=>'audit trail','ok'=>str_contains($complete,'merchant.onboarding_completed')&&str_contains($provisioning,'merchant.workspace_provisioned')];

$failed=array_values(array_filter($checks,static fn(array $check):bool=>!$check['ok']));
$score=max(0,10-count($failed)*0.4);
echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
