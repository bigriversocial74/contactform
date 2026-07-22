<?php
declare(strict_types=1);
$root=dirname(__DIR__);$read=static function(string $path)use($root):string{$value=@file_get_contents($root.'/'.$path);if(!is_string($value)){fwrite(STDERR,"Missing required file: {$path}\n");exit(1);}return $value;};
$s=[
'sql'=>$read('database/20260721_creator_campaign_deliverables_v4.sql'),
'def'=>$read('includes/creator-campaigns/deliverable-definitions.php'),
'ctx'=>$read('includes/creator-campaigns/deliverable-context.php'),
'repo'=>$read('includes/creator-campaigns/deliverable-repository.php'),
'deliverable'=>$read('includes/creator-campaigns/deliverable-service.php'),
'submission'=>$read('includes/creator-campaigns/submission-service.php'),
'query'=>$read('includes/creator-campaigns/deliverable-query.php'),
'notification'=>$read('includes/creator-campaigns/deliverable-notification.php'),
'merchant_api'=>$read('api/merchant/creator-campaign-deliverables.php'),
'creator_api'=>$read('api/creator/campaign-deliverables.php'),
'merchant_view'=>$read('includes/merchant-creator-campaign-deliverables-view.php'),
'creator_view'=>$read('includes/creator-campaign-deliverables-view.php'),
'merchant_js'=>$read('assets/js/merchant-creator-campaign-deliverables.js'),
'creator_js'=>$read('assets/js/creator-campaign-deliverables.js'),
'workflow'=>$read('.github/workflows/creator-campaign-deliverables-v4.yml'),
'manifest'=>$read('config/migrations.php'),
];
$tables=['creator_campaign_deliverables','creator_campaign_participant_deliverables','creator_campaign_submissions','creator_campaign_submission_revisions','creator_campaign_assets'];
$checks=[
'Schema'=>[
 ['Five normalized Phase 4 tables',count(array_filter($tables,fn($t)=>str_contains($s['sql'],'CREATE TABLE IF NOT EXISTS '.$t)))===5],
 ['Assignments reference accepted agreement version',str_contains($s['sql'],'agreement_version_id BIGINT UNSIGNED NOT NULL')&&str_contains($s['sql'],'fk_cc_pd_agreement_version')],
 ['Immutable revision history',str_contains($s['sql'],'uq_cc_submission_revision_number')&&str_contains($s['repo'],'mg_creator_campaign_submission_revision')],
 ['Migration registered',str_contains($s['manifest'],"'20260721_creator_campaign_deliverables_v4.sql'")],
],
'Lifecycle'=>[
 ['Campaign deliverable definitions',str_contains($s['deliverable'],'mg_creator_campaign_deliverable_save_merchant')&&str_contains($s['deliverable'],'required_talking_points')],
 ['Idempotent participant assignment',str_contains($s['deliverable'],'INSERT IGNORE INTO creator_campaign_participant_deliverables')&&str_contains($s['deliverable'],'latest_accepted_version_id')],
 ['Creator save, submit, withdraw, and proof',str_contains($s['submission'],'mg_creator_campaign_submission_save_creator')&&str_contains($s['submission'],'mg_creator_campaign_submission_withdraw_creator')&&str_contains($s['submission'],'publication_proof_creator')],
 ['Merchant revision, approval, rejection, verification',str_contains($s['submission'],"['under_review','revision_requested','approved','rejected','verified']")&&str_contains($s['submission'],'revision limit')],
],
'Integrity'=>[
 ['Optimistic locks',str_contains($s['deliverable'],'expected_lock_version')&&str_contains($s['submission'],'expected_lock_version')],
 ['Workspace and creator ownership scope',str_contains($s['ctx'],'deliverable_merchant_context')&&str_contains($s['ctx'],'deliverable_creator_context')],
 ['External asset URL validation',str_contains($s['def'],'FILTER_VALIDATE_URL')&&str_contains($s['submission'],'submission_attach_external_assets')],
 ['No tracking or financial tables',!preg_match('/CREATE TABLE IF NOT EXISTS creator_campaign_(tracking|attribution|compensation|earning|budget|payout|dispute)/',$s['sql'])],
],
'Delivery'=>[
 ['Dedicated merchant and Creator APIs',str_contains($s['merchant_api'],'review_submission')&&str_contains($s['creator_api'],'submit_publication_proof')],
 ['CSRF on both APIs',str_contains($s['merchant_api'],'mg_require_csrf_for_write')&&str_contains($s['creator_api'],'mg_require_csrf_for_write')],
 ['Operational workspaces',str_contains($s['merchant_view'],'data-ccdv-review-dialog')&&str_contains($s['creator_view'],'data-ccdv-submission-dialog')],
 ['Notifications reuse canonical system',str_contains($s['notification'],'mg_create_notification')&&str_contains($s['notification'],'Notifications must never invalidate')],
],
'Validation'=>[
 ['PHP 8.2 and 8.3 matrix',str_contains($s['workflow'],"php: ['8.2','8.3']")],
 ['JavaScript syntax checks',str_contains($s['workflow'],'node --check assets/js/merchant-creator-campaign-deliverables.js')&&str_contains($s['workflow'],'node --check assets/js/creator-campaign-deliverables.js')],
 ['Earlier phase compatibility',str_contains($s['workflow'],'validate_creator_campaign_participation_v3.php')&&str_contains($s['workflow'],'CreatorCampaignParticipationV3ContractTest.php')],
 ['MySQL schema validation',str_contains($s['workflow'],'validate_creator_campaign_deliverables_v4_mysql.php')],
],
];
$total=0;$max=0;foreach($checks as $group=>$rows){$score=0;foreach($rows as[$label,$ok]){$max+=5;if($ok){$score+=5;$total+=5;}printf("  [%s] %s\n",$ok?'PASS':'FAIL',$label);}printf("%s: %d/%d\n",$group,$score,count($rows)*5);}printf("TOTAL: %d/%d\n",$total,$max);exit($total===$max?0:1);
