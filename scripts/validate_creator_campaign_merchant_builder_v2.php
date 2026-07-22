<?php
declare(strict_types=1);
$root=dirname(__DIR__);$r=static function(string $p)use($root):string{$v=@file_get_contents($root.'/'.$p);if(!is_string($v)){fwrite(STDERR,"Missing required file: {$p}\n");exit(1);}return $v;};
$builder='';foreach(['core','options','save','validation','duplicate'] as $part)$builder.=$r("includes/creator-campaigns/builder-{$part}.php")."\n";
$s=['sql'=>$r('database/20260721_creator_campaign_merchant_builder_v2.sql'),'status'=>$r('includes/creator-campaigns/status-service.php'),'api'=>$r('api/merchant/creator-campaigns.php'),'view'=>$r('includes/merchant-creator-campaign-builder-view.php'),'nav'=>$r('includes/merchant-navigation.php'),'router'=>$r('includes/merchant-view.php'),'workflow'=>$r('.github/workflows/creator-campaign-merchant-builder-v2.yml'),'manifest'=>$r('config/migrations.php'),'builder'=>$builder];
$checks=[
['Typed builder schema',str_contains($s['sql'],'campaign_focus ENUM')&&str_contains($s['sql'],'creator_campaign_application_questions')],
['No premature finance storage',!str_contains($s['sql'],'commission_basis')],
['Workspace authorization',str_contains($builder,'mg_creator_campaign_actor_context')&&str_contains($builder,'workspace_owner_user_id')],
['CSRF write protection',str_contains($s['api'],'mg_require_csrf_for_write')],
['Optimistic locking',str_contains($builder,'lock_version=lock_version+1')],
['Automatic acceptance Phase 3 compatible',str_contains($s['view'],'name="automatic_acceptance"')&&!str_contains($s['view'],'name="automatic_acceptance" disabled')],
['Ten-step structure',str_contains($s['view'],"1=>'Campaign Details'")&&str_contains($s['view'],"10=>'Review'")],
['Operational Steps 1 through 3',str_contains($builder,'Only Builder Steps 1 through 3 are writable in Phase 2.')],
['Separate Creator Campaign routes',str_contains($s['nav'],"'creator_campaigns'")&&str_contains($s['router'],'merchant-creator-campaign-builder-view.php')],
['Canonical migration registration',str_contains($s['manifest'],"'20260721_creator_campaign_merchant_builder_v2.sql'")],
];
$total=0;foreach($checks as[$label,$ok]){if($ok)$total+=10;printf("  [%s] %s\n",$ok?'PASS':'FAIL',$label);}printf("TOTAL: %d/100\n",$total);exit($total===100?0:1);
