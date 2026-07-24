<?php
declare(strict_types=1);

$root=dirname(__DIR__);$failures=[];
$read=static function(string $path)use($root):string{$full=$root.'/'.$path;if(!is_file($full))throw new RuntimeException('Required file missing: '.$path);return (string)file_get_contents($full);};
$pass=static function(bool $condition,string $message)use(&$failures):void{if($condition){echo "[PASS] {$message}\n";return;}$failures[]=$message;echo "[FAIL] {$message}\n";};

$service=$read('includes/investment/investment-service.php');
$v12=$read('includes/investment/investment-audit-hardening-v12.php');
$wizardApi=$read('api/admin/investment-wizard.php');
$governanceApi=$read('api/admin/investor-governance.php');
$workflow=$read('.github/workflows/investor-module-audit-10of10.yml');

$pass(str_contains($service,'investment-audit-hardening-v12.php'),'Service loader includes publication hardening v12.');
$pass(str_contains($wizardApi,'mg_investment_save_documents_audited_v2'),'Investment Wizard uses approved-version document publication.');
$pass(str_contains($governanceApi,'mg_investment_governance_save_tax_document_audited_v3'),'Governance uses approved-version tax-document publication.');
$pass(str_contains($v12,'admin.investment.publish'),'Official document publication requires the dedicated publish permission.');
$pass(str_contains($v12,'admin.investment.governance.publish'),'Tax-document publication requires governance publish permission.');
$pass(str_contains($v12,'Approve the exact investment document version before publishing it.'),'Official documents require a separately approved exact version.');
$pass(str_contains($v12,'A changed investment document must be saved as an approved version'),'Changed official documents cannot bypass approval.');
$pass(str_contains($v12,'Approve the exact tax-document version before publishing it.'),'Tax documents require a separately approved exact version.');
$pass(str_contains($v12,'The published tax document must exactly match its approved version and metadata.'),'Tax publication freezes the approved version and investor-visible metadata.');
$pass(!str_contains($v12,'v.prepared_by'),'Tax publication does not query an undefined version column.');
$pass(str_contains($v12,"'external_provider'=>\$newProvider")&&str_contains($v12,"'title'=>\$newTitle"),'Tax title and external provider are part of exact approval.');
$pass(str_contains($workflow,'validate_investor_module_publication_v12.php'),'The full audit workflow runs the focused publication contract.');

require_once $root.'/includes/investment/investment-service.php';
$pass(function_exists('mg_investment_save_documents_audited_v2'),'Loaded service exposes official-document publication authority.');
$pass(function_exists('mg_investment_governance_save_tax_document_audited_v3'),'Loaded service exposes tax-document publication authority.');

if($failures!==[]){foreach($failures as $failure)fwrite(STDERR,"[PUBLICATION AUDIT FAILURE] {$failure}\n");exit(1);}echo "Investor module publication v12: 14/14 checks passed.\n";
