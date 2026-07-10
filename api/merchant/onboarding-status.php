<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
$user=mg_merchant_require_permission('merchant.workspace.view');
$pdo=mg_db();
$workspace=mg_merchant_ensure_workspace($pdo,$user);
$merchantId=(int)$user['id'];$workspaceId=(int)$workspace['id'];
$counts=[];
$queries=[
 'locations'=>"SELECT COUNT(*) FROM merchant_locations WHERE merchant_user_id=? AND workspace_id=? AND status='active'",
 'reward_templates'=>"SELECT COUNT(*) FROM reward_templates WHERE merchant_user_id=? AND status='active'",
 'loyalty_quests'=>"SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND campaign_type='loyalty_quest' AND status IN ('draft','active','paused')",
 'active_loyalty_quests'=>"SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND campaign_type='loyalty_quest' AND status='active'",
];
foreach($queries as $key=>$sql){$stmt=$pdo->prepare($sql);$stmt->execute($key==='locations'?[$merchantId,$workspaceId]:[$merchantId]);$counts[$key]=(int)$stmt->fetchColumn();}
$profileReady=trim((string)($workspace['display_name']??''))!==''&&trim((string)($workspace['support_email']??''))!=='';
$checks=[
 ['key'=>'business_profile','label'=>'Business profile','complete'=>$profileReady,'href'=>'/merchant-settings.php'],
 ['key'=>'first_location','label'=>'Primary location','complete'=>$counts['locations']>0,'href'=>'/merchant-settings.php#locations'],
 ['key'=>'reward_template','label'=>'Active reward template','complete'=>$counts['reward_templates']>0,'href'=>'/merchant-reward-templates.php'],
 ['key'=>'loyalty_quest','label'=>'First Loyalty Quest','complete'=>$counts['loyalty_quests']>0,'href'=>'/merchant-campaigns.php#campaign-create'],
 ['key'=>'launch','label'=>'Published Loyalty Quest','complete'=>$counts['active_loyalty_quests']>0,'href'=>'/merchant-campaigns.php'],
];
$complete=count(array_filter($checks,static fn(array $c):bool=>$c['complete']));$percent=(int)round(($complete/count($checks))*100);
$next=null;foreach($checks as $check){if(!$check['complete']){$next=$check;break;}}
mg_ok(['workspace'=>['id'=>(string)$workspace['public_id'],'display_name'=>(string)$workspace['display_name'],'status'=>(string)$workspace['status'],'eligibility_status'=>(string)$workspace['eligibility_status'],'onboarding_percent'=>$percent],'checks'=>$checks,'counts'=>$counts,'next_action'=>$next,'ready'=>$percent===100]);
