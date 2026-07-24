<?php
declare(strict_types=1);

$root=dirname(__DIR__);$checks=0;$failures=[];
$pass=static function(bool $condition,string $message)use(&$checks,&$failures):void{$checks++;if($condition)return;$failures[]=$message;echo "[FAIL] {$message}\n";};
$read=static function(string $path)use($root,$pass):string{$full=$root.'/'.$path;$pass(is_file($full),'Required file exists: '.$path);return is_file($full)?(string)file_get_contents($full):'';};

$required=[
'database/20260723_investor_governance_information_rights_v5_single_install.sql',
'includes/investment/investment-governance.php','includes/investment/investment-governance-documents.php','includes/investment/investment-portal-v5.php','includes/investment/investment-service.php',
'api/admin/investor-governance.php','api/investment/portal.php','admin/investor-governance.php','admin/investment-closing.php','investor-portal.php',
'assets/css/investor-governance-v5.css','assets/css/investor-portal-v5.css','assets/js/investor-governance-v5.js','assets/js/investor-portal-v5.js',
'docs/investment/investor-governance-information-rights-v5.md'];
foreach($required as $file)$read($file);

$sql=$read('database/20260723_investor_governance_information_rights_v5_single_install.sql');
foreach([
'investment.governance.view','investment.tax_documents.view','admin.investment.governance.view','admin.investment.governance.manage','admin.investment.governance.publish','admin.investment.governance.vote','admin.investment.rights.manage','admin.investment.obligations.manage','admin.investment.tax_documents.manage',
'CREATE TABLE IF NOT EXISTS investment_governance_participants','CREATE TABLE IF NOT EXISTS investment_governance_appointments','CREATE TABLE IF NOT EXISTS investment_board_meetings','CREATE TABLE IF NOT EXISTS investment_board_meeting_attendees','CREATE TABLE IF NOT EXISTS investment_board_agenda_items','CREATE TABLE IF NOT EXISTS investment_board_packet_documents','CREATE TABLE IF NOT EXISTS investment_board_minute_versions','CREATE TABLE IF NOT EXISTS investment_written_consents','CREATE TABLE IF NOT EXISTS investment_consent_participants','CREATE TABLE IF NOT EXISTS investment_investor_rights','CREATE TABLE IF NOT EXISTS investment_reporting_obligations','CREATE TABLE IF NOT EXISTS investment_reporting_obligation_events','CREATE TABLE IF NOT EXISTS investment_holdings_references','CREATE TABLE IF NOT EXISTS investment_tax_documents','CREATE TABLE IF NOT EXISTS investment_tax_document_versions','CREATE TABLE IF NOT EXISTS investment_material_notices','CREATE TABLE IF NOT EXISTS investment_material_notice_recipients','schema_migrations'
] as $needle)$pass(str_contains($sql,$needle),'SQL contract contains '.$needle);
$pass(!str_contains($sql,'DROP TABLE'),'Phase 5 migration does not drop prior investment tables.');
$pass(!str_contains($sql,'TRUNCATE TABLE'),'Phase 5 migration does not truncate prior investment data.');
$pass(!str_contains($sql,'payment_intent'),'Phase 5 does not introduce payment processing.');
$pass(!str_contains($sql,'stripe'),'Phase 5 does not introduce Stripe processing.');
$pass(str_contains($sql,'UNIQUE KEY uq_investment_board_minutes_version'),'Meeting-minute versions are immutable and uniquely sequenced.');
$pass(str_contains($sql,'UNIQUE KEY uq_investment_tax_document_version_number'),'Tax-document versions are uniquely sequenced.');
$pass(str_contains($sql,'external_signature_reference'),'Consent responses persist external execution references.');
$pass(str_contains($sql,'verified_funded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0'),'Holdings references persist verified funded money.');
$pass(str_contains($sql,"status ENUM('published','viewed','acknowledged','revoked')"),'Material-notice receipt states are explicit.');

$governance=$read('includes/investment/investment-governance.php');
foreach([
'function mg_investment_governance_dashboard','function mg_investment_governance_save_participant','function mg_investment_governance_save_appointment','function mg_investment_governance_save_meeting','function mg_investment_governance_save_attendee','function mg_investment_governance_save_agenda','function mg_investment_governance_save_packet_document','function mg_investment_governance_save_minutes','function mg_investment_governance_save_consent','function mg_investment_governance_record_consent_response','function mg_investment_governance_save_right','function mg_investment_governance_save_obligation','function mg_investment_governance_complete_obligation','function mg_investment_governance_refresh_holdings',
'admin.investment.governance.manage','admin.investment.governance.publish','admin.investment.governance.vote','admin.investment.rights.manage','admin.investment.obligations.manage','investment_board_minute_versions','investment_reporting_obligation_events','investor_closing_records','verified_funded_cents>0','external_signature_reference','approved_for_execution','source_document_reference','counsel_status','official_stock_ledger'=>false
] as $needle)$pass(str_contains($governance,(string)$needle),'Governance service contains '.(string)$needle);
$pass(str_contains($governance,'Only funded-investor packet documents may be published'),'Packet publication is limited to funded-investor confidentiality.');
$pass(str_contains($governance,'Counsel approval or not-applicable status is required before activating an investor right'),'Active rights require counsel authority.');
$pass(str_contains($governance,'Investor rights may only be assigned to a verified funded investor'),'Rights require verified funded money.');
$pass(str_contains($governance,'Consent responses may only be recorded after approval for external execution'),'Consent response recording requires approved external execution.');
$pass(str_contains($governance,'status="superseded"'),'New approved document versions supersede prior approved versions.');
$pass(!str_contains($governance,'send_email'),'Governance operations do not automatically send email.');
$pass(!str_contains($governance,'UPDATE investment_rounds SET funded_cents'),'Governance operations do not alter official funded totals.');
$pass(!str_contains($governance,'UPDATE investor_round_interests'),'Governance operations do not alter canonical investor-round money.');

$documents=$read('includes/investment/investment-governance-documents.php');
foreach([
'function mg_investment_governance_save_tax_document','function mg_investment_governance_notice_recipient_ids','function mg_investment_governance_save_notice','function mg_investment_governance_ai_draft','investment_tax_document_versions','current_version_number','status="superseded"','investment_material_notice_recipients','acknowledged','funded_investors','major_investors','rights_holders','selected_investors','specific_investors','admin.investment.tax_documents.manage','admin.investment.governance.publish','internal editable draft','anthropic-client.php'
] as $needle)$pass(str_contains($documents,$needle),'Governance document/notice service contains '.$needle);
$pass(str_contains($documents,'Tax and annual documents may only be assigned to a verified funded investor'),'Tax documents require verified funded money.');
$pass(str_contains($documents,'Counsel approval or not-applicable status is required before publishing a material notice'),'Notice publication requires counsel authority.');
$pass(str_contains($documents,'automatic_email'=>false),'Notice audit explicitly records that email is not automatic.');
$pass(!str_contains($documents,'send_email'),'Governance notices are not automatically emailed.');
$pass(!str_contains($documents,'prepare_tax_form'),'Microgifter does not prepare tax forms.');
$pass(!str_contains($documents,'issue_securities'),'Claude governance support does not issue securities.');
$pass(str_contains($documents,'Never determine legal rights'),'Claude cannot determine legal rights.');
$pass(str_contains($documents,'modify the official stock ledger'),'Claude cannot modify the official stock ledger.');

$portal=$read('includes/investment/investment-portal-v5.php');
foreach([
'function mg_investment_portal_data_v5','function mg_investment_portal_submit_diligence_v5','function mg_investment_portal_submit_interest_v5','function mg_investment_portal_acknowledge_notice_v5','function mg_investment_portal_event_v5','mg_investment_portal_data_v4','mg_investment_portal_submit_diligence_v4','mg_investment_portal_submit_interest_v4','mg_investment_portal_event_v4','investment.governance.view','investment.tax_documents.view','verified_funded_cents>0','summary_status="published"','confidentiality="funded_investors_summary"','investor_visible=1','status="published"','meeting_summary_view','governance_document_open','tax_document_open','material_notice_view','material_notice_acknowledge'
] as $needle)$pass(str_contains($portal,$needle),'Phase 5 Investor Portal service contains '.$needle);
foreach(['internal_notes','conflict_disclosure','minutes_text','resolution_text','decision_notes','request_reason'] as $needle)$pass(!str_contains($portal,$needle),'Phase 5 Investor Portal does not expose '.$needle.'.');
$pass(!str_contains($portal,'UPDATE investment_rounds'),'Phase 5 Investor Portal cannot change official round totals.');
$pass(!str_contains($portal,'UPDATE investor_round_interests'),'Phase 5 Investor Portal cannot change canonical investor-round money.');
$pass(str_contains($portal,'investor_user_id=?'),'Investor-specific governance and tax records remain scoped to the authenticated investor.');

$adminApi=$read('api/admin/investor-governance.php');
foreach([
'mg_require_api_user()','mg_investment_require_permission','mg_require_csrf_for_write','mg_rate_limit','Cache-Control: private, no-store','save_participant','save_appointment','save_meeting','save_attendee','save_agenda','save_packet_document','save_minutes','save_consent','record_consent_response','save_right','save_obligation','complete_obligation','refresh_holdings','save_tax_document','save_notice','ai_draft'
] as $needle)$pass(str_contains($adminApi,$needle),'Governance admin API contains '.$needle);

$portalApi=$read('api/investment/portal.php');
foreach(['mg_require_api_user()','mg_require_csrf_for_write','mg_rate_limit','mg_investment_portal_data_v5','mg_investment_portal_submit_diligence_v5','mg_investment_portal_submit_interest_v5','mg_investment_portal_acknowledge_notice_v5','mg_investment_portal_event_v5'] as $needle)$pass(str_contains($portalApi,$needle),'Investor Portal API contains '.$needle);

$page=$read('admin/investor-governance.php');
foreach([
'mg-app-shell','admin-sidebar.php','mg-app-workspace','data-investor-governance','data-governance-panel="overview"','data-governance-panel="participants"','data-governance-panel="meetings"','data-governance-panel="consents"','data-governance-panel="rights"','data-governance-panel="obligations"','data-governance-panel="holdings"','data-governance-panel="tax"','data-governance-panel="notices"','data-governance-panel="assistant"','data-governance-drawer-layer'
] as $needle)$pass(str_contains($page,$needle),'Governance Command Center contains '.$needle);

$portalPage=$read('investor-portal.php');
foreach(['mg-app-shell','account-sidebar.php','mg-app-workspace','data-investor-portal','data-csrf-token','investor-portal-v4.js','investor-portal-v4.css','investor-portal-v5.js','investor-portal-v5.css'] as $needle)$pass(str_contains($portalPage,$needle),'Investor Portal page contains '.$needle);

$adminJs=$read('assets/js/investor-governance-v5.js');
foreach([
'save_participant','save_appointment','save_meeting','save_attendee','save_agenda','save_packet_document','save_minutes','save_consent','record_consent_response','save_right','save_obligation','complete_obligation','refresh_holdings','save_tax_document','save_notice','ai_draft','data-edit-participant','data-edit-meeting','data-consent-response','data-edit-right','data-complete-obligation','data-edit-tax','data-edit-notice'
] as $needle)$pass(str_contains($adminJs,$needle),'Governance Command Center runtime contains '.$needle);

$portalJs=$read('assets/js/investor-portal-v5.js');
foreach([
'Governance','Your rights and obligations','Board-approved meeting summaries','Tax and annual documents','Material notices','meeting_summary_view','governance_document_open','tax_document_open','material_notice_view','acknowledge_notice','data-v5-notice-ack','MutationObserver','observer?.disconnect()','mg-portal-v5-disclaimer'
] as $needle)$pass(str_contains($portalJs,$needle),'Phase 5 Investor Portal runtime contains '.$needle);

$service=$read('includes/investment/investment-service.php');
foreach(['investment-governance.php','investment-governance-documents.php','investment-portal-v5.php'] as $needle)$pass(str_contains($service,$needle),'Investment service loader includes '.$needle);

require_once $root.'/includes/investment/investment-service.php';
foreach([
'mg_investment_governance_dashboard','mg_investment_governance_save_meeting','mg_investment_governance_save_minutes','mg_investment_governance_record_consent_response','mg_investment_governance_save_right','mg_investment_governance_save_obligation','mg_investment_governance_refresh_holdings','mg_investment_governance_save_tax_document','mg_investment_governance_save_notice','mg_investment_governance_ai_draft','mg_investment_portal_data_v5'
] as $function)$pass(function_exists($function),'Loaded service exposes '.$function);

if($failures!==[]){fwrite(STDERR,"\n".count($failures)." validation failure(s).\n");exit(1);}echo "Investor Governance, Information Rights & Board Operations v5: {$checks}/{$checks} checks passed.\n";
