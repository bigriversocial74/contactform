<?php
declare(strict_types=1);

$root=dirname(__DIR__);$checks=0;$failures=[];
$pass=static function(bool $condition,string $message)use(&$checks,&$failures):void{$checks++;if($condition)return;$failures[]=$message;echo "[FAIL] {$message}\n";};
$read=static function(string $path)use($root,$pass):string{$full=$root.'/'.$path;$pass(is_file($full),'Required file exists: '.$path);return is_file($full)?(string)file_get_contents($full):'';};

$required=[
'database/20260724_investor_closing_compliance_relations_v4_single_install.sql',
'includes/investment/investment-closing.php','includes/investment/investment-compliance.php','includes/investment/investment-relations.php','includes/investment/investment-portal-v4.php',
'api/admin/investment-closing.php','api/investment/portal.php','admin/investment-closing.php','investor-portal.php',
'assets/css/investment-closing-v4.css','assets/css/investor-portal-v4.css','assets/js/investment-closing-v4.js','assets/js/investor-portal-v4.js',
'docs/investment/investor-closing-compliance-relations-v4.md'];
foreach($required as $file)$read($file);

$sql=$read('database/20260724_investor_closing_compliance_relations_v4_single_install.sql');
foreach([
'investment.closing.view','investment.relations.view','admin.investment.closing.view','admin.investment.closing.manage','admin.investment.closing.verify','admin.investment.compliance.manage','admin.investment.relations.manage',
'CREATE TABLE IF NOT EXISTS investment_closing_profiles','CREATE TABLE IF NOT EXISTS investment_closing_batches','CREATE TABLE IF NOT EXISTS investor_closing_records','CREATE TABLE IF NOT EXISTS investor_closing_events','CREATE TABLE IF NOT EXISTS investment_closing_batch_investors','CREATE TABLE IF NOT EXISTS investment_compliance_requirements','CREATE TABLE IF NOT EXISTS investment_compliance_events','CREATE TABLE IF NOT EXISTS investor_onboarding_reviews','CREATE TABLE IF NOT EXISTS investment_closing_packets','CREATE TABLE IF NOT EXISTS investment_closing_documents','CREATE TABLE IF NOT EXISTS investment_financial_verification_requests','CREATE TABLE IF NOT EXISTS investment_financial_verification_decisions','CREATE TABLE IF NOT EXISTS investment_cap_reconciliation_snapshots','CREATE TABLE IF NOT EXISTS investment_reporting_periods','CREATE TABLE IF NOT EXISTS investment_reporting_snapshots','CREATE TABLE IF NOT EXISTS investment_use_of_funds_actuals','schema_migrations'
] as $needle)$pass(str_contains($sql,$needle),'SQL contract contains '.$needle);
$pass(!str_contains($sql,'DROP TABLE'),'Phase 4 migration does not drop prior investment tables.');
$pass(!str_contains($sql,'TRUNCATE TABLE'),'Phase 4 migration does not truncate prior investment data.');
$pass(str_contains($sql,"verification_type ENUM('signed_amount','funded_amount','funded_reversal','signed_reversal','adjustment')"),'Financial verification types are explicit.');
$pass(str_contains($sql,"status ENUM('pending','approved','rejected','cancelled')"),'Financial requests have controlled decision states.');
$pass(str_contains($sql,'UNIQUE KEY uq_investment_financial_decision_request'),'Each financial verification request has at most one decision.');
$pass(str_contains($sql,'locked_at DATETIME NULL'),'Completed closing batches persist a lock timestamp.');
$pass(str_contains($sql,'verified_funded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0'),'Verified funded money is distinct from reported money.');
$pass(str_contains($sql,'reported_funded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0'),'Reported funded money is persisted separately.');
$pass(!str_contains($sql,'payment_intent'),'Phase 4 does not introduce payment processing.');
$pass(!str_contains($sql,'stripe'),'Phase 4 does not introduce Stripe processing.');

$closing=$read('includes/investment/investment-closing.php');
foreach([
'function mg_investment_closing_sync','function mg_investment_closing_record','function mg_investment_closing_event','function mg_investment_closing_dashboard','function mg_investment_closing_save_profile','function mg_investment_closing_save_record','function mg_investment_closing_save_batch','function mg_investment_closing_assign_batch','function mg_investment_closing_complete_batch','function mg_investment_closing_reopen_batch','function mg_investment_onboarding_save','function mg_investment_packet_save','function mg_investment_packet_document_save','Completed closing batches are immutable','Only Super Admin can reopen','verified_funded_cents','investor_closing_events','investment_closing_batch_investors'
] as $needle)$pass(str_contains($closing,$needle),'Closing service contains '.$needle);
$pass(str_contains($closing,'admin.investment.closing.manage'),'Closing writes require closing-management permission.');
$pass(str_contains($closing,'mg_investment_is_super'),'Reopening a completed batch requires the Super Admin authority.');
$pass(str_contains($closing,'counsel_status')&&str_contains($closing,'board_status'),'Batch completion checks counsel and board status.');
$pass(str_contains($closing,'verified_funded_cents<1'),'Batch completion rejects unverified funded records.');
$pass(str_contains($closing,'status<>"complete"'),'Batch completion checks existing document packets.');
$pass(!str_contains($closing,'send_email'),'Closing services do not automatically send email.');
$pass(!str_contains($closing,'payment_intent'),'Closing services do not create payment intents.');
$pass(!str_contains($closing,'UPDATE user_roles'),'Closing services do not alter canonical roles.');

$compliance=$read('includes/investment/investment-compliance.php');
foreach([
'function mg_investment_compliance_seed','function mg_investment_compliance_save','function mg_investment_financial_request','function mg_investment_financial_decide','function mg_investment_recalculate_round_totals','function mg_investment_reconciliation_create','function mg_investment_closing_refresh_readiness','investment_financial_verification_decisions','submitted_by_user_id','requested_amount_cents','previous_amount_cents','investor_round_interests','UPDATE investment_rounds SET soft_commitment_cents','administrative estimate','not the official stock ledger'
] as $needle)$pass(str_contains($compliance,$needle),'Compliance/verification service contains '.$needle);
$pass(str_contains($compliance,"submitted_by_user_id']===$actorId")||str_contains($compliance,"submitted_by_user_id'] === $actorId"),'Maker/checker blocks self-review.');
$pass(str_contains($compliance,'admin.investment.closing.verify'),'Financial decisions require verification permission.');
$pass(str_contains($compliance,'status<>"pending"')||str_contains($compliance,"status']!=='pending'"),'Resolved financial requests cannot be decided twice.');
$pass(str_contains($compliance,'funded amount cannot exceed')||str_contains($compliance,'funded amount cannot exceed the verified signed amount'),'Funded verification is bounded by signed money.');
$pass(str_contains($compliance,'UPDATE investor_round_interests SET signed_cents'),'Approved signed verification updates the canonical investor-round record.');
$pass(str_contains($compliance,'UPDATE investor_round_interests SET funded_cents'),'Approved funded verification updates the canonical investor-round record.');
$pass(!str_contains($compliance,'send_email'),'Compliance and verification do not automatically send email.');
$pass(!str_contains($compliance,'accreditation_verified_by_microgifter'),'Microgifter does not claim to verify accreditation.');
$pass(!str_contains($compliance,'submit_form_d'),'Microgifter does not submit Form D.');

$relations=$read('includes/investment/investment-relations.php');
foreach([
'function mg_investment_relations_detail','function mg_investment_reporting_period_save','function mg_investment_reporting_snapshot_save','function mg_investment_use_of_funds_actual_save','function mg_investment_closing_ai_draft','investment_reporting_snapshots','version_number','status="superseded"','investor_visible','internal editable draft','anthropic-client.php','cannot verify funds','official stock ledger'
] as $needle)$pass(str_contains($relations,$needle),'Relations/AI service contains '.$needle);
$pass(str_contains($relations,'admin.investment.relations.manage'),'Investor reporting writes require relations-management permission.');
$pass(str_contains($relations,'status="published"'),'Only explicit publishing creates funded-investor reports.');
$pass(!str_contains($relations,'send_email'),'Investor relations do not automatically send email.');
$pass(!str_contains($relations,'process_payment'),'Claude and relations do not process payment.');
$pass(!str_contains($relations,'issue_securities'),'Claude and relations do not issue securities.');

$portal=$read('includes/investment/investment-portal-v4.php');
foreach([
'function mg_investment_portal_data_v4','function mg_investment_portal_submit_diligence_v4','function mg_investment_portal_submit_interest_v4','function mg_investment_portal_event_v4','mg_investment_portal_data_v3','mg_investment_portal_event_v3','verified_funded_cents>0','investment.closing.view','investment.relations.view','status="published"','investor_visible=1','closing_document_open','report_view'
] as $needle)$pass(str_contains($portal,$needle),'Phase 4 Investor Portal service contains '.$needle);
$pass(!str_contains($portal,'internal_notes'),'Investor Portal does not expose internal closing notes.');
$pass(!str_contains($portal,'decision_notes'),'Investor Portal does not expose financial decision notes.');
$pass(!str_contains($portal,'restriction_notes'),'Investor Portal does not expose onboarding restriction notes.');
$pass(!str_contains($portal,'request_reason'),'Investor Portal does not expose financial-verification request reasons.');
$pass(!str_contains($portal,'UPDATE investment_rounds'),'Investor Portal cannot change official round totals.');
$pass(!str_contains($portal,'UPDATE investor_round_interests'),'Investor Portal cannot change canonical investor-round money.');

$adminApi=$read('api/admin/investment-closing.php');
foreach([
'mg_require_api_user()','mg_investment_require_permission','mg_require_csrf_for_write','mg_rate_limit','Cache-Control: private, no-store','save_profile','save_record','save_batch','assign_batch','complete_batch','reopen_batch','save_onboarding','save_packet','save_document','seed_compliance','save_compliance','request_verification','decide_verification','create_reconciliation','refresh_readiness','save_period','save_snapshot','save_actual','ai_draft'
] as $needle)$pass(str_contains($adminApi,$needle),'Closing admin API contains '.$needle);

$portalApi=$read('api/investment/portal.php');
foreach(['mg_require_api_user()','mg_require_csrf_for_write','mg_rate_limit','mg_investment_portal_data_v4','mg_investment_portal_submit_diligence_v4','mg_investment_portal_submit_interest_v4','mg_investment_portal_event_v4'] as $needle)$pass(str_contains($portalApi,$needle),'Investor Portal API contains '.$needle);

$page=$read('admin/investment-closing.php');
foreach([
'mg-app-shell','admin-sidebar.php','mg-app-workspace','data-investment-closing','data-closing-panel="overview"','data-closing-panel="investors"','data-closing-panel="batches"','data-closing-panel="compliance"','data-closing-panel="verification"','data-closing-panel="packets"','data-closing-panel="reconciliation"','data-closing-panel="reports"','data-closing-drawer-layer'
] as $needle)$pass(str_contains($page,$needle),'Closing Command Center contains '.$needle);

$portalPage=$read('investor-portal.php');
foreach(['mg-app-shell','account-sidebar.php','mg-app-workspace','data-investor-portal','data-csrf-token','investor-portal-v4.js','investor-portal-v4.css'] as $needle)$pass(str_contains($portalPage,$needle),'Investor Portal page contains '.$needle);

$adminJs=$read('assets/js/investment-closing-v4.js');
foreach([
'save_profile','save_record','save_batch','assign_batch','complete_batch','reopen_batch','save_onboarding','save_packet','save_document','seed_compliance','save_compliance','request_verification','decide_verification','create_reconciliation','refresh_readiness','save_period','save_snapshot','save_actual','ai_draft','data-record-edit','data-verification-review','data-packet-edit'
] as $needle)$pass(str_contains($adminJs,$needle),'Closing Command Center runtime contains '.$needle);

$portalJs=$read('assets/js/investor-portal-v4.js');
foreach([
'Round Summary','Data Room','Ask a Question','submit_diligence','submit_interest','document_open','communication_view','qa_view','non-binding indication of interest','Investment Relations','closing_document_open','report_view','Verified funded amount','Administrative estimate only'
] as $needle)$pass(str_contains($portalJs,$needle),'Phase 4 Investor Portal runtime contains '.$needle);

$service=$read('includes/investment/investment-service.php');
foreach(['investment-closing.php','investment-compliance.php','investment-relations.php','investment-portal-v4.php'] as $needle)$pass(str_contains($service,$needle),'Investment service loader includes '.$needle);

require_once $root.'/includes/investment/investment-service.php';
foreach([
'mg_investment_closing_dashboard','mg_investment_closing_save_record','mg_investment_financial_request','mg_investment_financial_decide','mg_investment_reconciliation_create','mg_investment_reporting_snapshot_save','mg_investment_portal_data_v4'
] as $function)$pass(function_exists($function),'Loaded service exposes '.$function);
$pass(mg_investment_readable_stage('funds_verified')==='Funds Verified','Closing stage labels remain deterministic.');

if($failures!==[]){fwrite(STDERR,"\n".count($failures)." validation failure(s).\n");exit(1);}echo "Investor Closing, Compliance & Post-Investment Relations v4: {$checks}/{$checks} checks passed.\n";
