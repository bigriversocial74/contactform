<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$required=[
 'includes/loyalty-quest-campaign-type.php','api/merchant/loyalty-quest-campaigns.php','assets/js/loyalty-quest-campaign-type.js','loyalty-quest.php','api/public/loyalty-quest/detail.php',
 'includes/campaign-types.php','includes/public-donations-feature.php','includes/merchant-campaigns-view.php','merchant-campaigns.php','scripts/validate_loyalty_quest_campaign_type_v1.php','.github/workflows/loyalty-quest-campaign-type-validation.yml',
];
$checks=[];$read=static fn(string $path):string=>is_file($root.'/'.$path)?(string)file_get_contents($root.'/'.$path):'';
foreach($required as $file)$checks[]=['name'=>'file:'.$file,'ok'=>is_file($root.'/'.$file)];
$module=$read('includes/loyalty-quest-campaign-type.php');$registry=$read('includes/campaign-types.php');$feature=$read('includes/public-donations-feature.php');$view=$read('includes/merchant-campaigns-view.php');$page=$read('merchant-campaigns.php');$route=$read('loyalty-quest.php');$detail=$read('api/public/loyalty-quest/detail.php');$api=$read('api/merchant/loyalty-quest-campaigns.php');$js=$read('assets/js/loyalty-quest-campaign-type.js');
$checks[]=['name'=>'registry definition','ok'=>str_contains($module,"'key' => 'loyalty_quest'")&&str_contains($module,"'label' => 'Loyalty Quest'")&&str_contains($module,"'public_path' => '/loyalty-quest.php'")&&str_contains($registry,'mg_loyalty_quest_campaign_definition')];
$directCampaignOptions=str_contains($view,'mg_campaign_type_options(true)');
$featureGatedCampaignOptions=str_contains($view,'mg_public_donations_campaign_type_options(')&&str_contains($feature,'mg_campaign_type_options($includeInternal)');
$checks[]=['name'=>'main campaign option','ok'=>($directCampaignOptions||$featureGatedCampaignOptions)&&str_contains($page,'/assets/js/loyalty-quest-campaign-type.js')&&str_contains($js,"option.value='loyalty_quest'")];
$checks[]=['name'=>'action methods','ok'=>str_contains($module,"'location_visit'")&&str_contains($module,"'signed_qr'")&&str_contains($module,"'purchase'")&&str_contains($module,"'event_attendance'")&&str_contains($module,"'referral'")&&str_contains($module,"'multi_location'")&&str_contains($module,"'sequence'")];
$checks[]=['name'=>'verification methods','ok'=>str_contains($module,"'static_qr'")&&str_contains($module,"'geolocation'")&&str_contains($module,"'purchase_record'")&&str_contains($module,"'receipt_review'")&&str_contains($module,"'staff_confirmation'")&&str_contains($module,"'microgifter_transaction'")&&str_contains($module,"'manual_review'")];
$checks[]=['name'=>'audience visibility','ok'=>str_contains($module,"'public','customers','loyalty_members','new_customers','invite_only','campaign_contacts','geographic_radius'")];
$checks[]=['name'=>'Microgifter identity contract','ok'=>str_contains($module,"'merchant_account_required' => true")&&str_contains($module,"'microgifter_identity_required' => true")];
$checks[]=['name'=>'merchant scoped API','ok'=>str_contains($api,'mg_merchant_require_permission')&&str_contains($api,'merchant.campaigns.manage')&&substr_count($api,'merchant_user_id')>=8&&str_contains($api,"'loyalty_quest'")];
$checks[]=['name'=>'validation and activation gates','ok'=>str_contains($module,'mg_loyalty_quest_validate_rules')&&str_contains($module,'Active Loyalty Quest campaigns require participant instructions.')&&str_contains($module,'Location-based Loyalty Quests require a merchant location.')&&str_contains($api,'Active Loyalty Quests require an attached reward template.')];
$checks[]=['name'=>'safe limits','ok'=>str_contains($module,'max(25, min(5000')&&str_contains($module,'max(1, min(100')&&str_contains($module,'max(0, min(8760')];
$checks[]=['name'=>'one-way completion secrets','ok'=>str_contains($module,'completion_code_hash')&&str_contains($module,'staff_confirmation_code_hash')&&str_contains($module,'event_checkin_code_hash')&&str_contains($module,"hash('sha256'")];
$checks[]=['name'=>'public participant route','ok'=>str_contains($route,'data-loyalty-quest-participant')&&str_contains($route,'data-campaign-ref')&&str_contains($detail,'mg_lqp_campaign')&&str_contains($detail,'can_start')&&str_contains($detail,'can_submit')];
$checks[]=['name'=>'builder fields','ok'=>str_contains($js,'quest_action_type')&&str_contains($js,'quest_verification_type')&&str_contains($js,'quest_visibility')&&str_contains($js,'quest_radius_meters')&&str_contains($js,'quest_required_count')&&str_contains($js,'quest_instructions')&&str_contains($js,'quest_budget_limit')];
$checks[]=['name'=>'CSRF and audit','ok'=>str_contains($api,'mg_require_csrf_for_write')&&str_contains($api,'mg_audit')&&str_contains($api,'merchant.loyalty_quest_saved')];
$checks[]=['name'=>'No SQL required','ok'=>!is_file($root.'/database/loyalty_quest_campaign_type_v1.sql')];
$failed=array_values(array_filter($checks,static fn(array $check):bool=>!$check['ok']));$score=max(0,10-count($failed)*0.4);
echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
