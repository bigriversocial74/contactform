<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$files=[
 'quests.php','quest-merchant.php','loyalty-quest.php','api/public/loyalty-quests.php',
 'assets/js/loyalty-quest-marketplace.js','assets/css/loyalty-quest-marketplace.css',
 'assets/css/loyalty-quest-map.css','assets/images/loyalty-quest-placeholder.svg',
 'assets/images/microgifter-mark.svg','.github/workflows/public-loyalty-quest-marketplace-validation.yml',
];
$read=static fn(string $path):string=>is_file($root.'/'.$path)?(string)file_get_contents($root.'/'.$path):'';
$checks=[];foreach($files as $file)$checks[]=['name'=>'file:'.$file,'ok'=>is_file($root.'/'.$file)];
$page=$read('quests.php');$merchant=$read('quest-merchant.php');$route=$read('loyalty-quest.php');$api=$read('api/public/loyalty-quests.php');$js=$read('assets/js/loyalty-quest-marketplace.js');$css=$read('assets/css/loyalty-quest-marketplace.css');$mapCss=$read('assets/css/loyalty-quest-map.css');
$checks[]=['name'=>'public marketplace UX','ok'=>str_contains($page,'data-quest-marketplace')&&str_contains($page,'Search quests or merchants')&&str_contains($page,'Quest action')&&str_contains($page,'Reward status')&&str_contains($page,'Use my location')];
$checks[]=['name'=>'list and map views','ok'=>str_contains($page,'data-quest-view="list"')&&str_contains($page,'data-quest-view="map"')&&str_contains($page,'data-quest-map-surface')&&str_contains($js,'renderMap()')&&str_contains($mapCss,'.mg-quest-map-point')];
$checks[]=['name'=>'SEO and structured data','ok'=>str_contains($page,"'robots' => 'index, follow'")&&str_contains($page,'application/ld+json')&&str_contains($page,'CollectionPage')&&str_contains($page,'ItemList')];
$checks[]=['name'=>'public lifecycle gates','ok'=>str_contains($api,"c.campaign_type='loyalty_quest'")&&str_contains($api,"c.status='active'")&&str_contains($api,'c.starts_at<=NOW()')&&str_contains($api,'c.ends_at>NOW()')&&str_contains($api,"='public'")];
$checks[]=['name'=>'multi merchant identity','ok'=>str_contains($api,'INNER JOIN users')&&str_contains($api,'public_profiles')&&str_contains($api,'merchant_workspaces')&&str_contains($api,'merchant_name')&&str_contains($api,'merchant_url')];
$checks[]=['name'=>'location and nearby discovery','ok'=>str_contains($api,'mg_public_quest_distance')&&str_contains($api,'radiusMiles')&&str_contains($api,'distance_miles')&&str_contains($js,'navigator.geolocation')&&str_contains($js,"sort.value='nearby'")];
$checks[]=['name'=>'search filters and pagination','ok'=>str_contains($api,"$_GET['q']")&&str_contains($api,"$_GET['location']")&&str_contains($api,"$_GET['action']")&&str_contains($api,"$_GET['reward']")&&str_contains($api,"$_GET['sort']")&&str_contains($api,'has_more')&&str_contains($js,'data-quest-load-more')];
$checks[]=['name'=>'reward and availability presentation','ok'=>str_contains($api,'reward_title')&&str_contains($api,'value_amount_cents')&&str_contains($api,'remaining')&&str_contains($js,'q.reward')&&str_contains($js,'q.remaining')];
$checks[]=['name'=>'merchant public profile','ok'=>str_contains($merchant,"pp.visibility='public'")&&str_contains($merchant,"pp.status='active'")&&str_contains($merchant,"c.campaign_type='loyalty_quest'")&&str_contains($merchant,'Choose a quest')&&str_contains($merchant,'Explore all quests')];
$checks[]=['name'=>'correct quest route handoff','ok'=>str_contains($route,"$_GET['campaign']")&&str_contains($route,"$_GET['c'] = $ref")&&str_contains($route,"require __DIR__ . '/campaign.php'")];
$checks[]=['name'=>'safe image fallbacks','ok'=>str_contains($api,'/assets/images/loyalty-quest-placeholder.svg')&&str_contains($js,'/assets/images/microgifter-mark.svg')&&str_contains($merchant,'/assets/images/loyalty-quest-placeholder.svg')];
$checks[]=['name'=>'responsive accessible experience','ok'=>str_contains($page,'role="search"')&&str_contains($page,'aria-live="polite"')&&str_contains($page,'aria-pressed="true"')&&str_contains($css,'@media(max-width:680px)')&&str_contains($mapCss,'@media(max-width:680px)')];
$failed=array_values(array_filter($checks,static fn(array $check):bool=>!$check['ok']));
$score=max(0,10-count($failed)*0.4);
echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
