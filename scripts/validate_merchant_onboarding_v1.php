<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$files=['merchant-onboarding.php','assets/js/merchant-onboarding.js','api/merchant/onboarding-status.php','api/merchant/onboarding-complete.php','signup.php','api/auth/register.php','.github/workflows/merchant-onboarding-validation.yml'];
$read=static fn(string $p):string=>is_file($root.'/'.$p)?(string)file_get_contents($root.'/'.$p):'';
$checks=[];foreach($files as $file)$checks[]=['name'=>'file:'.$file,'ok'=>is_file($root.'/'.$file)];
$page=$read('merchant-onboarding.php');$js=$read('assets/js/merchant-onboarding.js');$status=$read('api/merchant/onboarding-status.php');$complete=$read('api/merchant/onboarding-complete.php');$signup=$read('signup.php');$register=$read('api/auth/register.php');
$checks[]=['name'=>'guided merchant UX','ok'=>str_contains($page,'Launch your first Loyalty Quest')&&str_contains($page,'data-onboarding-checklist')&&str_contains($js,'Continue:')];
$checks[]=['name'=>'merchant signup path','ok'=>str_contains($signup,"'merchant'")&&str_contains($signup,'business_name')&&str_contains($register,"\$accountType==='merchant'")];
$checks[]=['name'=>'merchant role and workspace creation','ok'=>str_contains($register,"slug='merchant'")&&str_contains($register,'merchant_workspaces')&&str_contains($register,'merchant_team_members')];
$checks[]=['name'=>'merchant-scoped readiness','ok'=>substr_count($status,'merchant_user_id')>=4&&str_contains($status,"campaign_type='loyalty_quest'")];
$checks[]=['name'=>'required onboarding gates','ok'=>str_contains($status,'business_profile')&&str_contains($status,'first_location')&&str_contains($status,'reward_template')&&str_contains($status,'loyalty_quest')&&str_contains($status,'launch')];
$checks[]=['name'=>'completion enforcement','ok'=>str_contains($complete,'Merchant onboarding is incomplete.')&&str_contains($complete,"onboarding_percent=100")&&str_contains($complete,"eligibility_status='approved'")];
$checks[]=['name'=>'csrf and permissions','ok'=>str_contains($complete,'mg_require_csrf_for_write')&&str_contains($complete,'merchant.workspace.manage')&&str_contains($status,'merchant.workspace.view')];
$checks[]=['name'=>'audit trail','ok'=>str_contains($complete,'merchant.onboarding_completed')];
$failed=array_values(array_filter($checks,static fn(array $c):bool=>!$c['ok']));$score=max(0,10-count($failed)*0.4);echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($failed===[]?0:1);
