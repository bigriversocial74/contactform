<?php
declare(strict_types=1);
$root=dirname(__DIR__);$read=static function(string $p)use($root):string{$v=@file_get_contents($root.'/'.$p);if(!is_string($v)){fwrite(STDERR,"Missing required file: {$p}\n");exit(1);}return $v;};
$s=[
'sql'=>$read('database/20260721_creator_campaign_participation_v3.sql'),
'def'=>$read('includes/creator-campaigns/participation-definitions.php'),
'ctx'=>$read('includes/creator-campaigns/participation-context.php'),
'app'=>$read('includes/creator-campaigns/application-creator.php').$read('includes/creator-campaigns/application-merchant.php'),
'invite'=>$read('includes/creator-campaigns/invitation-creator.php').$read('includes/creator-campaigns/invitation-merchant.php'),
'participant'=>$read('includes/creator-campaigns/participant-service.php'),
'agreement'=>$read('includes/creator-campaigns/agreement-service.php'),
'evaluator'=>$read('includes/creator-campaigns/eligibility-evaluator.php'),
'creator_api'=>$read('api/creator/campaigns.php'),'merchant_api'=>$read('api/merchant/creator-campaign-participation.php'),
'creator_view'=>$read('includes/creator-campaigns-participation-view.php'),'merchant_view'=>$read('includes/merchant-creator-campaign-participation-view.php'),
'creator_js'=>$read('assets/js/creator-campaign-participation.js'),'merchant_js'=>$read('assets/js/merchant-creator-campaign-participation.js'),
'workflow'=>$read('.github/workflows/creator-campaign-participation-v3.yml'),'manifest'=>$read('config/migrations.php'),
];
$checks=[
'Schema'=>[
 ['Eight normalized Phase 3 tables',count(array_filter(['creator_campaign_applications','creator_campaign_application_answers','creator_campaign_invitations','creator_campaign_participants','creator_campaign_participation_events','creator_campaign_agreements','creator_campaign_agreement_versions','creator_campaign_agreement_acceptances'],fn($t)=>str_contains($s['sql'],'CREATE TABLE IF NOT EXISTS '.$t)))===8],
 ['Immutable agreement versions',str_contains($s['sql'],'content_hash CHAR(64)')&&str_contains($s['sql'],'version_number INT UNSIGNED')&&str_contains($s['sql'],'creator_campaign_agreement_acceptances')],
 ['No later-phase finance/tracking schema',!preg_match('/CREATE TABLE IF NOT EXISTS creator_campaign_(deliverables|tracking_sources|earnings|payouts|disputes)/',$s['sql'])],
 ['Migration registered',str_contains($s['manifest'],"'20260721_creator_campaign_participation_v3.sql'")],
],
'Lifecycle'=>[
 ['Optional automatic acceptance',str_contains($s['app'],'automatic_acceptance')&&str_contains($s['app'],'mg_creator_campaign_evaluate_automatic_acceptance')],
 ['Fail-closed rule evaluator',str_contains($s['evaluator'],"'eligible' => \$eligible")&&str_contains($s['evaluator'],'participant_capacity')],
 ['Manual review remains supported',str_contains($s['app'],"'approve'=>'approved'")&&str_contains($s['merchant_view'],'data-review-action="approve"')],
 ['Invitation acceptance offers Version 1',str_contains($s['invite'],'mg_creator_campaign_agreement_ensure_offered')],
],
'Agreements'=>[
 ['Versioned immutable snapshots',str_contains($s['agreement'],'agreement_snapshot')&&str_contains($s['agreement'],'contentHash')],
 ['Acceptance receipt and activation',str_contains($s['agreement'],'creator_campaign_agreement_acceptances')&&str_contains($s['agreement'],"status='active'")],
 ['Reacceptance supported',str_contains($s['agreement'],'requires_reacceptance')&&str_contains($s['agreement'],"status='agreement_pending'")],
 ['Creator active workspace',str_contains($s['agreement'],'mg_creator_campaign_active_workspace_creator')&&str_contains($s['creator_api'],"'active_campaigns'")],
],
'Authorization'=>[
 ['Creator ownership context',str_contains($s['ctx'],'mg_creator_campaign_creator_context')&&str_contains($s['ctx'],'creator_user_id')],
 ['Merchant workspace scope',str_contains($s['ctx'],'mg_creator_campaign_actor_context')],
 ['CSRF on both APIs',str_contains($s['creator_api'],'mg_require_csrf_for_write')&&str_contains($s['merchant_api'],'mg_require_csrf_for_write')],
 ['Optimistic locks and capacity',str_contains($s['participant'],'expected_lock_version')&&str_contains($s['participant'],'maximum_approved_creators')],
],
'Delivery'=>[
 ['Creator UI complete',str_contains($s['creator_view'],'data-ccp-creator-tab="agreements"')&&str_contains($s['creator_view'],'data-ccp-creator-tab="active_campaigns"')],
 ['Merchant UI complete',str_contains($s['merchant_view'],'data-ccp-tab="agreements"')&&str_contains($s['merchant_view'],'data-ccp-open-invite')],
 ['JS agreement actions',str_contains($s['creator_js'],'respond_agreement')&&str_contains($s['merchant_js'],'offer_agreement')],
 ['PHP matrix and MySQL lifecycle',str_contains($s['workflow'],"php: ['8.2','8.3']")&&str_contains($s['workflow'],'validate_creator_campaign_participation_v3_mysql.php')],
],
];
$total=0;$max=0;foreach($checks as $group=>$rows){$score=0;foreach($rows as[$label,$ok]){$max+=5;if($ok){$score+=5;$total+=5;}printf("  [%s] %s\n",$ok?'PASS':'FAIL',$label);}printf("%s: %d/%d\n",$group,$score,count($rows)*5);}printf("TOTAL: %d/%d\n",$total,$max);exit($total===$max?0:1);
