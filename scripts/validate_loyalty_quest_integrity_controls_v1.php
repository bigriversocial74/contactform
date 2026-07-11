<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$read=static fn(string $path):string=>is_file($root.'/'.$path)?(string)file_get_contents($root.'/'.$path):'';
$files=[
    'database/loyalty_quest_integrity_controls_v1.sql',
    'api/public/loyalty-quest/_integrity.php',
    'api/public/loyalty-quest/start.php',
    'api/public/loyalty-quest/submit.php',
    'api/merchant/loyalty-quest-reviews.php',
    'includes/merchant-quest-reviews-view.php',
    'assets/js/merchant-quest-reviews.js',
    'assets/css/merchant-quest-reviews.css',
    'admin/loyalty-quest-integrity.php',
    'api/admin/_loyalty_quest_integrity.php',
    'api/admin/loyalty-quest-integrity.php',
    'assets/js/admin-loyalty-quest-integrity.js',
    'assets/css/admin-loyalty-quest-integrity.css',
    'docs/architecture/loyalty_quest_integrity_controls_v1.md',
    'scripts/validate_loyalty_quest_integrity_controls_behavior.php',
    '.github/workflows/loyalty-quest-integrity-controls-validation.yml',
];
$checks=[];
foreach($files as $file)$checks[]=['name'=>'file:'.$file,'ok'=>is_file($root.'/'.$file)];

$sql=$read('database/loyalty_quest_integrity_controls_v1.sql');
$integrity=$read('api/public/loyalty-quest/_integrity.php');
$start=$read('api/public/loyalty-quest/start.php');
$submit=$read('api/public/loyalty-quest/submit.php');
$merchant=$read('api/merchant/loyalty-quest-reviews.php');
$merchantView=$read('includes/merchant-quest-reviews-view.php');
$merchantJs=$read('assets/js/merchant-quest-reviews.js');
$merchantCss=$read('assets/css/merchant-quest-reviews.css');
$adminPage=$read('admin/loyalty-quest-integrity.php');
$adminService=$read('api/admin/_loyalty_quest_integrity.php');
$adminApi=$read('api/admin/loyalty-quest-integrity.php');
$adminJs=$read('assets/js/admin-loyalty-quest-integrity.js');
$adminCss=$read('assets/css/admin-loyalty-quest-integrity.css');
$permissions=$read('includes/admin-permission-matrix.php');
$sidebar=$read('includes/admin-sidebar.php');
$manifest=$read('config/migrations.php');
$docs=$read('docs/architecture/loyalty_quest_integrity_controls_v1.md');
$behavior=$read('scripts/validate_loyalty_quest_integrity_controls_behavior.php');

$reviewPos=strpos($submit,'if ($requiresReview)');
$progressPos=$reviewPos===false?false:strpos($submit,'$newProgress =',$reviewPos);
$rewardPos=$progressPos===false?false:strpos($submit,'mg_lqr_issue_reward',$progressPos);
$riskOrdering=$reviewPos!==false&&$progressPos!==false&&$rewardPos!==false&&$reviewPos<$progressPos&&$progressPos<$rewardPos;

$checks[]=['name'=>'registered additive migration','ok'=>str_contains($manifest,"'loyalty_quest_integrity_controls_v1.sql'")&&str_contains($sql,"'loyalty_quest_integrity_controls_v1'")&&str_contains($sql,'migration_key')&&str_contains($sql,'information_schema.COLUMNS')&&str_contains($sql,'information_schema.STATISTICS')];
$checks[]=['name'=>'hashed privacy schema','ok'=>str_contains($sql,'evidence_fingerprint CHAR(64)')&&str_contains($sql,'ip_hash CHAR(64)')&&str_contains($sql,'device_hash CHAR(64)')&&str_contains($sql,"integrity_status ENUM(''clear'',''review'',''blocked'',''resolved'')")&&!str_contains($sql,'ip_address VARCHAR')&&!str_contains($sql,'device_token VARCHAR')];
$checks[]=['name'=>'evidence-scoped signal dedupe','ok'=>str_contains($sql,'uq_lq_integrity_signal_dedupe (campaign_id,participant_user_id,evidence_id,signal_type,source_hash)')&&str_contains($sql,'idx_lq_integrity_attempt_request')];
$checks[]=['name'=>'fail-closed schema and secret','ok'=>str_contains($integrity,'mg_lqi_require_schema')&&str_contains($integrity,'integrity_schema_missing')&&str_contains($integrity,'MG_LOYALTY_QUEST_INTEGRITY_PEPPER')&&str_contains($integrity,'strlen($pepper) < 24')&&str_contains($integrity,'integrity_pepper_missing')];
$checks[]=['name'=>'participant IP device and account throttles','ok'=>str_contains($integrity,'loyalty_quest.start.user')&&str_contains($integrity,'loyalty_quest.start.ip')&&str_contains($integrity,'loyalty_quest.start.device')&&str_contains($integrity,'loyalty_quest.submit.user')&&str_contains($integrity,'loyalty_quest.submit.ip')&&str_contains($integrity,'loyalty_quest.submit.device')&&str_contains($start,'mg_lqi_gate_request')&&str_contains($submit,'mg_lqi_gate_request')];
$checks[]=['name'=>'explainable integrity signals','ok'=>str_contains($integrity,"'duplicate_evidence'")&&str_contains($integrity,"'shared_ip_velocity'")&&str_contains($integrity,"'shared_device_velocity'")&&str_contains($integrity,"'rapid_completion'")&&str_contains($integrity,"'rejection_history'")&&str_contains($integrity,"'reward_velocity'")&&str_contains($integrity,"'impossible_travel'")&&str_contains($integrity,"'code_velocity'")];
$checks[]=['name'=>'strong proof fingerprinting','ok'=>str_contains($integrity,'$strong[\'reference_id\']')&&str_contains($integrity,'$strong[\'proof_url\']')&&str_contains($integrity,'$strong[\'proof_note\']')&&str_contains($integrity,'mb_strlen($proofNote)>=16')];
$checks[]=['name'=>'risk review precedes progress and reward','ok'=>str_contains($submit,'$requiresReview = !$verificationPassed || $integrity[\'decision\'] === \'review\'')&&str_contains($submit,'$evidenceStatus = $requiresReview ? \'submitted\' : \'verified\'')&&str_contains($submit,"status='pending_review'")&&$riskOrdering];
$checks[]=['name'=>'participant response limits internal detail','ok'=>str_contains($submit,"'integrity_review_required'")&&!str_contains($submit,'\'signals\'=>$integrity[\'signals\']')&&!str_contains($submit,'\'integrity_score\'=>(int)$integrity[\'score\'],\'reward\'')];
$checks[]=['name'=>'merchant scoped safe signal context','ok'=>str_contains($merchant,'lqe.merchant_user_id=?')&&str_contains($merchant,'mg_lqmr_safe_integrity_context')&&str_contains($merchant,'$allowed=[\'matched_evidence_id\'')&&!str_contains($merchant,"'ip_hash'=>")&&!str_contains($merchant,"'device_hash'=>")];
$checks[]=['name'=>'confirmed abuse blocks merchant approval','ok'=>str_contains($merchant,'$integrityBlocked')&&str_contains($merchant,'administrator must clear the signal before approval')&&str_contains($merchantJs,'Administrator clearance is required before approval')&&str_contains($merchantJs,'Approval blocked')];
$checks[]=['name'=>'high risk approval acknowledgment and note','ok'=>str_contains($merchant,'$requiresAcknowledgment')&&str_contains($merchant,'mb_strlen($note)<12')&&str_contains($merchantJs,'data-integrity-acknowledged')&&str_contains($merchantJs,'note.length<12')&&str_contains($merchantView,'data-review-kpi-integrity')&&str_contains($merchantView,'data-review-kpi-blocked')];
$checks[]=['name'=>'admin permissions CSRF and reason','ok'=>str_contains($permissions,"'admin.loyalty_quest_integrity' => ['admin.operations_command.view', 'admin.operations_command.manage']")&&str_contains($adminPage,"mg_require_admin_page_permission('admin.loyalty_quest_integrity')")&&str_contains($adminApi,"'admin.operations_command.view'")&&str_contains($adminApi,"'admin.operations_command.manage'")&&str_contains($adminApi,'mg_require_csrf_for_write')&&str_contains($adminApi,'mg_lqo_require_reason')];
$checks[]=['name'=>'audited confirm and re-review clearance','ok'=>str_contains($adminService,'$resolution===\'confirmed\'')&&str_contains($adminService,"integrity_status='blocked'")&&str_contains($adminService,'$current===\'confirmed\'')&&str_contains($adminService,"['confirmed','cleared']")&&str_contains($adminService,'\'old_status\'=>$current')&&str_contains($adminService,'mg_lqo_campaign_event')&&str_contains($adminService,"mg_audit('admin.loyalty_quest_integrity_")&&str_contains($adminJs,'Clear after re-review')];
$checks[]=['name'=>'admin output minimizes sensitive evidence','ok'=>str_contains($adminService,'mg_lqi_admin_safe_context')&&str_contains($adminService,"'email_masked'")&&!str_contains($adminService,'proof_url')&&!str_contains($adminService,'proof_note')&&!str_contains($adminService,'latitude')&&!str_contains($adminService,'longitude')&&!str_contains($adminService,"'ip_hash'")&&!str_contains($adminService,"'device_hash'")];
$checks[]=['name'=>'no parallel reward claim or PPPM authority','ok'=>!str_contains($integrity,'INSERT INTO wallet_items')&&!str_contains($integrity,'UPDATE wallet_items')&&!str_contains($adminService,'INSERT INTO wallet_items')&&!str_contains($adminService,'UPDATE wallet_items')&&!str_contains($adminService,'INSERT INTO pppm_items')&&!str_contains($adminService,'UPDATE pppm_items')&&!str_contains($adminService,'UPDATE gift_claims')];
$checks[]=['name'=>'accessible responsive operations UI','ok'=>str_contains($adminPage,'aria-label="Integrity authority boundary"')&&str_contains($adminPage,'aria-live="polite"')&&str_contains($adminPage,'aria-labelledby="lqi-dialog-title"')&&str_contains($merchantView,'aria-labelledby="quest-review-dialog-title"')&&str_contains($adminCss,':focus-visible')&&str_contains($adminCss,'@media(max-width:760px)')&&str_contains($merchantCss,'.is-integrity-blocked')&&str_contains($merchantCss,':focus-visible')];
$checks[]=['name'=>'behavior coverage','ok'=>str_contains($behavior,'migration repeat execution')&&str_contains($behavior,'duplicate evidence routes review')&&str_contains($behavior,'shared device velocity')&&str_contains($behavior,'impossible travel')&&str_contains($behavior,'confirmed signal blocks evidence')&&str_contains($behavior,'confirmed signal can be cleared')&&str_contains($behavior,'sensitive context excluded')];
$checks[]=['name'=>'production boundaries documented','ok'=>str_contains($docs,'MG_LOYALTY_QUEST_INTEGRITY_PEPPER')&&str_contains($docs,'Microgift → PPPM → Inbox')&&str_contains($docs,'do not use device fingerprinting libraries')&&str_contains($docs,'Browser cookie behavior')&&str_contains($docs,'administrator clears the confirmed signal')];

$failed=array_values(array_filter($checks,static fn(array $check):bool=>!$check['ok']));
$score=max(0,10-count($failed)*.35);
echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
