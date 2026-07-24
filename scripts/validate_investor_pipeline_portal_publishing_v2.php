<?php
declare(strict_types=1);

$root=dirname(__DIR__);$checks=0;$failures=[];
$pass=static function(bool $condition,string $message)use(&$checks,&$failures):void{$checks++;if($condition){echo "[PASS] {$message}\n";return;}$failures[]=$message;echo "[FAIL] {$message}\n";};
$read=static function(string $path)use($root,$pass):string{$full=$root.'/'.$path;$pass(is_file($full),'Required file exists: '.$path);return is_file($full)?(string)file_get_contents($full):'';};

$required=[
'database/20260723_investor_pipeline_portal_publishing_v2_single_install.sql',
'includes/investment/investment-pipeline.php','includes/investment/investment-pipeline-queries.php','includes/investment/investment-publishing.php','includes/investment/investment-portal-v2.php',
'api/admin/investor-pipeline.php','api/investment/portal.php','admin/investor-pipeline.php','assets/css/investor-pipeline-v2.css','assets/js/investor-pipeline-v2.js','assets/js/investor-portal-v2.js','docs/investment/investor-pipeline-portal-publishing-v2.md'];
foreach($required as $file)$read($file);

$sql=$read('database/20260723_investor_pipeline_portal_publishing_v2_single_install.sql');
foreach(['admin.investor_pipeline.view','admin.investor_pipeline.manage','admin.investment.publish','admin.investment.metrics.refresh','CREATE TABLE IF NOT EXISTS investor_pipeline_records','CREATE TABLE IF NOT EXISTS investor_round_interests','CREATE TABLE IF NOT EXISTS investor_pipeline_activities','CREATE TABLE IF NOT EXISTS investor_follow_up_tasks','CREATE TABLE IF NOT EXISTS investment_round_publication','CREATE TABLE IF NOT EXISTS investment_portal_events','CREATE TABLE IF NOT EXISTS investment_metric_adapters','registered_users','active_merchants','published_products','active_campaigns','completed_orders','funded_round_total','schema_migrations'] as $needle)$pass(str_contains($sql,$needle),'SQL contract contains '.$needle);
$pass(!str_contains($sql,'DROP TABLE'),'Migration does not drop Phase 1 tables.');
$pass(!str_contains($sql,'TRUNCATE TABLE'),'Migration does not truncate existing data.');

$pipeline=$read('includes/investment/investment-pipeline.php').$read('includes/investment/investment-pipeline-queries.php');
foreach(['function mg_investment_pipeline_sync_profiles','function mg_investment_pipeline_dashboard_v2','function mg_investment_pipeline_detail','function mg_investment_pipeline_save_record_v2','function mg_investment_pipeline_add_activity','function mg_investment_pipeline_save_task','function mg_investment_pipeline_complete_task','function mg_investment_pipeline_save_interest','function mg_investment_pipeline_set_access','soft_commitment_cents','signed_cents','funded_cents','investment_round_access','UPDATE investment_rounds SET soft_commitment_cents','function mg_investment_metric_history_v2','variance_to_target'] as $needle)$pass(str_contains($pipeline,$needle),'Pipeline service contains '.$needle);
$pass(str_contains($pipeline,'DELETE FROM user_roles')===false,'Pipeline operations never remove canonical user roles.');
$pass(str_contains($pipeline,'send_email')===false,'Pipeline operations do not automatically send email.');

$publishing=$read('includes/investment/investment-publishing.php').$read('includes/investment/investment-portal-v2.php');
foreach(['function mg_investment_publication_default_sections','function mg_investment_publication_save','function mg_investment_publication_preview','private_preview','published','counsel_status','function mg_investment_metric_adapter_value','function mg_investment_metrics_refresh','investment_metric_snapshots','system_calculated','function mg_investment_portal_log','function mg_investment_pipeline_ai_draft','function mg_investment_portal_data_v2','selected_investors','funded_investors','function mg_investment_portal_event_v2'] as $needle)$pass(str_contains($publishing,$needle),'Publishing/evidence service contains '.$needle);
$pass(!str_contains($publishing,'publish_round'),'Claude cannot directly publish an official round.');
$pass(!str_contains($publishing,'UPDATE user_roles'),'Claude and publishing do not alter user roles.');
$pass(str_contains($publishing,'status="published"'),'Portal documents require published status.');

$api=$read('api/admin/investor-pipeline.php');
foreach(['mg_require_api_user()','mg_investment_require_permission','mg_require_csrf_for_write','mg_rate_limit','save_record','add_activity','save_task','complete_task','save_interest','set_access','save_publication','refresh_metrics','ai_draft'] as $needle)$pass(str_contains($api,$needle),'Admin API contains '.$needle);
$portalApi=$read('api/investment/portal.php');
foreach(['mg_require_api_user()','mg_require_csrf_for_write','mg_investment_portal_data_v2','mg_investment_portal_event_v2'] as $needle)$pass(str_contains($portalApi,$needle),'Portal API contains '.$needle);

$page=$read('admin/investor-pipeline.php');
foreach(['mg-app-shell','admin-sidebar.php','mg-app-workspace','data-investor-pipeline','data-tab-panel="pipeline"','data-tab-panel="publishing"','data-tab-panel="metrics"','data-pipeline-drawer-layer'] as $needle)$pass(str_contains($page,$needle),'Admin workspace contains '.$needle);
$js=$read('assets/js/investor-pipeline-v2.js');
foreach(['save_record','add_activity','save_task','complete_task','save_interest','set_access','save_publication','refresh_metrics','ai_draft','Investor View Preview','data-document-id'] as $needle)$pass(str_contains($js,$needle),'Pipeline runtime contains '.$needle);
$portalJs=$read('assets/js/investor-portal-v2.js');
foreach(['founder_update','important_notice','round_terms','raise_progress','use_of_funds','evidence_metrics','documents','document_open','metric_view'] as $needle)$pass(str_contains($portalJs,$needle),'Investor Portal runtime contains '.$needle);

require_once $root.'/includes/investment/investment-service.php';
$sections=mg_investment_publication_default_sections();
$pass(count($sections)===9,'Publication contract exposes nine independently controlled sections.');
$pass($sections['round_terms']===true&&$sections['documents']===true,'Round terms and documents default to enabled for preview.');
$pass(mg_investment_readable_stage('due_diligence')==='Due Diligence','Pipeline stage labels are deterministic.');

if($failures!==[]){fwrite(STDERR,"\n".count($failures)." validation failure(s).\n");exit(1);}echo "\nInvestor Pipeline, Portal Publishing & Live Evidence v2: {$checks}/{$checks} checks passed.\n";
