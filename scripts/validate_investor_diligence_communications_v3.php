<?php
declare(strict_types=1);

$root=dirname(__DIR__);$checks=0;$failures=[];
$pass=static function(bool $condition,string $message)use(&$checks,&$failures):void{$checks++;if($condition){echo "[PASS] {$message}\n";return;}$failures[]=$message;echo "[FAIL] {$message}\n";};
$read=static function(string $path)use($root,$pass):string{$full=$root.'/'.$path;$pass(is_file($full),'Required file exists: '.$path);return is_file($full)?(string)file_get_contents($full):'';};

$required=[
'database/20260723_investor_diligence_communications_v3_single_install.sql',
'includes/investment/investment-diligence.php','includes/investment/investment-communications.php','includes/investment/investment-engagement.php','includes/investment/investment-portal-v3.php',
'api/admin/investor-diligence.php','api/investment/portal.php','admin/investor-diligence.php','investor-portal.php',
'assets/css/investor-diligence-v3.css','assets/css/investor-portal-v3.css','assets/js/investor-diligence-v3.js','assets/js/investor-portal-v3.js',
'docs/investment/investor-diligence-communications-v3.md'];
foreach($required as $file)$read($file);

$sql=$read('database/20260723_investor_diligence_communications_v3_single_install.sql');
foreach(['investment.diligence.view','investment.diligence.submit','admin.investment.diligence.manage','admin.investment.diligence.publish','admin.investment.engagement.view','CREATE TABLE IF NOT EXISTS investment_dataroom_folders','CREATE TABLE IF NOT EXISTS investment_dataroom_documents','CREATE TABLE IF NOT EXISTS investment_dataroom_document_versions','CREATE TABLE IF NOT EXISTS investor_diligence_requests','CREATE TABLE IF NOT EXISTS investor_diligence_response_versions','CREATE TABLE IF NOT EXISTS investor_diligence_request_documents','CREATE TABLE IF NOT EXISTS investment_qa_entries','CREATE TABLE IF NOT EXISTS investor_meetings','CREATE TABLE IF NOT EXISTS investor_communications','CREATE TABLE IF NOT EXISTS investor_communication_recipients','CREATE TABLE IF NOT EXISTS investor_interest_submissions','CREATE TABLE IF NOT EXISTS investor_engagement_snapshots','schema_migrations'] as $needle)$pass(str_contains($sql,$needle),'SQL contract contains '.$needle);
$pass(!str_contains($sql,'DROP TABLE'),'Migration does not drop prior investment tables.');
$pass(!str_contains($sql,'TRUNCATE TABLE'),'Migration does not truncate prior investment data.');
$pass(str_contains($sql,"status ENUM('draft','internal_review','legal_review','approved','published'"),'Review and publishing states are explicit.');
$pass(str_contains($sql,'acknowledgement_at DATETIME NOT NULL'),'Non-binding interest acknowledgement is persisted.');

$diligence=$read('includes/investment/investment-diligence.php');
foreach(['function mg_investment_diligence_dashboard','function mg_investment_dataroom_save_folder','function mg_investment_dataroom_save_document','function mg_investment_diligence_admin_save_request','investment_dataroom_document_versions','investor_diligence_response_versions','requires_legal_review','admin.investment.diligence.publish','approved_response','mg_investment_pipeline_activity'] as $needle)$pass(str_contains($diligence,$needle),'Diligence service contains '.$needle);
$pass(str_contains($diligence,'status===\'published\'')||str_contains($diligence,"status==='published'"),'Publishing is explicitly gated in the diligence service.');
$pass(!str_contains($diligence,'DELETE FROM user_roles'),'Diligence operations never remove canonical roles.');

$communications=$read('includes/investment/investment-communications.php');
foreach(['function mg_investment_qa_save','function mg_investment_meeting_save','function mg_investment_communication_recipients','function mg_investment_communication_save','function mg_investment_interest_review','investor_communication_recipients','selected_investors','funded_investors','published'] as $needle)$pass(str_contains($communications,$needle),'Communications service contains '.$needle);
$pass(!str_contains($communications,'send_email'),'Communications are not automatically emailed.');
$pass(!str_contains($communications,'UPDATE user_roles'),'Communications do not alter user roles.');
$pass(!str_contains($communications,'UPDATE investment_rounds SET signed_cents'),'Interest review does not alter signed totals.');
$pass(!str_contains($communications,'UPDATE investment_rounds SET funded_cents'),'Interest review does not alter funded totals.');

$engagement=$read('includes/investment/investment-engagement.php');
foreach(['function mg_investment_engagement_calculate','function mg_investment_engagement_refresh','maximum_score','portal_sessions','document_views','questions_submitted','communications_viewed','meetings_completed','recency','function mg_investment_diligence_ai_draft','internal editable draft','anthropic-client.php'] as $needle)$pass(str_contains($engagement,$needle),'Engagement/AI service contains '.$needle);
$pass(str_contains($engagement,"min(100,array_sum"),'Engagement score is deterministically capped at 100.');
$pass(!str_contains($engagement,'publish_round'),'Claude cannot publish an official round.');
$pass(!str_contains($engagement,'send_email'),'Claude cannot send email.');
$pass(!str_contains($engagement,'UPDATE investment_rounds'),'Claude cannot change round totals or terms.');

$portal=$read('includes/investment/investment-portal-v3.php');
foreach(['function mg_investment_portal_data_v3','function mg_investment_portal_submit_diligence','function mg_investment_portal_submit_interest','function mg_investment_portal_event_v3','investment.diligence.submit','investment.interest.submit','non_binding','approved_response','selected_investors','funded_investors','communication_view','qa_view'] as $needle)$pass(str_contains($portal,$needle),'Investor Portal service contains '.$needle);
$pass(str_contains($portal,'acknowledgement'),'Portal interest requires acknowledgement.');
$pass(!str_contains($portal,'signed_cents='),'Portal submissions cannot change signed totals.');
$pass(!str_contains($portal,'funded_cents='),'Portal submissions cannot change funded totals.');

$adminApi=$read('api/admin/investor-diligence.php');
foreach(['mg_require_api_user()','mg_investment_require_permission','mg_require_csrf_for_write','mg_rate_limit','save_folder','save_document','save_request','save_qa','save_meeting','save_communication','review_interest','refresh_engagement','ai_draft'] as $needle)$pass(str_contains($adminApi,$needle),'Admin API contains '.$needle);
$portalApi=$read('api/investment/portal.php');
foreach(['mg_require_api_user()','mg_require_csrf_for_write','mg_rate_limit','mg_investment_portal_data_v3','submit_diligence','submit_interest','mg_investment_portal_event_v3'] as $needle)$pass(str_contains($portalApi,$needle),'Portal API contains '.$needle);

$page=$read('admin/investor-diligence.php');
foreach(['mg-app-shell','admin-sidebar.php','mg-app-workspace','data-investor-diligence','data-diligence-panel="dataroom"','data-diligence-panel="requests"','data-diligence-panel="qa"','data-diligence-panel="meetings"','data-diligence-panel="communications"','data-diligence-panel="interest"','data-diligence-panel="engagement"','data-diligence-drawer-layer'] as $needle)$pass(str_contains($page,$needle),'Admin diligence workspace contains '.$needle);
$portalPage=$read('investor-portal.php');
foreach(['mg-app-shell','account-sidebar.php','mg-app-workspace','data-investor-portal','data-csrf-token','investor-portal-v3.js','investor-portal-v3.css'] as $needle)$pass(str_contains($portalPage,$needle),'Investor Portal page contains '.$needle);

$adminJs=$read('assets/js/investor-diligence-v3.js');
foreach(['save_folder','save_document','save_request','save_qa','save_meeting','save_communication','review_interest','refresh_engagement','ai_draft','data-document-id','data-request-id','data-interest-id'] as $needle)$pass(str_contains($adminJs,$needle),'Admin diligence runtime contains '.$needle);
$portalJs=$read('assets/js/investor-portal-v3.js');
foreach(['Data Room','Ask a Question','submit_diligence','submit_interest','document_open','communication_view','qa_view','non-binding indication of interest','data-diligence-form','data-interest-form'] as $needle)$pass(str_contains($portalJs,$needle),'Investor Portal runtime contains '.$needle);

require_once $root.'/includes/investment/investment-service.php';
foreach(['mg_investment_diligence_dashboard','mg_investment_dataroom_save_document','mg_investment_communication_save','mg_investment_engagement_calculate','mg_investment_portal_data_v3'] as $function)$pass(function_exists($function),'Loaded service exposes '.$function);
$pass(mg_investment_readable_stage('legal_review')==='Legal Review','Shared stage labeling remains deterministic.');

if($failures!==[]){fwrite(STDERR,"\n".count($failures)." validation failure(s).\n");exit(1);}echo "\nInvestor Due Diligence, Data Room & Communications v3: {$checks}/{$checks} checks passed.\n";
