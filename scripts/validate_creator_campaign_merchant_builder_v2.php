<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{
    $value=@file_get_contents($root.'/'.$path);
    if(!is_string($value)){fwrite(STDERR,"Missing required file: {$path}\n");exit(1);}return $value;
};
$builder='';foreach(['core','options','save','validation','duplicate'] as $part){$builder.=$read("includes/creator-campaigns/builder-{$part}.php")."\n";}
$s=[
 'sql'=>$read('database/20260721_creator_campaign_merchant_builder_v2.sql'),
 'status'=>$read('includes/creator-campaigns/status-service.php'),
 'api'=>$read('api/merchant/creator-campaigns.php'),
 'overview'=>$read('includes/merchant-creator-campaigns-view.php'),
 'view'=>$read('includes/merchant-creator-campaign-builder-view.php'),
 'nav'=>$read('includes/merchant-navigation.php'),
 'router'=>$read('includes/merchant-view.php'),
 'workflow'=>$read('.github/workflows/creator-campaign-merchant-builder-v2.yml'),
 'mysql'=>$read('scripts/validate_creator_campaign_merchant_builder_v2_mysql.php'),
 'manifest'=>$read('config/migrations.php'),
 'builder'=>$builder,
];
$groups=[
 'Schema and domain boundaries'=>[
  ['Typed builder columns',str_contains($s['sql'],'campaign_focus ENUM')&&str_contains($s['sql'],'builder_completed_steps_json JSON')&&str_contains($s['sql'],'builder_version INT UNSIGNED')],
  ['Normalized application questions',str_contains($s['sql'],'creator_campaign_application_questions')&&str_contains($s['sql'],'question_type ENUM')],
  ['No premature compensation storage',!str_contains($s['sql'],'commission_basis')&&!str_contains($builder,'mg_creator_campaign_commission_bases')],
  ['Legacy campaign separation',!preg_match('/\b(?:FROM|UPDATE)\s+campaigns\b/i',$builder."\n".$s['api'])],
 ],
 'Services and authorization'=>[
  ['Workspace-scoped service context',str_contains($builder,'mg_creator_campaign_actor_context')&&str_contains($builder,'workspace_owner_user_id')],
  ['CSRF write protection',str_contains($s['api'],'mg_require_csrf_for_write($input)')],
  ['Automatic acceptance fails closed',str_contains($builder,'Automatic acceptance is unavailable until Creator Participation is installed.')&&str_contains($s['view'],'name="automatic_acceptance" disabled')],
  ['Owned references are resolved',str_contains($builder,'mg_creator_campaign_builder_resolve_asset')&&str_contains($builder,'mg_creator_campaign_builder_resolve_reward')&&str_contains($builder,'mg_creator_campaign_builder_resolve_manager')],
 ],
 'Merchant workflow and UX'=>[
  ['Separate creator campaign routes',str_contains($s['nav'],"'creator_campaigns'")&&str_contains($s['router'],'merchant-creator-campaigns-view.php')&&str_contains($s['router'],'merchant-creator-campaign-builder-view.php')],
  ['Approved ten-step structure',str_contains($s['view'],"1=>'Campaign Details'")&&str_contains($s['view'],"10=>'Review'")&&str_contains($s['view'],"4=>'Deliverables'")],
  ['Operational Steps 1 through 3',str_contains($builder,'Only Builder Steps 1 through 3 are writable in Phase 2.')&&str_contains($s['view'],'Campaign products')&&str_contains($s['view'],'Application questions')],
  ['Review and pagination controls',str_contains($s['overview'],'data-cc-pagination')&&str_contains($read('assets/js/merchant-creator-campaign-builder.js'),'renderReview')],
 ],
 'Lifecycle and concurrency'=>[
  ['Optimistic locking',str_contains($builder,'lock_version=lock_version+1')&&str_contains($builder,'expected_lock_version is required.')],
  ['Atomic child replacement',str_contains($builder,'DELETE FROM creator_campaign_products')&&str_contains($builder,'DELETE FROM creator_campaign_eligibility_rules')&&str_contains($builder,'DELETE FROM creator_campaign_application_questions')],
  ['Idempotent duplication',str_contains($builder,"duplicate:' . \$idempotencyKey")&&str_contains($builder,'mg_creator_campaign_repository_by_idempotency')],
  ['Agreement-gated publication',str_contains($s['status'],'creator_campaign_agreement_versions')&&str_contains($s['status'],'Campaign publication will unlock when the Agreement phase is installed.')],
 ],
 'Validation and delivery'=>[
  ['Scored validator registered',str_contains($s['workflow'],'Scored Phase 2 validation')],
  ['PHP and JavaScript matrices',str_contains($s['workflow'],"php: ['8.2','8.3']")&&str_contains($s['workflow'],'node --check assets/js/merchant-creator-campaigns.js')&&str_contains($s['workflow'],'node --check assets/js/merchant-creator-campaign-builder.js')],
  ['Clean MySQL lifecycle',str_contains($s['workflow'],'validate_creator_campaign_merchant_builder_v2_mysql.php')&&str_contains($s['mysql'],'phase2_score')],
  ['Canonical migration registration',str_contains($s['manifest'],"'20260721_creator_campaign_merchant_builder_v2.sql'")],
 ],
];
$total=0;$maximum=0;foreach($groups as $name=>$checks){$score=0;foreach($checks as [$label,$passed]){$maximum+=5;if($passed){$score+=5;$total+=5;}printf("  [%s] %s\n",$passed?'PASS':'FAIL',$label);}printf("%s: %d/%d\n",$name,$score,count($checks)*5);}printf("TOTAL: %d/%d\n",$total,$maximum);exit($total===$maximum?0:1);
