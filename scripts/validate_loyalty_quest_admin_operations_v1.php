<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static fn(string $path):string=>is_file($root.'/'.$path)?(string)file_get_contents($root.'/'.$path):'';
$files=[
    'admin/loyalty-quests.php',
    'api/admin/_loyalty_quest_operations.php',
    'api/admin/loyalty-quests.php',
    'assets/css/admin-loyalty-quests.css',
    'assets/js/admin-loyalty-quests.js',
    'scripts/validate_loyalty_quest_admin_operations_behavior.php',
    'docs/architecture/loyalty_quest_admin_operations_v1.md',
    '.github/workflows/loyalty-quest-admin-operations-validation.yml',
];
$checks=[];
foreach($files as $file)$checks[]=['name'=>'file:'.$file,'ok'=>is_file($root.'/'.$file)];

$page=$read('admin/loyalty-quests.php');
$service=$read('api/admin/_loyalty_quest_operations.php');
$api=$read('api/admin/loyalty-quests.php');
$css=$read('assets/css/admin-loyalty-quests.css');
$js=$read('assets/js/admin-loyalty-quests.js');
$behavior=$read('scripts/validate_loyalty_quest_admin_operations_behavior.php');
$permissions=$read('includes/admin-permission-matrix.php');
$sidebar=$read('includes/admin-sidebar.php');
$docs=$read('docs/architecture/loyalty_quest_admin_operations_v1.md');

$checks[]=['name'=>'permission-matrix authority','ok'=>str_contains($permissions,"'loyalty_quest_operations' => 'campaign.manage'")&&str_contains($api,"mg_require_permission('campaign.manage')")&&str_contains($page,'mg_admin_section_permission($adminPage)')];
$checks[]=['name'=>'admin sidebar integration','ok'=>str_contains($sidebar,'loyalty_quest_operations')&&str_contains($sidebar,'/admin/loyalty-quests.php')&&str_contains($sidebar,'Loyalty Quests')];
$checks[]=['name'=>'CSRF and reason requirement','ok'=>str_contains($api,'mg_require_csrf_for_write')&&str_contains($service,'mb_strlen($reason)<12')&&str_contains($service,'mb_strlen($reason)>1000')&&str_contains($page,'minlength="12"')];
$checks[]=['name'=>'campaign transition locks','ok'=>str_contains($service,"campaign_type='loyalty_quest' LIMIT 1 FOR UPDATE")&&str_contains($service,"['active','scheduled']")&&str_contains($service,"$old!=='paused'")&&str_contains($service,"['active','scheduled','paused']")];
$checks[]=['name'=>'explicit admin event names','ok'=>str_contains($service,"'pause'=>'quest.admin_paused'")&&str_contains($service,"'resume'=>'quest.admin_resumed'")&&str_contains($service,"'end'=>'quest.admin_ended'")];
$checks[]=['name'=>'audited campaign controls','ok'=>str_contains($service,'mg_lqo_campaign_event')&&str_contains($service,"mg_audit('admin.loyalty_quest_'")&&str_contains($service,"'old_status'=>$old")&&str_contains($service,"'new_status'=>$new")];
$checks[]=['name'=>'merchant review nudge only','ok'=>str_contains($service,"lqe.status='submitted'")&&str_contains($service,"event_type='quest.admin_review_nudged'")&&str_contains($service,'INTERVAL 12 HOUR')&&str_contains($service,'mg_create_operational_alert')];
$checks[]=['name'=>'no admin evidence decision mutation','ok'=>!str_contains($service,'UPDATE loyalty_quest_evidence SET status=')&&!str_contains($api,"'approve'")&&!str_contains($api,"'reject'")&&!str_contains($js,'Approve evidence')&&!str_contains($js,'Reject evidence')];
$checks[]=['name'=>'safe delivery recovery','ok'=>str_contains($service,"['failed','dead_letter']")&&str_contains($service,"status==='processing'")&&str_contains($service,'time()-900')&&str_contains($service,'min(10')&&str_contains($service,"status='queued'")];
$checks[]=['name'=>'delivery recovery reuses canonical queue','ok'=>str_contains($service,'UPDATE message_delivery_jobs')&&!str_contains($service,'INSERT INTO message_delivery_jobs')&&str_contains($service,'message_events me')];
$checks[]=['name'=>'PII-minimized evidence queue','ok'=>!str_contains($service,'proof_url')&&!str_contains($service,'proof_note')&&!str_contains($service,'latitude')&&!str_contains($service,'longitude')&&!str_contains($service,'participant_email')&&!str_contains($service,'participant_name')];
$checks[]=['name'=>'masked delivery recipients','ok'=>str_contains($service,'function mg_lqo_mask_email')&&str_contains($service,"'email_masked'")&&!str_contains($service,"'email'=>(string)($recipient['email']")];
$checks[]=['name'=>'explicit authority response','ok'=>str_contains($api,"'can_approve_evidence'=>false")&&str_contains($api,"'can_issue_rewards'=>false")&&str_contains($api,"'can_redeem_pppm'=>false")];
$checks[]=['name'=>'no reward claim or PPPM mutation','ok'=>!str_contains($service,'INSERT INTO wallet_items')&&!str_contains($service,'UPDATE wallet_items')&&!str_contains($service,'INSERT INTO pppm_items')&&!str_contains($service,'UPDATE pppm_items')&&!str_contains($service,'UPDATE gift_claims')];
$checks[]=['name'=>'responsive accessible command center','ok'=>str_contains($page,'aria-label="Administrative authority boundary"')&&str_contains($page,'aria-live="polite"')&&str_contains($page,'aria-labelledby="lqo-dialog-title"')&&str_contains($css,':focus-visible')&&str_contains($css,'@media(max-width:760px)')];
$checks[]=['name'=>'dialog action safeguards','ok'=>str_contains($js,'data-lqo-action')&&str_contains($js,'payload.reason.length<12')&&str_contains($js,"review_nudge")&&str_contains($js,"retry_delivery")&&str_contains($js,'This does not approve or reject evidence')];
$checks[]=['name'=>'strict MySQL behavior coverage','ok'=>str_contains($behavior,'quest-only campaign scope')&&str_contains($behavior,'evidence PII excluded')&&str_contains($behavior,'delivery recipient masked')&&str_contains($behavior,'stale processing detected')&&str_contains($behavior,'summary totals')];
$checks[]=['name'=>'no parallel admin datastore','ok'=>!is_file($root.'/database/loyalty_quest_admin_operations_v1.sql')&&!str_contains($service,'CREATE TABLE')&&str_contains($docs,'No new SQL migration is required')];
$checks[]=['name'=>'ownership authority documented','ok'=>str_contains($docs,'Microgift → PPPM → Inbox')&&str_contains($docs,'does not approve or reject participant evidence')&&str_contains($docs,'does not')&&str_contains($page,'PPPM ownership')];
$checks[]=['name'=>'production limits documented','ok'=>str_contains($docs,'Browser rendering')&&str_contains($docs,'production operational-alert delivery')&&str_contains($docs,'production-volume query performance')];

$failed=array_values(array_filter($checks,static fn(array $check):bool=>!$check['ok']));
$score=max(0,10-count($failed)*.4);
echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
