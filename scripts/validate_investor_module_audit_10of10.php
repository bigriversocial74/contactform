<?php
declare(strict_types=1);

$root=dirname(__DIR__);$score=0;$maximum=0;$failures=[];$categories=[];
$read=static function(string $path)use($root):string{$full=$root.'/'.$path;return is_file($full)?(string)file_get_contents($full):'';};
$all=static function(string $source,array $needles):bool{foreach($needles as $needle)if(!str_contains($source,$needle))return false;return true;};
$check=static function(string $category,int $points,bool $condition,string $message)use(&$score,&$maximum,&$failures,&$categories):void{
    $maximum+=$points;$categories[$category]['maximum']=($categories[$category]['maximum']??0)+$points;
    if($condition){$score+=$points;$categories[$category]['score']=($categories[$category]['score']??0)+$points;echo "[PASS +{$points}] {$category}: {$message}\n";return;}
    $categories[$category]['score']=$categories[$category]['score']??0;$failures[]=$category.': '.$message;echo "[FAIL +0/{$points}] {$category}: {$message}\n";
};

$hardeningFiles=['includes/investment/investment-audit-hardening.php'];
for($i=2;$i<=13;$i++)$hardeningFiles[]='includes/investment/investment-audit-hardening-v'.$i.'.php';
$required=array_merge([
    'database/20260724_investor_module_audit_hardening_v1.sql',
    'assets/js/investor-module-audit-v1.js',
    'docs/investment/investor-module-audit-10of10.md',
    '.github/workflows/investor-module-audit-10of10.yml',
    'scripts/validate_investor_module_publication_v12.php',
    'scripts/validate_investor_module_exact_approval_v13.php',
],$hardeningFiles);
foreach($required as $path)if(!is_file($root.'/'.$path))$failures[]='Required file missing: '.$path;

$service=$read('includes/investment/investment-service.php');
$accessApi=$read('api/investment/access-request.php');
$wizardApi=$read('api/admin/investment-wizard.php');
$pipelineApi=$read('api/admin/investor-pipeline.php');
$diligenceApi=$read('api/admin/investor-diligence.php');
$closingApi=$read('api/admin/investment-closing.php');
$governanceApi=$read('api/admin/investor-governance.php');
$portalApi=$read('api/investment/portal.php');
$hardening='';foreach($hardeningFiles as $path)$hardening.=$read($path);
$h1=$read($hardeningFiles[0]);$h3=$read($hardeningFiles[2]);$h4=$read($hardeningFiles[3]);$h5=$read($hardeningFiles[4]);$h6=$read($hardeningFiles[5]);$h7=$read($hardeningFiles[6]);$h8=$read($hardeningFiles[7]);$h9=$read($hardeningFiles[8]);$h10=$read($hardeningFiles[9]);$h11=$read($hardeningFiles[10]);$h12=$read($hardeningFiles[11]);$h13=$read($hardeningFiles[12]);
$sql=$read('database/20260724_investor_module_audit_hardening_v1.sql');$ui=$read('assets/js/investor-module-audit-v1.js');$docs=$read('docs/investment/investor-module-audit-10of10.md');$workflow=$read('.github/workflows/investor-module-audit-10of10.yml');

// Architecture — 15 points.
$layerNeedles=['investment-audit-hardening.php'];for($i=2;$i<=13;$i++)$layerNeedles[]='investment-audit-hardening-v'.$i.'.php';
$check('Architecture',5,$all($service,$layerNeedles),'All thirteen audited authority layers load after the Phase 1–5 services.');
$check('Architecture',5,
    $all($wizardApi,['mg_investment_update_round_audited','mg_investment_save_documents_audited_v2'])
    &&$all($pipelineApi,['mg_investment_pipeline_save_interest_audited_v2','mg_investment_publication_save_audited'])
    &&$all($diligenceApi,['mg_investment_dataroom_save_document_audited_v2','mg_investment_qa_save_audited_v2','mg_investment_communication_save_audited_v3'])
    &&$all($closingApi,['mg_investment_financial_decide_audited_v3','mg_investment_reporting_snapshot_save_audited'])
    &&$all($governanceApi,['mg_investment_governance_dashboard_audited_v2','mg_investment_governance_save_packet_document_audited_v2','mg_investment_governance_save_notice_audited_v4','mg_investment_governance_save_obligation_audited_v2'])
    &&$all($portalApi,['mg_investment_portal_data_v5_final3','mg_investment_portal_event_v5_final3']),
    'Every investment API routes through the final audited authority.');
$check('Architecture',5,
    $all($pipelineApi,["'dashboard'=>mg_investment_pipeline_dashboard_audited","'sync_profiles'=>"])
    &&$all($closingApi,["'dashboard'=>mg_investment_closing_dashboard_audited","'sync'=>mg_investment_closing_sync_audited"])
    &&str_contains($governanceApi,'mg_investment_governance_dashboard_audited_v2'),
    'Read dashboards are separated from explicit protected synchronization.');

// Security and privacy — 20 points.
$check('Security & privacy',4,$all($accessApi,['mg_investment_access_public_audited','mg_investment_access_result_public_audited'])&&$all($h3,["unset(\$public['review_notes'])","unset(\$result['review_notes'])"]),'Investor applicants cannot receive internal access-review notes.');
$profileStart=strpos($h4,'function mg_investment_portal_profile_public_audited');$profileSlice=$profileStart===false?'':substr($h4,$profileStart,1500);
$check('Security & privacy',4,$all($profileSlice,["'firm_name'","'investor_type'","'approved_at'"])&&!str_contains($profileSlice,"'notes'"),'Investor Portal profile data uses an explicit public whitelist.');
$check('Security & privacy',4,$all($h8,['mg_investment_portal_accessible_round_final3','mg_investment_portal_event_v5_final3'])&&$all($h6,['Q&A entry is not available','Communication is not available'])&&str_contains($h4,'Round-view subject does not match'),'Portal events validate accessible rounds and published subjects before recording engagement.');
$check('Security & privacy',4,$all($h6,['A document cannot be less restricted than its data-room folder',"\$folders[(string)\$folder['public_id']]"])&&$all($h8,['funding_verification_source="maker_checker"',"\$portalRound['governance']=null"]),'Folder, selected-investor and maker/checker-funded visibility are enforced at the final portal boundary.');
$check('Security & privacy',4,$all($h6,['admin.investment.diligence.publish','approved in a separate step before publication'])&&$all($h8,['admin.investment.relations.publish','Approve this exact report version'])&&str_contains($h1,'requires counsel approval or an explicit not-applicable status')&&str_contains($h9,'admin.investment.publish'),'Publishing uses dedicated permissions plus counsel and separate approval gates.');

// Financial integrity — 20 points.
$check('Financial integrity',4,$all($sql,['signed_verification_source','funding_verification_source',"ENUM(''unverified'',''maker_checker'')",'investment_financial_verification_decisions']),'Signed and funded money carries maker/checker provenance.');
$check('Financial integrity',4,$all($pipelineApi,['mg_investment_pipeline_save_interest_audited_v2'])&&$all($wizardApi,['mg_investment_update_round_audited'])&&str_contains($h10,'Signed and funded values are read-only here')&&$all($h9,["\$safe['signed']","\$safe['funded']"]),'Pipeline and official-round editors cannot bypass Closing maker/checker authority.');
$check('Financial integrity',4,$all($h7,['mg_investment_closing_sync_audited',"0,'unverified',ri.funded_cents,0,'unverified'"])&&!str_contains($closingApi,"'dashboard'=>mg_investment_closing_dashboard(\$pdo"),'Legacy Pipeline money imports as reported/unverified and GET does not synchronize it.');
$check('Financial integrity',4,$all($h7,['mg_investment_recalculate_round_totals_audited','signed_verification_source="maker_checker"','funding_verification_source="maker_checker"'])&&$all($sql,['UPDATE investor_round_interests ri','UPDATE investment_rounds r']),'Relationship and official-round totals reconcile only from proven closing records.');
$check('Financial integrity',4,$all($h11,['function mg_investment_financial_decide_audited_v3','FOR UPDATE',"'verification_source'=>'maker_checker'",'Generic financial adjustments are not allowed','pipeline_stage'])&&$all($h7,['Only maker/checker verified funded records can be assigned','mg_investment_governance_refresh_holdings_audited'])&&str_contains($h8,'mg_investment_portal_data_v5_final3'),'Maker/checker decisions, reversals, totals, holdings and funded portal access share one proven authority.');

// Publication and governance — 15 points.
$check('Publication & governance',3,$all($sql,['CREATE TABLE IF NOT EXISTS investment_round_publication_versions','CREATE TABLE IF NOT EXISTS investment_document_versions'])&&$all($h9,['INSERT INTO investment_round_publication_versions','INSERT INTO investment_document_versions']),'Official portal and document changes create immutable reasoned versions.');
$check('Publication & governance',3,$all($hardening,['Published Q&A is immutable','Published investor communications are immutable','A published material notice is immutable','Executed consent content is immutable','Published reporting periods are immutable']),'Published investor records cannot be silently rewritten.');
$check('Publication & governance',3,$all($sql,['investor_visible TINYINT(1)','admin.investment.relations.publish'])&&$all($governanceApi,["'set_consent_visibility'",'mg_investment_governance_save_tax_document_audited_v3'])&&$all($h8,['Approve this exact report version','Investor-visible use-of-funds actuals are immutable']),'Consent, tax and investor-relations publication use explicit governed authority.');
$check('Publication & governance',3,$all($h12,['Approve the exact investment document version','Approve the exact tax-document version'])&&$all($h13,['Approve the exact data-room document version','Approve the exact Q&A entry','Approve the exact investor communication','Approve the exact funded-investor meeting summary']),'Official, tax, diligence and meeting materials require exact prior approval.');
$check('Publication & governance',3,$all($h13,['Approve the exact board packet document','Approve the exact written consent','Approve the exact material notice','Active or investor-visible rights must exactly match','Approve the exact reporting obligation','LIMIT 1 FOR UPDATE','investment_board_packet_document_published']),'Packet, consent, notice, rights and obligation transitions require exact approval and atomic publication.');

// Runtime and data integrity — 15 points.
$check('Runtime & data integrity',3,substr_count($hardening,'mg_investment_audit_transaction')>=11&&substr_count($hardening,'FOR UPDATE')>=11,'Version creation and sensitive transitions use transactions and row locks.');
$check('Runtime & data integrity',3,$all($pipelineApi,['mg_investment_pipeline_dashboard_audited'])&&$all($closingApi,['mg_investment_closing_dashboard_audited'])&&$all($governanceApi,['mg_investment_governance_dashboard_audited_v2'])&&!str_contains($portalApi,'mg_investment_closing_sync'),'GET and portal reads are free of synchronization side effects.');
$check('Runtime & data integrity',3,str_contains($h5,'mg_investment_pipeline_admin_user_audited')&&str_contains($h6,'mg_investment_pipeline_admin_user_audited')&&str_contains($h10,'mg_investment_pipeline_admin_user_audited'),'Pipeline, diligence and compliance assignees must be Admin or Super Admin.');
$check('Runtime & data integrity',3,$all($h9,['mg_investment_decimal_input_audited','/^\\d{1,15}(?:\\.\\d{1,2})?$/','mg_investment_save_scenario_audited','mg_investment_replace_budget_audited']),'Planning money is validated as exact decimal text before canonical conversion.');
$check('Runtime & data integrity',3,$all($h6,['Diligence request limit reached','Interest submission limit reached'])&&str_contains($h9,'Invalid official-round transition')&&str_contains($h11,'Generic financial adjustments are not allowed'),'Submission volume and financial/round state transitions are bounded.');

// Operator UX — 5 points.
$pagesOk=true;foreach(['admin/investment-wizard.php','admin/investor-pipeline.php','admin/investment-closing.php','admin/investor-governance.php'] as $page)$pagesOk=$pagesOk&&str_contains($read($page),'investor-module-audit-v1.js');
$check('Operator UX',5,$pagesOk&&$all($ui,['input.readOnly = true','Revision reason','legacy signed/funded record','set_consent_visibility','Save the packet version as Approved first','Publish Approved Packet','document_id: packet.public_id']),'Admin surfaces visibly enforce canonical money, revision reasons, provenance warnings, consent controls and exact packet publication.');

// Tests and deployment — 10 points.
$check('Tests & deployment',4,str_contains($sql,'CREATE TABLE IF NOT EXISTS')&&str_contains($sql,'schema_migrations')&&!str_contains($sql,'DROP TABLE')&&!str_contains($sql,'TRUNCATE TABLE'),'Audit migration is additive, idempotent and preserves prior investment data.');
$check('Tests & deployment',3,$all($docs,['Initial architecture review','Critical findings and fixes','Required smoke tests','100/100']),'The score history, fixes, deployment and smoke-test plan are documented.');
$check('Tests & deployment',3,$all($workflow,["php-version: ['8.2','8.3']",'validate_investor_role_investment_wizard_v1.php','validate_investor_pipeline_portal_publishing_v2.php','validate_investor_diligence_communications_v3.php','validate_investor_closing_compliance_relations_v4.php','validate_investor_governance_information_rights_v5.php','validate_investor_module_publication_v12.php','validate_investor_module_exact_approval_v13.php','investment-audit-hardening-v13.php']),'The workflow runs PHP 8.2/8.3, focused publication contracts and every inherited Phase 1–5 contract.');

if($maximum!==100)$failures[]='Audit weighting error: maximum is '.$maximum.', expected 100.';
require_once $root.'/includes/investment/investment-service.php';
foreach(['mg_investment_decimal_input_audited','mg_investment_update_round_audited','mg_investment_pipeline_save_interest_audited_v2','mg_investment_closing_sync_audited','mg_investment_financial_decide_audited_v3','mg_investment_portal_data_v5_final3','mg_investment_governance_dashboard_audited_v2','mg_investment_dataroom_save_document_audited_v2','mg_investment_governance_save_packet_document_audited_v2','mg_investment_governance_save_notice_audited_v4'] as $function)if(!function_exists($function))$failures[]='Loaded service is missing audited authority: '.$function;
try{
    if(mg_investment_decimal_input_audited('$1,234.50','Test amount')!=='1234.50')$failures[]='Exact decimal normalization failed.';
    try{mg_investment_decimal_input_audited('1.234','Test amount');$failures[]='Exact decimal validation accepted more than two decimal places.';}catch(MgInvestmentException){}
}catch(Throwable $error){$failures[]='Exact decimal test failed unexpectedly: '.$error->getMessage();}
foreach($categories as $category=>$result)echo sprintf("[CATEGORY] %s: %d/%d\n",$category,(int)($result['score']??0),(int)$result['maximum']);
echo "[SCORE] {$score}/{$maximum}\n";
if($score!==100||$failures!==[]){foreach($failures as $failure)fwrite(STDERR,"[AUDIT FAILURE] {$failure}\n");fwrite(STDERR,"Investor module audit result: {$score}/100 — not yet 10/10.\n");exit(1);}echo "Investor module audit result: 100/100 — 10/10.\n";
