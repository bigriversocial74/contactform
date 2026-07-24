<?php
declare(strict_types=1);

$root=dirname(__DIR__);$failures=[];$checks=0;
$read=static function(string $path)use($root):string{$full=$root.'/'.$path;if(!is_file($full))throw new RuntimeException('Required file missing: '.$path);return (string)file_get_contents($full);};
$pass=static function(bool $condition,string $message)use(&$failures,&$checks):void{$checks++;if($condition){echo "[PASS] {$message}\n";return;}$failures[]=$message;echo "[FAIL] {$message}\n";};
$all=static function(string $source,array $needles):bool{foreach($needles as $needle)if(!str_contains($source,$needle))return false;return true;};

$service=$read('includes/investment/investment-service.php');
$v13=$read('includes/investment/investment-audit-hardening-v13.php');
$v14=$read('includes/investment/investment-audit-hardening-v14.php');
$diligence=$read('api/admin/investor-diligence.php');
$governance=$read('api/admin/investor-governance.php');
$ui=$read('assets/js/investor-module-audit-v1.js');
$workflow=$read('.github/workflows/investor-module-audit-10of10.yml');

$pass($all($service,['investment-audit-hardening-v13.php','investment-audit-hardening-v14.php']),'Service loader activates exact-approval hardening v13 and metadata hardening v14.');
$pass($all($diligence,['mg_investment_dataroom_save_document_audited_v2','mg_investment_qa_save_audited_v2','mg_investment_communication_save_audited_v3']),'Diligence publication routes through exact approved-content authorities.');
$pass($all($governance,['mg_investment_governance_dashboard_audited_v2','mg_investment_governance_save_meeting_audited_v3','mg_investment_governance_save_packet_document_audited_v2','mg_investment_governance_save_consent_audited_v2','mg_investment_governance_save_notice_audited_v5','mg_investment_governance_save_right_audited_v3','mg_investment_governance_save_obligation_audited_v3']),'Governance routes every investor-visible transition through the final authority.');
$pass($all($v13,['Approve the exact data-room document version','Approve the exact Q&A entry','Approve the exact investor communication','Approve the exact funded-investor meeting summary','Approve the exact board packet document','Approve the exact written consent','Approve the exact material notice','Active or investor-visible rights must exactly match','Approve the exact reporting obligation']),'Every investor-visible record requires prior exact content approval.');
$pass($all($v14,['Published meeting status and counsel authority must exactly match','Published notice counsel authority must exactly match','Published obligation status and counsel-review requirement must exactly match']),'Final investor-visible status and approval metadata are frozen.');
$pass($all($v13,['function mg_investment_governance_dashboard_audited_v2','packet_documents','meeting_public_id']),'Governance dashboard returns packet-version identities for controlled publication.');
$pass($all($v13,['LIMIT 1 FOR UPDATE','status="published",published_at=NOW()','investment_board_packet_document_published']),'Packet publication locks and publishes the approved row atomically.');
$pass(!str_contains($v13,"'external_reference'=>mg_investment_audit_nullable_text(\$input['external_reference']"),'Packet validation does not reference a nonexistent packet column.');
$pass(str_contains($v13,"['approved','published']")&&str_contains($v13,'Approve the exact reporting obligation before publishing it.'),'Obligation publication uses the actual approved state.');
$pass($all($ui,['Save the packet version as Approved first','Publish Approved Packet','document_id: packet.public_id','option[value="published"]']),'Operator UI removes direct packet publication and publishes a selected approved version.');
$pass(str_contains($workflow,'validate_investor_module_exact_approval_v13.php')&&str_contains($workflow,'investment-audit-hardening-v14.php'),'Full audit workflow runs the v13/v14 exact-approval contract.');

require_once $root.'/includes/investment/investment-service.php';
foreach(['mg_investment_governance_dashboard_audited_v2','mg_investment_dataroom_save_document_audited_v2','mg_investment_qa_save_audited_v2','mg_investment_communication_save_audited_v3','mg_investment_governance_save_packet_document_audited_v2','mg_investment_governance_save_meeting_audited_v3','mg_investment_governance_save_notice_audited_v5','mg_investment_governance_save_obligation_audited_v3'] as $function)$pass(function_exists($function),'Loaded service exposes '.$function.'.');

if($failures!==[]){foreach($failures as $failure)fwrite(STDERR,"[EXACT APPROVAL FAILURE] {$failure}\n");exit(1);}echo "Investor module exact approval v13/v14: {$checks}/{$checks} checks passed.\n";
