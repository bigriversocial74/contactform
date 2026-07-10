<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$required=[
 'includes/loyalty-quest-campaign-type.php',
 'api/merchant/loyalty-quest-campaigns.php',
 'assets/js/loyalty-quest-campaign-type.js',
 'merchant-campaigns.php',
 'loyalty-quest.php',
 '.github/workflows/loyalty-quest-campaign-type-validation.yml',
];
$read=static fn(string $path):string=>is_file($root.'/'.$path)?(string)file_get_contents($root.'/'.$path):'';
$checks=[];
foreach($required as $path)$checks[]=['name'=>'file:'.$path,'ok'=>is_file($root.'/'.$path)];
$definition=$read('includes/loyalty-quest-campaign-type.php');
$api=$read('api/merchant/loyalty-quest-campaigns.php');
$js=$read('assets/js/loyalty-quest-campaign-type.js');
$page=$read('merchant-campaigns.php');
$public=$read('loyalty-quest.php');
$checks[]=['name'=>'first class type contract','ok'=>str_contains($definition,"'key' => 'loyalty_quest'")&&str_contains($definition,"'label' => 'Loyalty Quest'")&&str_contains($definition,"'wallet_issue_mode' => 'verified_quest_reward'")];
$checks[]=['name'=>'merchant account requirement','ok'=>str_contains($definition,"'merchant_account_required' => true")&&str_contains($definition,"'microgifter_identity_required' => true")];
$checks[]=['name'=>'complete action catalog','ok'=>str_contains($definition,'location_visit')&&str_contains($definition,'product_purchase')&&str_contains($definition,'event_attendance')&&str_contains($definition,'multi_location')&&str_contains($definition,'sequence')];
$checks[]=['name'=>'complete verification catalog','ok'=>str_contains($definition,'signed_qr')&&str_contains($definition,'geolocation')&&str_contains($definition,'purchase_record')&&str_contains($definition,'staff_confirmation')&&str_contains($definition,'manual_review')];
$checks[]=['name'=>'merchant isolation','ok'=>substr_count($api,'merchant_user_id')>=8&&str_contains($api,"campaign_type='loyalty_quest'")];
$checks[]=['name'=>'secure activation gates','ok'=>str_contains($api,'Active Loyalty Quests require an active reward template.')&&str_contains($definition,'Active Loyalty Quest campaigns require participant instructions.')&&str_contains($definition,'Location-based Loyalty Quests require a merchant location.')];
$checks[]=['name'=>'rules persistence','ok'=>str_contains($api,'rules_json')&&str_contains($api,'loyalty_quest_campaign_type_v1')&&str_contains($api,'qr_code_token')];
$checks[]=['name'=>'campaign builder option','ok'=>str_contains($js,"option.value='loyalty_quest'")&&str_contains($js,'Create Loyalty Quest')&&str_contains($page,'loyalty-quest-campaign-type.js')];
$checks[]=['name'=>'builder fields','ok'=>str_contains($js,'quest_action_type')&&str_contains($js,'quest_verification_type')&&str_contains($js,'quest_visibility')&&str_contains($js,'quest_location_id')&&str_contains($js,'quest_budget_limit')];
$checks[]=['name'=>'public route','ok'=>str_contains($public,'Loyalty Quest')&&str_contains($public,'campaign.php')];
$checks[]=['name'=>'audit and lifecycle','ok'=>str_contains($api,'merchant.loyalty_quest_saved')&&str_contains($api,'campaign.launched')&&str_contains($api,'campaign.created')];
$failed=array_values(array_filter($checks,static fn(array $check):bool=>!$check['ok']));
$score=max(0,10-(count($failed)*.4));
$result=['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed,'generated_at'=>gmdate('c')];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
